<?php
/**
 * Cloud Sekolah - Guest File Browser
 * Bisa akses tanpa login
 */
session_start();
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$currentFolderId = intval($_GET['folder'] ?? 0) ?: null;
$breadcrumbs = $currentFolderId ? getBreadcrumbs($currentFolderId) : [];

// Get folders in current directory
$stmt = $db->prepare("SELECT * FROM folders WHERE parent_id " . ($currentFolderId ? "= ?" : "IS NULL") . " ORDER BY name ASC");
$stmt->execute($currentFolderId ? [$currentFolderId] : []);
$folders = $stmt->fetchAll();

// Get files in current directory
$stmt = $db->prepare("SELECT f.*, u.username as uploader FROM files f LEFT JOIN users u ON f.uploaded_by = u.id WHERE f.folder_id " . ($currentFolderId ? "= ?" : "IS NULL") . " ORDER BY f.created_at DESC");
$stmt->execute($currentFolderId ? [$currentFolderId] : []);
$files = $stmt->fetchAll();

$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Sekolah - File Manager</title>
    <meta name="description" content="Sistem File Cloud Lokal Sekolah - Akses dan download file sekolah dengan mudah">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .file-card { transition: all 0.2s; }
        .file-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .modal-backdrop { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }

        /* Mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex h-screen overflow-hidden">
        <aside class="sidebar w-64 md:w-64 bg-slate-900 text-white flex flex-col flex-shrink-0" id="sidebar">
            <div class="p-5 border-b border-slate-700/50">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/25 flex-shrink-0">
                            <i class="fas fa-cloud text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="font-bold text-lg leading-tight">Cloud Sekolah</h1>
                            <p class="text-xs text-slate-400">File Sharing</p>
                        </div>
                    </div>
                    <button onclick="toggleSidebar()" class="md:hidden w-8 h-8 hover:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <nav class="flex-1 p-3 space-y-1">
                <a href="index.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>Semua File</span>
                </a>
            </nav>

            <div class="p-4 border-t border-slate-700/50">
                <div class="bg-slate-800/50 rounded-xl p-4 text-center">
                    <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-user text-slate-400"></i>
                    </div>
                    <p class="text-sm text-slate-300 font-medium">Mode Guest</p>
                    <p class="text-xs text-slate-500 mt-1">Lihat & download file</p>
                </div>
                <?php if ($isLoggedIn): ?>
                <a href="../admin/dashboard.php" class="block mt-3 text-center px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-xl transition-colors">
                    <i class="fas fa-tachometer-alt mr-2"></i>Admin Panel
                </a>
                <?php else: ?>
                <a href="../auth/login.php" class="block mt-3 text-center px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium rounded-xl transition-colors border border-slate-700">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login Admin
                </a>
                <?php endif; ?>
            </div>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center text-slate-600 flex-shrink-0 transition-colors">
                        <i class="fas fa-bars"></i>
                    </button>
                    <nav class="flex items-center gap-1 sm:gap-2 text-sm min-w-0 overflow-x-auto whitespace-nowrap scrollbar-hide">
                        <a href="index.php" class="text-slate-500 hover:text-blue-600 transition-colors flex items-center gap-1 flex-shrink-0">
                            <i class="fas fa-home"></i>
                            <span class="hidden sm:inline">Home</span>
                        </a>
                        <?php foreach ($breadcrumbs as $bc): ?>
                        <i class="fas fa-chevron-right text-slate-300 text-xs flex-shrink-0"></i>
                        <a href="index.php?folder=<?= $bc['id'] ?>" class="text-slate-500 hover:text-blue-600 transition-colors truncate max-w-[100px] sm:max-w-none">
                            <?= htmlspecialchars($bc['name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                    <div class="flex bg-slate-100 rounded-lg p-1">
                        <button onclick="setView('grid')" id="gridBtn" class="px-2.5 sm:px-3 py-1.5 rounded-md text-sm font-medium transition-all bg-white shadow text-blue-600">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button onclick="setView('list')" id="listBtn" class="px-2.5 sm:px-3 py-1.5 rounded-md text-sm font-medium transition-all text-slate-500">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <?php if (empty($folders) && empty($files)): ?>
                <div class="text-center py-16 sm:py-20">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-slate-400 text-2xl sm:text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-600 mb-1">Folder kosong</h3>
                    <p class="text-slate-400 text-sm">Belum ada file atau folder di sini</p>
                </div>
                <?php else: ?>

                <div id="gridView">
                    <?php if (!empty($folders)): ?>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Folder</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 mb-6">
                        <?php foreach ($folders as $folder): ?>
                        <a href="index.php?folder=<?= $folder['id'] ?>" class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 block group">
                            <div class="text-center">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-50 rounded-xl flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:bg-blue-100 transition-colors">
                                    <i class="fas fa-folder text-blue-500 text-xl sm:text-2xl"></i>
                                </div>
                                <p class="text-xs sm:text-sm font-medium text-slate-700 truncate"><?= htmlspecialchars($folder['name']) ?></p>
                                <p class="text-xs text-slate-400 mt-1 hidden sm:block"><?= date('d M Y', strtotime($folder['created_at'])) ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($files)): ?>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">File</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
                        <?php foreach ($files as $file):
                            $category = getFileCategory($file['type']);
                            $iconMap = [
                                'image' => ['fa-image', 'text-emerald-500', 'bg-emerald-50'],
                                'video' => ['fa-play-circle', 'text-purple-500', 'bg-purple-50'],
                                'audio' => ['fa-music', 'text-pink-500', 'bg-pink-50'],
                                'pdf' => ['fa-file-pdf', 'text-red-500', 'bg-red-50'],
                                'document' => ['fa-file-alt', 'text-blue-500', 'bg-blue-50'],
                                'archive' => ['fa-file-archive', 'text-amber-500', 'bg-amber-50'],
                                'text' => ['fa-file-alt', 'text-slate-500', 'bg-slate-50'],
                                'code' => ['fa-file-code', 'text-cyan-500', 'bg-cyan-50'],
                                'executable' => ['fa-cog', 'text-orange-500', 'bg-orange-50'],
                                'font' => ['fa-font', 'text-indigo-500', 'bg-indigo-50'],
                                'design' => ['fa-paint-brush', 'text-fuchsia-500', 'bg-fuchsia-50'],
                                'other' => ['fa-file', 'text-slate-500', 'bg-slate-50']
                            ];
                            $icon = $iconMap[$category] ?? $iconMap['other'];
                        ?>
                        <div class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 cursor-pointer group relative" onclick="previewFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>', '<?= $file['type'] ?>', '<?= $file['path'] ?>')">
                            <div class="text-center">
                                <?php if ($category === 'image'): ?>
                                <div class="w-full h-20 sm:h-24 rounded-lg mb-2 sm:mb-3 overflow-hidden bg-slate-100">
                                    <img src="../uploads/<?= $file['path'] ?>" alt="" class="w-full h-full object-cover" loading="lazy">
                                </div>
                                <?php else: ?>
                                <div class="w-12 h-12 sm:w-14 sm:h-14 <?= $icon[2] ?> rounded-xl flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                    <i class="fas <?= $icon[0] ?> <?= $icon[1] ?> text-xl sm:text-2xl"></i>
                                </div>
                                <?php endif; ?>
                                <p class="text-xs sm:text-sm font-medium text-slate-700 truncate" title="<?= htmlspecialchars($file['original_name']) ?>"><?= htmlspecialchars($file['original_name']) ?></p>
                                <p class="text-xs text-slate-400 mt-1"><?= formatFileSize($file['size']) ?></p>
                            </div>
                            <div class="absolute top-2 right-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="../download.php?id=<?= $file['id'] ?>" onclick="event.stopPropagation();" class="w-8 h-8 bg-blue-500/80 hover:bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg backdrop-blur-sm" title="Download">
                                    <i class="fas fa-download text-xs"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="listView" class="hidden">
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden overflow-x-auto">
                        <table class="w-full min-w-[500px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider hidden sm:table-cell">Ukuran</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:table-cell">Tanggal</th>
                                    <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($folders as $folder): ?>
                                <tr class="border-b border-slate-100 hover:bg-blue-50/50 cursor-pointer transition-colors" onclick="window.location='index.php?folder=<?= $folder['id'] ?>'">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-folder text-blue-500"></i>
                                            </div>
                                            <span class="text-sm font-medium text-slate-700 truncate"><?= htmlspecialchars($folder['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-400 hidden sm:table-cell">—</td>
                                    <td class="px-4 py-3 text-sm text-slate-400 hidden md:table-cell"><?= date('d M Y H:i', strtotime($folder['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-xs text-slate-400"><i class="fas fa-folder-open mr-1"></i>Buka</span>
                                    </td>
                                </tr>
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
                                <tr class="border-b border-slate-100 hover:bg-blue-50/50 cursor-pointer transition-colors" onclick="previewFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>', '<?= $file['type'] ?>', '<?= $file['path'] ?>')">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 <?= $icon[2] ?> rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas <?= $icon[0] ?> <?= $icon[1] ?>"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-sm font-medium text-slate-700 block truncate"><?= htmlspecialchars($file['original_name']) ?></span>
                                                <span class="text-xs text-slate-400 sm:hidden"><?= formatFileSize($file['size']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-400 hidden sm:table-cell"><?= formatFileSize($file['size']) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-400 hidden md:table-cell"><?= date('d M Y H:i', strtotime($file['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="../download.php?id=<?= $file['id'] ?>" onclick="event.stopPropagation();" class="inline-flex items-center gap-1 text-blue-500 hover:text-blue-600 text-sm font-medium transition-colors p-2 hover:bg-blue-50 rounded-lg">
                                            <i class="fas fa-download text-xs"></i> <span class="hidden sm:inline">Download</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="previewModal" class="fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 flex-shrink-0">
                <h3 class="text-base sm:text-lg font-bold text-slate-800 truncate mr-3" id="previewTitle">Preview</h3>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="#" id="previewDownload" download="" class="px-3 sm:px-4 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-500 text-white rounded-xl transition-colors flex items-center gap-2">
                        <i class="fas fa-download"></i> <span class="hidden sm:inline">Download</span>
                    </a>
                    <button onclick="closePreviewModal()" class="w-8 h-8 hover:bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-4 sm:p-6 flex items-center justify-center bg-slate-50" id="previewContent">
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        let currentView = localStorage.getItem('viewMode') || 'grid';

        document.addEventListener('DOMContentLoaded', () => setView(currentView));

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }

        function setView(mode) {
            currentView = mode;
            localStorage.setItem('viewMode', mode);
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridBtn');
            const listBtn = document.getElementById('listBtn');
            
            if (mode === 'grid') {
                gridView?.classList.remove('hidden');
                listView?.classList.add('hidden');
                gridBtn.classList.add('bg-white', 'shadow', 'text-blue-600');
                gridBtn.classList.remove('text-slate-500');
                listBtn.classList.remove('bg-white', 'shadow', 'text-blue-600');
                listBtn.classList.add('text-slate-500');
            } else {
                gridView?.classList.add('hidden');
                listView?.classList.remove('hidden');
                listBtn.classList.add('bg-white', 'shadow', 'text-blue-600');
                listBtn.classList.remove('text-slate-500');
                gridBtn.classList.remove('bg-white', 'shadow', 'text-blue-600');
                gridBtn.classList.add('text-slate-500');
            }
        }

        function previewFile(id, name, type, path) {
            const modal = document.getElementById('previewModal');
            const title = document.getElementById('previewTitle');
            const content = document.getElementById('previewContent');
            const downloadBtn = document.getElementById('previewDownload');

            title.textContent = name;
            downloadBtn.href = BASE_URL + '/download.php?id=' + id;
            downloadBtn.setAttribute('download', name);

            const imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg','tiff','tif'];
            const videoExts = ['mp4','webm','mov','avi','mkv','flv','wmv','3gp'];
            const audioExts = ['mp3','wav','ogg','flac','aac','wma','m4a'];

            if (imageExts.includes(type)) {
                content.innerHTML = '<img src="../uploads/' + path + '" class="max-w-full max-h-[70vh] rounded-lg shadow-lg" alt="' + name + '">';
            } else if (videoExts.includes(type)) {
                content.innerHTML = '<video controls class="max-w-full max-h-[70vh] rounded-lg shadow-lg"><source src="../uploads/' + path + '"></video>';
            } else if (audioExts.includes(type)) {
                content.innerHTML = '<div class="text-center py-8 w-full"><div class="w-20 h-20 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-music text-pink-500 text-3xl"></i></div><p class="text-slate-600 font-medium mb-4">' + name + '</p><audio controls class="w-full max-w-md mx-auto"><source src="../uploads/' + path + '"></audio></div>';
            } else if (type === 'pdf') {
                content.innerHTML = '<iframe src="../uploads/' + path + '" class="w-full h-[70vh] rounded-lg border border-slate-200"></iframe>';
            } else {
                content.innerHTML = '<div class="text-center py-12"><div class="w-20 h-20 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-file text-slate-400 text-3xl"></i></div><p class="text-slate-600 font-medium">' + name + '</p><p class="text-slate-400 text-sm mt-1">Preview tidak tersedia untuk tipe file ini</p><a href="' + BASE_URL + '/download.php?id=' + id + '" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-500 transition-colors text-sm font-medium"><i class="fas fa-download"></i> Download File</a></div>';
            }

            modal.classList.remove('hidden');
        }

        function closePreviewModal() {
            document.getElementById('previewModal').classList.add('hidden');
            document.getElementById('previewContent').innerHTML = '';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePreviewModal();
        });
    </script>
</body>
</html>