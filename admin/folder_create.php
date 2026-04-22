<?php
/**
 * Cloud Sekolah - Create Folder Handler (AJAX)
 * Membuat folder di database DAN folder fisik di server
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

$name = trim($_POST['name'] ?? '');
$parentIdParam = $_POST['parent_id'] ?? '';
$parentId = $parentIdParam ? decodeId($parentIdParam) : null;

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nama folder harus diisi']);
    exit;
}

// Sanitize folder name (hapus karakter berbahaya untuk filesystem)
$name = preg_replace('/[\/\\\\:*?"<>|]/', '', $name);
$name = trim($name);

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nama folder tidak valid']);
    exit;
}

$db = getDB();

// Check if folder with same name exists in same parent
$stmt = $db->prepare("SELECT id FROM folders WHERE name = ? AND parent_id " . ($parentId ? "= ?" : "IS NULL"));
$params = [$name];
if ($parentId) $params[] = $parentId;
$stmt->execute($params);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'folder dengan nama yang sama sudah ada']);
    exit;
}

// Determine the physical path for the new folder
$parentPath = getFolderPhysicalPath($parentId);
$newFolderPath = $parentPath . $name;

// Create physical folder on the server
if (!is_dir($newFolderPath)) {
    if (!mkdir($newFolderPath, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder di server. Periksa izin akses.']);
        exit;
    }
} else {
    // Jika folder fisik sudah ada tapi belum ada di database, kita izinkan masuk ke DB, tapi ada baiknya memberi notif
    // Di sini kita biarkan masuk agar database sinkron
}

// Create folder record in database
$stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, ?, ?)");
$stmt->execute([$name, $parentId, $_SESSION['user_id']]);

echo json_encode([
    'success' => true,
    'message' => 'Folder berhasil dibuat',
    'folder' => [
        'id' => encodeId($db->lastInsertId()),
        'name' => $name,
        'parent_id' => $parentId
    ]
]);
