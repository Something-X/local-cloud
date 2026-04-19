<?php
/**
 * Database Configuration
 * Cloud Sekolah - Sistem File Cloud Lokal
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'cloud_sekolah');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Base URL - sesuaikan dengan IP server sekolah
define('BASE_URL', '/cloud-local');

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Max upload size (2GB)
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024 * 1024);

// Allowed file extensions
define('ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'mp4', 'webm', 'mov',
    'pdf',
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'zip', 'rar', '7z',
    'txt', 'csv'
]);

// File type categories
define('FILE_CATEGORIES', [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'tiff', 'tif'],
    'video' => ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', '3gp'],
    'audio' => ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a'],
    'pdf'   => ['pdf'],
    'document' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'],
    'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
    'text'  => ['txt', 'csv', 'log', 'md', 'json', 'xml', 'yml', 'yaml'],
    'code'  => ['html', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'sh', 'bat'],
    'executable' => ['exe', 'msi', 'apk', 'dmg', 'deb', 'rpm', 'appimage', 'jar'],
    'font'  => ['ttf', 'otf', 'woff', 'woff2', 'eot'],
    'design' => ['psd', 'ai', 'sketch', 'fig', 'xd', 'cdr']
]);

/**
 * Get PDO Database Connection
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}

/**
 * Get file category from extension
 */
function getFileCategory($extension) {
    $ext = strtolower($extension);
    foreach (FILE_CATEGORIES as $category => $extensions) {
        if (in_array($ext, $extensions)) {
            return $category;
        }
    }
    return 'other';
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Check if user is logged in as admin
 */
function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin login
 */
function requireAdmin() {
    session_start();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Generate share token
 */
function generateToken() {
    return bin2hex(random_bytes(16));
}

/**
 * Get breadcrumb path for a folder
 */
function getBreadcrumbs($folderId) {
    $db = getDB();
    $breadcrumbs = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
        $stmt->execute([$currentId]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            array_unshift($breadcrumbs, $folder);
            $currentId = $folder['parent_id'];
        } else {
            break;
        }
    }
    
    return $breadcrumbs;
}

/**
 * Delete folder recursively
 */
function deleteFolderRecursive($folderId) {
    $db = getDB();
    
    // Delete files in this folder
    $stmt = $db->prepare("SELECT path FROM files WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    $files = $stmt->fetchAll();
    
    foreach ($files as $file) {
        $filePath = UPLOAD_DIR . $file['path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Delete file records
    $stmt = $db->prepare("DELETE FROM shares WHERE file_id IN (SELECT id FROM files WHERE folder_id = ?)");
    $stmt->execute([$folderId]);
    $stmt = $db->prepare("DELETE FROM files WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    
    // Get child folders
    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->execute([$folderId]);
    $children = $stmt->fetchAll();
    
    foreach ($children as $child) {
        deleteFolderRecursive($child['id']);
    }
    
    // Delete share links for this folder
    $stmt = $db->prepare("DELETE FROM shares WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    
    // Delete the folder itself
    $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
}
