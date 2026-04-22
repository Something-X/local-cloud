<?php
/**
 * Cloud Sekolah - Share View
 * Akses file/folder via share token
 */
require_once __DIR__ . '/../config/database.php';

$token = $_GET['token'] ?? '';

// ... (logika PHP tetap sama seperti sebelumnya) ...
if (empty($token)) {
    http_response_code(404);
    $error = 'Link tidak valid.';
} else {
    $db = getDB();
    $stmt = $db->prepare("SELECT s.*, f.original_name, f.path, f.type, f.size, f.id as file_id, fld.name as folder_name, fld.id as shared_folder_id FROM shares s LEFT JOIN files f ON s.file_id = f.id LEFT JOIN folders fld ON s.folder_id = fld.id WHERE s.token = ?");
    $stmt->execute([$token]);
    $share = $stmt->fetch();
    
    if (!$share) {
        http_response_code(404);
        $error = 'Link share tidak ditemukan atau sudah kedaluwarsa.';
    } elseif ($share['file_id'] && !$share['original_name']) {
        $error = 'File sudah dihapus.';
    } else {
        $error = null;
        
        // If sharing a folder, get its contents
        if ($share['shared_folder_id']) {
            $browseFolderId = intval($_GET['subfolder'] ?? 0) ?: $share['shared_folder_id'];
            
            $stmt = $db->prepare("SELECT * FROM folders WHERE parent_id = ? ORDER BY name ASC");
            $stmt->execute([$browseFolderId]);
            $folders = $stmt->fetchAll();
            
            $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY created_at DESC");
            $stmt->execute([$browseFolderId]);
            $files = $stmt->fetchAll();
            
            $breadcrumbs = getBreadcrumbs($browseFolderId);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($share) && $share['original_name'] ? htmlspecialchars($share['original_name']) . ' - ' : '' ?>Share - Cloud Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .share-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        }
        .glass-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
        }
        .orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.15; }
        .file-card { transition: all 0.2s; }
        .file-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        /* Hide scrollbar for clean look but allow scroll */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="share-bg min-h-screen flex items-center justify-center p-3 sm:p-4 relative overflow-hidden">
    <div class="orb w-96 h-96 bg-blue-500 -top-48 -left-48"></div>
    <div class="orb w-80 h-80 bg-purple-500 -bottom-40 -right-40"></div>

    <?php if ($error): ?>
    <div class="glass-card rounded-2xl shadow-2xl p-6 sm:p-8 w-full max-w-md text-center z-10">
        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl sm:text-3xl"></i>
        </div>
        <h2 class="text-lg sm:text-xl font-bold text-slate-800 mb-2">Link Tidak Valid</h2>
        <p class="text-slate-500 text-xs sm:text-sm mb-6"><?= htmlspecialchars($error) ?></p>
        <a href="../guest/index.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-cyan-500 text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl text-sm font-medium hover:shadow-lg transition-all">
            <i class="fas fa-home"></i> Ke Halaman Utama
        </a>
    </div>

    <?php elseif ($share['file_id']): ?>
    <div class="glass-card rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden z-10">
        <div class="bg-gradient-to-r from-blue-600 to-cyan-500 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between">
            <div class="flex items-center gap-2 sm:gap-3 text-white">
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-cloud text-sm sm:text-base"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-sm sm:text-base truncate">Cloud Sekolah</p>
                    <p class="text-[10px] sm:text-xs text-blue-100 truncate">Shared File</p>
                </div>
            </div>
            <a href="../download.php?id=<?= $share['file_id'] ?>" class="flex items-center gap-2 bg-white text-blue-600 px-3 sm:px-5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold hover:shadow-lg transition-all flex-shrink-0">
                <i class="fas fa-download"></i> <span class="hidden sm:inline">Download</span>
            </a>
        </div>

        <div class="p-4 sm:p-6">
            <div class="flex items-center gap-3 sm:gap-4 mb-4 sm:mb-6">
                <?php 
                    $category = getFileCategory($share['type']);
                    $iconMap = [
                        'image' => ['fa-image', 'text-emerald-500', 'bg-emerald-50'],
                        'video' => ['fa-play-circle', 'text-purple-500', 'bg-purple-50'],
                        'pdf' => ['fa-file-pdf', 'text-red-500', 'bg-red-50'],
                        'document' => ['fa-file-alt', 'text-blue-500', 'bg-blue-50'],
                        'archive' => ['fa-file-archive', 'text-amber-500', 'bg-amber-50'],
                        'text' => ['fa-file-alt', 'text-slate-500', 'bg-slate-50'],
                        'other' => ['fa-file', 'text-slate-500', 'bg-slate-50']
                    ];
                    $icon = $iconMap[$category] ?? $iconMap['other'];
                ?>
                <div class="w-12 h-12 sm:w-14 sm:h-14 <?= $icon[2] ?> rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas <?= $icon[0] ?> <?= $icon[1] ?> text-xl sm:text-2xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 truncate"><?= htmlspecialchars($share['original_name']) ?></h2>
                    <p class="text-xs sm:text-sm text-slate-400 block truncate"><?= formatFileSize($share['size']) ?> · <?= strtoupper($share['type']) ?> · <?= date('d M Y', strtotime($share['created_at'])) ?></p>
                </div>
            </div>

            <div class="bg-slate-50 rounded-xl p-2 sm:p-4 flex items-center justify-center min-h-[150px] sm:min-h-[200px]">
                <?php if ($category === 'image'): ?>
                <img src="../uploads/<?= urlEncodePath($share['path']) ?>" alt="" class="max-w-full max-h-[60vh] rounded-lg shadow-sm">
                <?php elseif ($category === 'video'): ?>
                <video controls class="max-w-full max-h-[60vh] rounded-lg shadow-sm">
                    <source src="../uploads/<?= urlEncodePath($share['path']) ?>" type="video/<?= $share['type'] ?>">
                    Browser Anda tidak mendukung video.
                </video>
                <?php elseif ($category === 'pdf'): ?>
                <iframe src="../uploads/<?= urlEncodePath($share['path']) ?>" class="w-full h-[50vh] sm:h-[60vh] rounded-lg border border-slate-200"></iframe>
                <?php else: ?>
                <div class="text-center py-6 sm:py-8">
                    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3">
                        <i class="fas fa-file text-slate-400 text-xl sm:text-2xl"></i>
                    </div>
                    <p class="text-slate-500 text-xs sm:text-sm">Preview tidak tersedia</p>
                    <p class="text-slate-400 text-[10px] sm:text-xs mt-1">Silakan download file untuk melihat isinya</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php elseif ($share['shared_folder_id']): ?>
    <div class="glass-card rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col z-10">
        <div class="bg-gradient-to-r from-blue-600 to-cyan-500 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2 sm:gap-3 text-white">
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-cloud text-sm sm:text-base"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-sm sm:text-base truncate">Cloud Sekolah</p>
                    <p class="text-[10px] sm:text-xs text-blue-100 truncate">Shared Folder: <?= htmlspecialchars($share['folder_name']) ?></p>
                </div>
            </div>
        </div>

        <div class="px-4 sm:px-6 py-2.5 sm:py-3 border-b border-slate-200 bg-white">
            <nav class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm overflow-x-auto whitespace-nowrap scrollbar-hide">
                <a href="view.php?token=<?= $token ?>" class="text-slate-500 hover:text-blue-600 flex items-center gap-1 flex-shrink-0">
                    <i class="fas fa-folder"></i> <span class="truncate max-w-[80px] sm:max-w-none"><?= htmlspecialchars($share['folder_name']) ?></span>
                </a>
                <?php 
                $showBreadcrumbs = false;
                foreach ($breadcrumbs as $bc):
                    if ($bc['id'] == $share['shared_folder_id']) { $showBreadcrumbs = true; continue; }
                    if (!$showBreadcrumbs) continue;
                ?>
                <i class="fas fa-chevron-right text-slate-300 text-[10px] sm:text-xs flex-shrink-0"></i>
                <a href="view.php?token=<?= $token ?>&subfolder=<?= $bc['id'] ?>" class="text-slate-500 hover:text-blue-600 truncate max-w-[80px] sm:max-w-none">
                    <?= htmlspecialchars($bc['name']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6">
            <?php if (empty($folders) && empty($files)): ?>
            <div class="text-center py-10 sm:py-12">
                <div class="w-12 h-12 sm:w-16 sm:h-16 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3">
                    <i class="fas fa-folder-open text-slate-400 text-xl sm:text-2xl"></i>
                </div>
                <p class="text-slate-500 text-sm">Folder kosong</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
                <?php foreach ($folders as $folder): ?>
                <a href="view.php?token=<?= $token ?>&subfolder=<?= $folder['id'] ?>" class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 block">
                    <div class="text-center">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-50 rounded-xl flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-folder text-blue-500 text-lg sm:text-xl"></i>
                        </div>
                        <p class="text-xs sm:text-sm font-medium text-slate-700 truncate"><?= htmlspecialchars($folder['name']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php foreach ($files as $file):
                    $category = getFileCategory($file['type']);
                    $iconMap = [
                        'image' => ['fa-image', 'text-emerald-500', 'bg-emerald-50'],
                        'video' => ['fa-play-circle', 'text-purple-500', 'bg-purple-50'],
                        'pdf' => ['fa-file-pdf', 'text-red-500', 'bg-red-50'],
                        'document' => ['fa-file-alt', 'text-blue-500', 'bg-blue-50'],
                        'archive' => ['fa-file-archive', 'text-amber-500', 'bg-amber-50'],
                        'text' => ['fa-file-alt', 'text-slate-500', 'bg-slate-50'],
                        'other' => ['fa-file', 'text-slate-500', 'bg-slate-50']
                    ];
                    $icon = $iconMap[$category] ?? $iconMap['other'];
                ?>
                <div class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 group relative">
                    <div class="text-center">
                        <?php if ($category === 'image'): ?>
                        <div class="w-full h-16 sm:h-20 rounded-lg mb-2 overflow-hidden bg-slate-100">
                            <img src="../uploads/<?= urlEncodePath($file['path']) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <?php else: ?>
                        <div class="w-10 h-10 sm:w-12 sm:h-12 <?= $icon[2] ?> rounded-xl flex items-center justify-center mx-auto mb-2">
                            <i class="fas <?= $icon[0] ?> <?= $icon[1] ?> text-lg sm:text-xl"></i>
                        </div>
                        <?php endif; ?>
                        <p class="text-xs sm:text-sm font-medium text-slate-700 truncate"><?= htmlspecialchars($file['original_name']) ?></p>
                        <p class="text-[10px] sm:text-xs text-slate-400 mt-1"><?= formatFileSize($file['size']) ?></p>
                    </div>
                    <div class="absolute top-2 right-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="../download.php?id=<?= $file['id'] ?>" class="w-7 h-7 sm:w-8 sm:h-8 bg-blue-500/80 hover:bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg backdrop-blur-sm" title="Download">
                            <i class="fas fa-download text-[10px] sm:text-xs"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="fixed bottom-4 left-1/2 -translate-x-1/2 z-10">
        <a href="../guest/index.php" class="text-slate-400 hover:text-white text-xs sm:text-sm transition-colors flex items-center gap-2 px-4 py-2 bg-slate-900/50 rounded-full backdrop-blur-md">
            <i class="fas fa-arrow-left"></i> Ke Halaman Utama
        </a>
    </div>
</body>
</html>