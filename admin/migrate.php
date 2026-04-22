<?php
/**
 * Cloud Sekolah - Migration Script
 * Migrasi data dari sistem lama (flat file) ke Physical Filesystem
 * 
 * Jalankan script ini SATU KALI setelah update kode.
 * Script ini akan:
 * 1. Membuat folder fisik untuk semua folder yang ada di database
 * 2. Memindahkan file dari posisi flat ke dalam folder fisiknya
 * 3. Mengupdate path file di database
 * 
 * PENTING: Backup database dan folder uploads sebelum menjalankan script ini!
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// Hanya admin yang boleh menjalankan migrasi
if (!isAdmin()) {
    die('Unauthorized. Silakan login sebagai admin terlebih dahulu.');
}

$db = getDB();
$log = [];
$errors = [];

// Ensure uploads directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

echo "<!DOCTYPE html><html><head><title>Migrasi Physical Filesystem</title>";
echo '<script src="https://cdn.tailwindcss.com"></script>';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
echo '</head><body class="bg-slate-100 min-h-screen p-8" style="font-family:Inter,sans-serif">';
echo '<div class="max-w-3xl mx-auto">';
echo '<div class="bg-white rounded-2xl shadow-lg p-8">';
echo '<h1 class="text-2xl font-bold text-slate-800 mb-2">🔄 Migrasi Physical Filesystem</h1>';
echo '<p class="text-slate-500 text-sm mb-6">Memindahkan dari sistem flat file ke folder fisik...</p>';

// ==========================================
// STEP 1: Create physical folders
// ==========================================
echo '<h2 class="text-lg font-semibold text-slate-700 mb-3 mt-6">📁 Step 1: Membuat Folder Fisik</h2>';

$stmt = $db->query("SELECT id, name, parent_id FROM folders ORDER BY id ASC");
$allFolders = $stmt->fetchAll();
$folderCount = 0;

foreach ($allFolders as $folder) {
    $physicalPath = getFolderPhysicalPath($folder['id']);
    
    if (!is_dir($physicalPath)) {
        if (mkdir($physicalPath, 0755, true)) {
            echo '<div class="text-sm text-emerald-600 ml-4 mb-1">✅ Dibuat: ' . htmlspecialchars($physicalPath) . '</div>';
            $folderCount++;
        } else {
            echo '<div class="text-sm text-red-600 ml-4 mb-1">❌ Gagal: ' . htmlspecialchars($physicalPath) . '</div>';
            $errors[] = "Gagal membuat folder: $physicalPath";
        }
    } else {
        echo '<div class="text-sm text-slate-400 ml-4 mb-1">⏭️ Sudah ada: ' . htmlspecialchars($physicalPath) . '</div>';
    }
}

echo "<div class='text-sm font-medium text-slate-600 mt-2 ml-4'>Total folder dibuat: $folderCount</div>";

// ==========================================
// STEP 2: Move files to their physical folders
// ==========================================
echo '<h2 class="text-lg font-semibold text-slate-700 mb-3 mt-6">📄 Step 2: Memindahkan File</h2>';

$stmt = $db->query("SELECT f.*, fo.id as f_id FROM files f LEFT JOIN folders fo ON f.folder_id = fo.id ORDER BY f.id ASC");
$allFiles = $stmt->fetchAll();
$fileCount = 0;
$skippedCount = 0;

foreach ($allFiles as $file) {
    $currentPath = UPLOAD_DIR . $file['path'];
    
    // Determine the correct target directory
    $targetDir = getFolderPhysicalPath($file['folder_id']);
    $folderRelPath = getFolderRelativePath($file['folder_id']);
    
    // Use original name for the file
    $targetName = getUniqueFileName($targetDir, $file['original_name']);
    $targetPath = $targetDir . $targetName;
    
    // New relative path for database
    $newRelativePath = ($folderRelPath ? $folderRelPath . '/' : '') . $targetName;
    
    // Check if file is already at the correct location
    if ($file['path'] === $newRelativePath && file_exists($currentPath)) {
        echo '<div class="text-sm text-slate-400 ml-4 mb-1">⏭️ Sudah benar: ' . htmlspecialchars($file['original_name']) . '</div>';
        $skippedCount++;
        continue;
    }
    
    // Check if source file exists
    if (!file_exists($currentPath)) {
        echo '<div class="text-sm text-amber-600 ml-4 mb-1">⚠️ File fisik tidak ditemukan: ' . htmlspecialchars($file['original_name']) . ' (' . htmlspecialchars($file['path']) . ')</div>';
        $errors[] = "File tidak ditemukan: " . $file['path'];
        continue;
    }
    
    // Ensure target directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Move file
    if (rename($currentPath, $targetPath)) {
        // Update database
        $stmtUpdate = $db->prepare("UPDATE files SET name = ?, path = ? WHERE id = ?");
        $stmtUpdate->execute([$targetName, $newRelativePath, $file['id']]);
        
        echo '<div class="text-sm text-emerald-600 ml-4 mb-1">✅ Dipindahkan: ' . htmlspecialchars($file['original_name']) . ' → ' . htmlspecialchars($newRelativePath) . '</div>';
        $fileCount++;
    } else {
        echo '<div class="text-sm text-red-600 ml-4 mb-1">❌ Gagal memindahkan: ' . htmlspecialchars($file['original_name']) . '</div>';
        $errors[] = "Gagal memindahkan: " . $file['original_name'];
    }
}

echo "<div class='text-sm font-medium text-slate-600 mt-2 ml-4'>File dipindahkan: $fileCount | Sudah benar: $skippedCount</div>";

// ==========================================
// SUMMARY
// ==========================================
echo '<div class="mt-8 p-4 rounded-xl ' . (empty($errors) ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200') . '">';
if (empty($errors)) {
    echo '<p class="text-emerald-700 font-semibold">✅ Migrasi selesai tanpa error!</p>';
    echo '<p class="text-emerald-600 text-sm mt-1">Folder dibuat: ' . $folderCount . ' | File dipindahkan: ' . $fileCount . '</p>';
} else {
    echo '<p class="text-amber-700 font-semibold">⚠️ Migrasi selesai dengan ' . count($errors) . ' error:</p>';
    echo '<ul class="text-amber-600 text-sm mt-2 space-y-1">';
    foreach ($errors as $err) {
        echo '<li class="ml-4">• ' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul>';
}
echo '</div>';

echo '<div class="mt-6 text-center">';
echo '<a href="dashboard.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl text-sm font-medium transition-colors"><i class="fas fa-home"></i> Ke Dashboard</a>';
echo '</div>';

echo '</div></div></body></html>';
