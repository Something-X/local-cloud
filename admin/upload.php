<?php
/**
 * Cloud Sekolah - Admin Upload Handler (AJAX)
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

$folderId = intval($_POST['folder_id'] ?? 0) ?: null;
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

// Create upload directory if not exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Generate unique filename
$newName = time() . '_' . rand(1000, 9999) . '.' . $ext;

// Move file
if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO files (name, original_name, path, type, size, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $newName,
        $originalName,
        $newName,
        $ext,
        $file['size'],
        $folderId,
        $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'File berhasil diupload',
        'file' => [
            'id' => $db->lastInsertId(),
            'name' => $newName,
            'original_name' => $originalName,
            'type' => $ext,
            'size' => $file['size']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file']);
}
