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

// Hardcoded Application Key for consistent encryption across team environments
define('APP_KEY', 'CloudSekolah_TeamDev_SecretKey2026!');

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
 * URL-encode a file path (encode each segment, keep slashes)
 * e.g. "Mata Pelajaran/Soal UTS.pdf" → "Mata%20Pelajaran/Soal%20UTS.pdf"
 */
function urlEncodePath($path) {
    $segments = explode('/', $path);
    return implode('/', array_map('rawurlencode', $segments));
}

/**
 * Encrypt an ID
 * Digunakan untuk menyembunyikan ID urut dari user di Frontend.
 */
function encodeId($id) {
    if (!$id) return null;
    $iv = substr(hash('sha256', APP_KEY), 0, 16);
    $encrypted = openssl_encrypt((string)$id, 'AES-256-CBC', APP_KEY, 0, $iv);
    return str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);
}

/**
 * Decrypt an ID
 * Menerjemahkan kembali ID yang diterima dari parameter (GET/POST) ke Integer di backend.
 */
function decodeId($hash) {
    if (!$hash) return null;
    $hashWithEquals = str_replace(['-', '_'], ['+', '/'], $hash);
    $pad = strlen($hashWithEquals) % 4;
    if ($pad) {
        $hashWithEquals .= str_repeat('=', 4 - $pad);
    }
    
    $iv = substr(hash('sha256', APP_KEY), 0, 16);
    $decrypted = openssl_decrypt($hashWithEquals, 'AES-256-CBC', APP_KEY, 0, $iv);
    return is_numeric($decrypted) ? (int)$decrypted : null;
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
 * Delete folder recursively (database + physical)
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
    
    // Delete the physical folder
    $physicalPath = getFolderPhysicalPath($folderId);
    if ($physicalPath && is_dir($physicalPath)) {
        @rmdir($physicalPath); // rmdir hanya menghapus folder kosong, aman
    }
    
    // Delete the folder record from database
    $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
}

/**
 * Get the physical filesystem path for a folder
 * Builds the full path by traversing the parent chain
 * Returns: absolute path like "C:/laragon/www/local-cloud/uploads/Mata Pelajaran/Matematika/"
 */
function getFolderPhysicalPath($folderId) {
    if (!$folderId) return UPLOAD_DIR;
    
    $db = getDB();
    $parts = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
        $stmt->execute([$currentId]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            array_unshift($parts, $folder['name']);
            $currentId = $folder['parent_id'];
        } else {
            break;
        }
    }
    
    if (empty($parts)) return UPLOAD_DIR;
    
    return UPLOAD_DIR . implode('/', $parts) . '/';
}

/**
 * Get the relative path of a folder (from UPLOAD_DIR)
 * e.g. "Mata Pelajaran/Matematika"
 */
function getFolderRelativePath($folderId) {
    if (!$folderId) return '';
    
    $db = getDB();
    $parts = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ?");
        $stmt->execute([$currentId]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            array_unshift($parts, $folder['name']);
            $currentId = $folder['parent_id'];
        } else {
            break;
        }
    }
    
    return implode('/', $parts);
}

/**
 * Generate a unique filename in a directory
 * If "Soal UTS.pdf" exists, returns "Soal UTS (2).pdf", etc.
 */
function getUniqueFileName($directory, $originalName) {
    $filePath = $directory . $originalName;
    
    if (!file_exists($filePath)) {
        return $originalName;
    }
    
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
    $counter = 2;
    
    do {
        $newName = $nameWithoutExt . ' (' . $counter . ')' . ($ext ? '.' . $ext : '');
        $filePath = $directory . $newName;
        $counter++;
    } while (file_exists($filePath));
    
    return $newName;
}

/**
 * Sync filesystem with database (two-way)
 * - Scans uploads/ directory for new folders/files → adds to database
 * - Removes database records for folders/files that no longer exist on disk
 * Uses a timestamp cache to avoid scanning on every page load
 */
