<?php
/**
 * Cloud Sekolah - Create Folder Handler (AJAX)
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
$parentId = intval($_POST['parent_id'] ?? 0) ?: null;

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nama folder harus diisi']);
    exit;
}

// Sanitize folder name
$name = preg_replace('/[\/\\\\:*?"<>|]/', '', $name);

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
    echo json_encode(['success' => false, 'message' => 'Folder dengan nama ini sudah ada']);
    exit;
}

// Create folder
$stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, ?, ?)");
$stmt->execute([$name, $parentId, $_SESSION['user_id']]);

echo json_encode([
    'success' => true,
    'message' => 'Folder berhasil dibuat',
    'folder' => [
        'id' => $db->lastInsertId(),
        'name' => $name,
        'parent_id' => $parentId
    ]
]);
