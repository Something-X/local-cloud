<?php
/**
 * Cloud Sekolah - Manual Sync Handler (AJAX)
 * Force sinkronisasi filesystem <-> database
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    syncFilesystem(true); // force = true, abaikan cache
    echo json_encode(['success' => true, 'message' => 'Sinkronisasi berhasil']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal sinkronisasi: ' . $e->getMessage()]);
}