function syncFilesystem($force = false) {
    // Cache mechanism: only sync if last sync was > 10 seconds ago
    $cacheFile = UPLOAD_DIR . '.sync_cache';
    if (!$force && file_exists($cacheFile)) {
        $lastSync = (int)file_get_contents($cacheFile);
        if (time() - $lastSync < 10) {
            return; // Skip sync, data masih fresh
        }
    }
    
    // Ensure uploads directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $db = getDB();
    
    // ============================
    // PHASE 1: Filesystem → Database
    // Scan physical folders/files and add missing records
    // ============================
    syncDirectoryToDb($db, UPLOAD_DIR, null);
    
    // ============================
    // PHASE 2: Database → Filesystem
    // Remove DB records for items that no longer exist physically
    // ============================
    
    // Check folders
    $stmt = $db->query("SELECT id FROM folders ORDER BY id ASC");
    $allFolders = $stmt->fetchAll();
    foreach ($allFolders as $folder) {
        $physPath = getFolderPhysicalPath($folder['id']);
        if (!is_dir($physPath)) {
            // Folder doesn't exist on disk, remove from DB (with children)
            // But only remove the record, not try to delete physical files again
            $stmtDel = $db->prepare("DELETE FROM shares WHERE folder_id = ?");
            $stmtDel->execute([$folder['id']]);
            $stmtDel = $db->prepare("DELETE FROM shares WHERE file_id IN (SELECT id FROM files WHERE folder_id = ?)");
            $stmtDel->execute([$folder['id']]);
            $stmtDel = $db->prepare("DELETE FROM files WHERE folder_id = ?");
            $stmtDel->execute([$folder['id']]);
            $stmtDel = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmtDel->execute([$folder['id']]);
        }
    }
    
    // Check files
    $stmt = $db->query("SELECT id, path FROM files");
    $allFiles = $stmt->fetchAll();
    foreach ($allFiles as $file) {
        $physPath = UPLOAD_DIR . $file['path'];
        if (!file_exists($physPath)) {
            $stmtDel = $db->prepare("DELETE FROM shares WHERE file_id = ?");
            $stmtDel->execute([$file['id']]);
            $stmtDel = $db->prepare("DELETE FROM files WHERE id = ?");
            $stmtDel->execute([$file['id']]);
        }
    }
    
    // Update cache timestamp
    file_put_contents($cacheFile, time());
}

/**
 * Recursively scan a directory and sync its contents to the database
 */
function syncDirectoryToDb($db, $directory, $parentFolderId) {
    $items = @scandir($directory);
    if ($items === false) return;
    
    foreach ($items as $item) {
        // Skip hidden files/folders and special entries
        if ($item === '.' || $item === '..' || $item[0] === '.') continue;
        
        $fullPath = $directory . $item;
        
        if (is_dir($fullPath)) {
            // Check if this folder exists in DB
            $stmt = $db->prepare(
                "SELECT id FROM folders WHERE name = ? AND parent_id " . ($parentFolderId ? "= ?" : "IS NULL")
            );
            $params = [$item];
            if ($parentFolderId) $params[] = $parentFolderId;
            $stmt->execute($params);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $folderId = $existing['id'];
            } else {
                // Create folder record in DB
                $stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (?, ?, NULL)");
                $stmt->execute([$item, $parentFolderId]);
                $folderId = $db->lastInsertId();
            }
            
            // Recurse into subdirectory
            syncDirectoryToDb($db, $fullPath . '/', $folderId);
            
        } else if (is_file($fullPath)) {
            // Build relative path from UPLOAD_DIR
            $relativePath = str_replace(
                str_replace('\\', '/', UPLOAD_DIR),
                '',
                str_replace('\\', '/', $fullPath)
            );
            
            // Check if this file exists in DB by its relative path
            $stmt = $db->prepare("SELECT id FROM files WHERE path = ?");
            $stmt->execute([$relativePath]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                // Register file in DB
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (empty($ext)) $ext = 'bin';
                
                $stmt = $db->prepare(
                    "INSERT INTO files (name, original_name, path, type, size, folder_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, NULL)"
                );
                $stmt->execute([
                    $item,
                    $item,
                    $relativePath,
                    $ext,
                    filesize($fullPath),
                    $parentFolderId
                ]);
            }
        }
    }
}
