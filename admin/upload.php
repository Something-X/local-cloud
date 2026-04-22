<?php
/**
 * Cloud Sekolah - Admin Upload Handler (AJAX)
 * File disimpan di folder fisik sesuai lokasi, dengan nama asli
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload']);
    exit;
}

$folderIdParam = $_POST['folder_id'] ?? '';
$folderId = $folderIdParam ? decodeId($folderIdParam) : null;
$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi batas server)',
        UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension',
    ];
    echo json_encode(['success' => false, 'message' => $errorMessages[$file['error']] ?? 'Error upload']);
    exit;
}

// Validate size
if ($file['size'] > MAX_UPLOAD_SIZE) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file melebihi batas (max ' . formatFileSize(MAX_UPLOAD_SIZE) . ')']);
    exit;
}

// Get file info
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (empty($ext)) {
    $ext = 'bin';
}

// ==========================================
// VALIDASI KEAMANAN: Cek ekstensi file
// ==========================================
if (!in_array($ext, ALLOWED_EXTENSIONS)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Tipe file .' . $ext . ' tidak diizinkan. Ekstensi yang diizinkan: ' . implode(', ', array_map(fn($e) => '.' . $e, ALLOWED_EXTENSIONS))
    ]);
    exit;
}

// Determine target directory (physical folder path)
$targetDir = getFolderPhysicalPath($folderId);

// Create target directory if not exists
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Generate unique filename (keep original name, add counter if duplicate)
$finalName = getUniqueFileName($targetDir, $originalName);

// Build relative path from UPLOAD_DIR for database storage
$folderRelPath = getFolderRelativePath($folderId);
$relativePath = ($folderRelPath ? $folderRelPath . '/' : '') . $finalName;

// Move file to its physical folder
if (move_uploaded_file($file['tmp_name'], $targetDir . $finalName)) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO files (name, original_name, path, type, size, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $finalName,
        $originalName,
        $relativePath,
        $ext,
        $file['size'],
        $folderId,
        $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'File berhasil diupload',
        'file' => [
            'id' => encodeId($db->lastInsertId()),
            'name' => $finalName,
            'original_name' => $originalName,
            'type' => $ext,
            'size' => $file['size']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file']);
}
