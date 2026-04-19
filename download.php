<?php
/**
 * Cloud Sekolah - File Download Handler
 * Bulletproof version - handles all edge cases
 */

// Turn off all error output
ini_set('display_errors', 0);
error_reporting(0);

// Clean ALL output buffers
while (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/config/database.php';

$fileId = intval($_GET['id'] ?? 0);

if (!$fileId) {
    http_response_code(400);
    die('File tidak ditemukan.');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

$filePath = UPLOAD_DIR . $file['path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File tidak ditemukan di server.');
}

// Clean ALL output buffers again (in case require_once added one)
while (ob_get_level()) {
    ob_end_clean();
}

$originalName = $file['original_name'];
$fileSize = filesize($filePath);

// Sanitize filename - remove any problematic characters
$safeFilename = str_replace(['"', "\r", "\n"], ['_', '', ''], $originalName);
$encodedFilename = rawurlencode($originalName);

// Send headers
header('HTTP/1.1 200 OK');
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . $encodedFilename);
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $fileSize);
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Accept-Ranges: bytes');

// Flush headers
flush();

// Read and output file
readfile($filePath);
exit;
