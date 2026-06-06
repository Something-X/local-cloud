<?php
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
$idParam = $_POST['id'] ?? '';
$targetFolderIdParam = $_POST['target_folder_id'] ?? '';

$id = $idParam ? decodeId($idParam) : 0;
$targetFolderId = $targetFolderIdParam ? decodeId($targetFolderIdParam) : null;

if (!$id || $type !== 'file') {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

$db = getDB();

// Get the file
$stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
    exit;
}

// Ensure target folder exists if not root
if ($targetFolderId) {
    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->execute([$targetFolderId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Folder tujuan tidak ditemukan']);
        exit;
    }
}

// Cannot move to the same folder
$currentFolderId = $file['folder_id'];
if ($currentFolderId == $targetFolderId) {
    echo json_encode(['success' => true, 'message' => 'File sudah berada di folder tersebut']);
    exit;
}

// Determine physical paths
$oldPhysicalPath = rtrim(UPLOAD_DIR, '/') . '/' . ltrim($file['path'], '/');
$targetPhysicalFolder = getFolderPhysicalPath($targetFolderId);
$newFileName = getUniqueFileName($targetPhysicalFolder, $file['original_name']);
$newPhysicalPath = $targetPhysicalFolder . $newFileName;

// Calculate new relative path safely
// str_replace using the normalized UPLOAD_DIR
$normalizedUploadDir = str_replace('\\', '/', UPLOAD_DIR);
$normalizedNewPath = str_replace('\\', '/', $newPhysicalPath);
$newRelativePath = ltrim(str_replace($normalizedUploadDir, '', $normalizedNewPath), '/');

// Move physical file
$moved = false;
if (file_exists($oldPhysicalPath)) {
    if (!is_dir($targetPhysicalFolder)) {
        mkdir($targetPhysicalFolder, 0755, true);
    }
    $moved = rename($oldPhysicalPath, $newPhysicalPath);
} else {
    // If physical file doesn't exist, we might as well sync it by removing from DB
    syncFilesystem(true);
    echo json_encode(['success' => false, 'message' => 'File fisik tidak ditemukan']);
    exit;
}

if ($moved) {
    // Update DB
    $stmt = $db->prepare("UPDATE files SET folder_id = ?, path = ?, name = ? WHERE id = ?");
    $stmt->execute([$targetFolderId, $newRelativePath, $newFileName, $id]);
    echo json_encode(['success' => true, 'message' => 'File berhasil dipindahkan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file fisik']);
}
