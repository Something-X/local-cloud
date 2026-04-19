<?php
/**
 * Cloud Sekolah - Delete File/Folder Handler (AJAX)
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

$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id || !in_array($type, ['file', 'folder'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

$db = getDB();

if ($type === 'file') {
    // Get file info
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    
    if (!$file) {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
        exit;
    }
    
    // Delete physical file
    $filePath = UPLOAD_DIR . $file['path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete share links
    $stmt = $db->prepare("DELETE FROM shares WHERE file_id = ?");
    $stmt->execute([$id]);
    
    // Delete database record
    $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'File berhasil dihapus']);
    
} elseif ($type === 'folder') {
    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->execute([$id]);
    $folder = $stmt->fetch();
    
    if (!$folder) {
        echo json_encode(['success' => false, 'message' => 'Folder tidak ditemukan']);
        exit;
    }
    
    // Recursive delete
    deleteFolderRecursive($id);
    
    echo json_encode(['success' => true, 'message' => 'Folder berhasil dihapus']);
}
