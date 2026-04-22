<?php
/**
 * Cloud Sekolah - Generate Share Link (AJAX)
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

$type = $_POST['type'] ?? 'file';
$idParam = $_POST['id'] ?? '';
$id = $idParam ? decodeId($idParam) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$db = getDB();

// Check if share already exists
if ($type === 'file') {
    $stmt = $db->prepare("SELECT token FROM shares WHERE file_id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("SELECT token FROM shares WHERE folder_id = ?");
    $stmt->execute([$id]);
}

$existing = $stmt->fetch();

if ($existing) {
    // Return existing token
    $token = $existing['token'];
} else {
    // Generate new token
    $token = generateToken();
    
    if ($type === 'file') {
        $stmt = $db->prepare("INSERT INTO shares (file_id, token) VALUES (?, ?)");
    } else {
        $stmt = $db->prepare("INSERT INTO shares (folder_id, token) VALUES (?, ?)");
    }
    $stmt->execute([$id, $token]);
}

$shareUrl = BASE_URL . '/share/view.php?token=' . $token;

echo json_encode([
    'success' => true,
    'message' => 'Share link berhasil dibuat',
    'token' => $token,
    'url' => $shareUrl
]);
