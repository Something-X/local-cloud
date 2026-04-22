<?php

/**
 * Cloud Sekolah - Admin Dashboard
 * File Manager dengan fitur lengkap
 */
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Sinkronisasi filesystem <-> database
syncFilesystem();

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

// Stats
$totalFiles = $db->query("SELECT COUNT(*) FROM files")->fetchColumn();
$totalFolders = $db->query("SELECT COUNT(*) FROM folders")->fetchColumn();
$totalSize = $db->query("SELECT COALESCE(SUM(size), 0) FROM files")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cloud Sekolah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        },
                        dark: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar-link {
            transition: all 0.2s;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }

        .file-card {
            transition: all 0.2s;
        }

        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .drop-zone.drag-over {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        .modal-backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .toast {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .progress-bar {
            transition: width 0.3s ease;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Mobile sidebar */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
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

        /* Mobile context menu fix */
        @media (max-width: 640px) {
            .file-card .group-hover\:opacity-100 {
                opacity: 1 !important;
            }
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 md:w-64 bg-slate-900 text-white flex flex-col flex-shrink-0" id="sidebar">
            <!-- Logo -->
            <div class="p-5 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <i class="fas fa-cloud text-white text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="font-bold text-lg leading-tight">Cloud Sekolah</h1>
                        <p class="text-xs text-slate-400">File Manager</p>
                    </div>
                    <!-- Close button for mobile -->
                    <button onclick="toggleSidebar()" class="md:hidden w-8 h-8 hover:bg-slate-800 rounded-lg flex items-center justify-center text-slate-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Menu -->
            <nav class="flex-1 p-3 space-y-1">
                <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium">
                    <i class="fas fa-folder-open w-5 text-center"></i>
                    <span>File Manager</span>
                </a>
                <a href="../guest/index.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-400">
                    <i class="fas fa-globe w-5 text-center"></i>
                    <span>Lihat sebagai Guest</span>
                </a>
            </nav>

            <!-- Stats -->
            <div class="p-4 border-t border-slate-700/50">
                <div class="bg-slate-800/50 rounded-xl p-3 space-y-2">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Total File</span>
                        <span class="text-blue-400 font-semibold"><?= $totalFiles ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Total Folder</span>
                        <span class="text-cyan-400 font-semibold"><?= $totalFolders ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Penyimpanan</span>
                        <span class="text-emerald-400 font-semibold"><?= formatFileSize($totalSize) ?></span>
                    </div>
                </div>
            </div>

            <!-- User -->
            <div class="p-4 border-t border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></p>
                        <p class="text-xs text-slate-400">Admin</p>
                    </div>
                    <a href="../auth/logout.php" class="text-slate-400 hover:text-red-400 transition-colors" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 sm:py-4 flex-shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Hamburger Menu -->
                        <button onclick="toggleSidebar()" class="md:hidden w-10 h-10 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center text-slate-600 flex-shrink-0 transition-colors">
                            <i class="fas fa-bars"></i>
                        </button>
                        <!-- Breadcrumb -->
                        <nav class="flex items-center gap-1 sm:gap-2 text-sm min-w-0 overflow-x-auto">
                            <a href="dashboard.php" class="text-slate-500 hover:text-blue-600 transition-colors flex items-center gap-1 flex-shrink-0">
                                <i class="fas fa-home"></i>
                                <span class="hidden sm:inline">Home</span>
                            </a>
                            <?php foreach ($breadcrumbs as $bc): ?>
                                <i class="fas fa-chevron-right text-slate-300 text-xs flex-shrink-0"></i>
                                <a href="dashboard.php?folder=<?= $bc['id'] ?>" class="text-slate-500 hover:text-blue-600 transition-colors truncate max-w-[100px] sm:max-w-none">
                                    <?= htmlspecialchars($bc['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                        <!-- View Toggle -->
                        <div class="flex bg-slate-100 rounded-lg p-1">
                            <button onclick="setView('grid')" id="gridBtn" class="px-2.5 sm:px-3 py-1.5 rounded-md text-sm font-medium transition-all bg-white shadow text-blue-600">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button onclick="setView('list')" id="listBtn" class="px-2.5 sm:px-3 py-1.5 rounded-md text-sm font-medium transition-all text-slate-500">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                        <!-- Sync -->
                        <button onclick="syncFiles()" id="syncBtn" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-sm font-medium transition-colors" title="Sinkronisasi dengan server">
                            <i class="fas fa-sync-alt" id="syncIcon"></i>
                            <span class="hidden lg:inline">Sync</span>
                        </button>
                        <!-- New Folder -->
                        <button onclick="showCreateFolderModal()" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 sm:px-4 py-2 rounded-xl text-sm font-medium transition-colors">
                            <i class="fas fa-folder-plus"></i>
                            <span class="hidden sm:inline">Folder Baru</span>
                        </button>
                        <!-- Upload -->
                        <button onclick="document.getElementById('fileInput').click()" class="flex items-center gap-2 bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-500 hover:to-cyan-400 text-white px-3 sm:px-4 py-2 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/25">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="hidden sm:inline">Upload File</span>
                        </button>
                        <input type="file" id="fileInput" multiple class="hidden" onchange="handleFileSelect(this.files)">
                    </div>
                </div>
            </header>

            <!-- Drop Zone & Content -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6" id="contentArea">
                <!-- Upload Drop Zone -->
                <div id="dropZone" class="drop-zone border-2 border-dashed border-slate-300 rounded-2xl p-6 sm:p-8 mb-6 text-center hidden">
                    <div class="text-slate-400">
                        <i class="fas fa-cloud-upload-alt text-4xl mb-3"></i>
                        <p class="text-lg font-medium">Drop file di sini untuk upload</p>
                        <p class="text-sm mt-1">atau klik tombol Upload File</p>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="uploadProgress" class="hidden mb-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-slate-700 truncate mr-2" id="uploadFileName">Uploading...</span>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <span class="text-sm text-blue-600 font-semibold" id="uploadPercent">0%</span>
                                <button onclick="cancelUpload()" class="text-xs bg-red-100 text-red-600 hover:bg-red-200 hover:text-red-700 px-2 py-1 rounded-md font-medium transition-colors">
                                    <i class="fas fa-times mr-1"></i>Batal
                                </button>
                            </div>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar bg-gradient-to-r from-blue-600 to-cyan-500 h-2 rounded-full" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <div id="itemsContainer" class="relative min-h-[50vh]">
                    <!-- Loader overlay -->
                    <div id="filesLoader" class="absolute inset-0 bg-white/60 backdrop-blur-[1px] rounded-xl z-10 flex flex-col items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
                        <i class="fas fa-circle-notch fa-spin text-blue-500 text-3xl mb-3 shadow-sm rounded-full"></i>
                        <span class="text-slate-600 font-semibold text-sm">Memuat Data...</span>
                    </div>
                    <!-- Empty State -->
                    <?php if (empty($folders) && empty($files)): ?>
                        <div class="text-center py-16 sm:py-20">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-folder-open text-slate-400 text-2xl sm:text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-slate-600 mb-1">Folder kosong</h3>
                            <p class="text-slate-400 text-sm">Upload file atau buat folder baru untuk memulai</p>
                        </div>
                    <?php else: ?>

                        <!-- Grid View -->
                        <div id="gridView">
                            <!-- Folders -->
                            <?php if (!empty($folders)): ?>
                                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Folder</h3>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 mb-6">
                                    <?php foreach ($folders as $folder): ?>
                                        <div class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 cursor-pointer group relative" onclick="window.location='dashboard.php?folder=<?= $folder['id'] ?>'">
                                            <div class="text-center">
                                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-50 rounded-xl flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:bg-blue-100 transition-colors">
                                                    <i class="fas fa-folder text-blue-500 text-xl sm:text-2xl"></i>
                                                </div>
                                                <p class="text-xs sm:text-sm font-medium text-slate-700 truncate" title="<?= htmlspecialchars($folder['name']) ?>"><?= htmlspecialchars($folder['name']) ?></p>
                                                <p class="text-xs text-slate-400 mt-1 hidden sm:block"><?= date('d M Y', strtotime($folder['created_at'])) ?></p>
                                            </div>
                                            <!-- Context Menu -->
                                            <div class="absolute top-2 right-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onclick="event.stopPropagation(); showContextMenu(event, 'folder', <?= $folder['id'] ?>, '<?= htmlspecialchars($folder['name'], ENT_QUOTES) ?>')" class="w-8 h-8 bg-slate-100 hover:bg-slate-200 rounded-lg flex items-center justify-center text-slate-500">
                                                    <i class="fas fa-ellipsis-v text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Files -->
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
                                        <div class="file-card bg-white rounded-xl border border-slate-200 p-3 sm:p-4 cursor-pointer group relative" onclick="previewFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>', '<?= $file['type'] ?>', '<?= urlEncodePath($file['path']) ?>')">
                                            <div class="text-center">
                                                <?php if ($category === 'image'): ?>
                                                    <div class="w-full h-20 sm:h-24 rounded-lg mb-2 sm:mb-3 overflow-hidden bg-slate-100">
                                                        <img src="../uploads/<?= urlEncodePath($file['path']) ?>" alt="" class="w-full h-full object-cover">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-12 h-12 sm:w-14 sm:h-14 <?= $icon[2] ?> rounded-xl flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                                        <i class="fas <?= $icon[0] ?> <?= $icon[1] ?> text-xl sm:text-2xl"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <p class="text-xs sm:text-sm font-medium text-slate-700 truncate" title="<?= htmlspecialchars($file['original_name']) ?>"><?= htmlspecialchars($file['original_name']) ?></p>
                                                <p class="text-xs text-slate-400 mt-1"><?= formatFileSize($file['size']) ?></p>
                                            </div>
                                            <!-- Context Menu -->
                                            <div class="absolute top-2 right-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onclick="event.stopPropagation(); showContextMenu(event, 'file', <?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>')" class="w-8 h-8 bg-slate-100/80 hover:bg-slate-200 rounded-lg flex items-center justify-center text-slate-500">
                                                    <i class="fas fa-ellipsis-v text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- List View -->
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
                                            <tr class="border-b border-slate-100 hover:bg-blue-50/50 cursor-pointer transition-colors" onclick="window.location='dashboard.php?folder=<?= $folder['id'] ?>'">
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
                                                    <button onclick="showContextMenu(event, 'folder', <?= $folder['id'] ?>, '<?= htmlspecialchars($folder['name'], ENT_QUOTES) ?>')" class="w-8 h-8 hover:bg-slate-100 rounded-lg inline-flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                                                        <i class="fas fa-ellipsis-v text-xs"></i>
                                                    </button>
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
                                            <tr class="border-b border-slate-100 hover:bg-blue-50/50 cursor-pointer transition-colors" onclick="previewFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>', '<?= $file['type'] ?>', '<?= urlEncodePath($file['path']) ?>')">
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
                                                    <button onclick="event.stopPropagation(); showContextMenu(event, 'file', <?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name'], ENT_QUOTES) ?>')" class="w-8 h-8 hover:bg-slate-100 rounded-lg inline-flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                                                        <i class="fas fa-ellipsis-v text-xs"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white rounded-xl shadow-2xl border border-slate-200 py-2 w-48 z-[60] hidden">
        <button onclick="contextAction('open')" id="ctxOpen" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
            <i class="fas fa-folder-open w-4 text-center text-slate-400"></i> Buka
        </button>
        <button onclick="contextAction('download')" id="ctxDownload" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
            <i class="fas fa-download w-4 text-center text-slate-400"></i> Download
        </button>
        <button onclick="contextAction('share')" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors">
            <i class="fas fa-share-alt w-4 text-center text-slate-400"></i> Share Link
        </button>
        <hr class="my-1 border-slate-100">
        <button onclick="contextAction('delete')" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors">
            <i class="fas fa-trash-alt w-4 text-center"></i> Hapus
        </button>
    </div>

    <!-- Create Folder Modal -->
    <div id="folderModal" class="fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 sm:p-6 transform transition-all">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-slate-800">Buat Folder Baru</h3>
                <button onclick="closeFolderModal()" class="w-8 h-8 hover:bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" id="folderName" placeholder="Nama folder"
                class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                onkeypress="if(event.key==='Enter')createFolder()">
            <div class="flex justify-end gap-3 mt-5">
                <button onclick="closeFolderModal()" class="px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Batal</button>
                <button onclick="createFolder()" class="px-5 py-2.5 text-sm font-medium bg-gradient-to-r from-blue-600 to-cyan-500 text-white rounded-xl hover:shadow-lg hover:shadow-blue-500/25 transition-all">Buat Folder</button>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 flex-shrink-0">
                <h3 class="text-base sm:text-lg font-bold text-slate-800 truncate mr-3" id="previewTitle">Preview</h3>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="#" id="previewDownload" download="" class="px-3 sm:px-4 py-2 text-sm font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors flex items-center gap-2">
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

    <!-- Share Modal -->
    <div id="shareModal" class="fixed inset-0 modal-backdrop z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 sm:p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-slate-800">Share Link</h3>
                <button onclick="closeShareModal()" class="w-8 h-8 hover:bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex gap-2">
                <input type="text" id="shareUrl" readonly class="flex-1 bg-slate-100 border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-700 min-w-0">
                <button onclick="copyShareLink()" class="px-4 py-3 bg-gradient-to-r from-blue-600 to-cyan-500 text-white rounded-xl hover:shadow-lg transition-all flex-shrink-0" title="Copy">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <p class="text-xs text-slate-400 mt-3"><i class="fas fa-info-circle mr-1"></i>Siapapun dengan link ini dapat melihat dan download file.</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-20 right-4 sm:top-24 sm:right-6 z-[70] space-y-2 max-w-[calc(100vw-2rem)]"></div>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const currentFolderId = <?= $currentFolderId ?? 'null' ?>;
        let currentView = localStorage.getItem('viewMode') || 'grid';
        let ctxType = '',
            ctxId = 0,
            ctxName = '';

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }

        // Initialize view
        document.addEventListener('DOMContentLoaded', () => {
            setView(currentView);
            initDragDrop();
        });

        // View Toggle
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

        let activeXHRs = [];

        function handleFileSelect(files) {
            for (let i = 0; i < files.length; i++) {
                uploadFile(files[i]);
            }
            document.getElementById('fileInput').value = '';
        }

        // Drag & Drop
        function initDragDrop() {
            const content = document.getElementById('contentArea');
            const dropZone = document.getElementById('dropZone');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                content.addEventListener(evt, preventDefaults);
            });

            content.addEventListener('dragenter', () => {
                dropZone.classList.remove('hidden');
                dropZone.classList.add('drag-over');
            });

            content.addEventListener('dragleave', (e) => {
                if (!content.contains(e.relatedTarget)) {
                    dropZone.classList.add('hidden');
                    dropZone.classList.remove('drag-over');
                }
            });

            content.addEventListener('drop', (e) => {
                dropZone.classList.add('hidden');
                dropZone.classList.remove('drag-over');
                const files = e.dataTransfer.files;
                if (files.length) handleFileSelect(files);
            });
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // File Upload
        function handleFileSelect(files) {
            for (let i = 0; i < files.length; i++) {
                uploadFile(files[i]);
            }
            document.getElementById('fileInput').value = '';
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            if (currentFolderId) formData.append('folder_id', currentFolderId);

            const xhr = new XMLHttpRequest();
            activeXHRs.push(xhr); // Simpan proses XHR ke dalam array

            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const fileName = document.getElementById('uploadFileName');
            const percent = document.getElementById('uploadPercent');

            progressDiv.classList.remove('hidden');
            fileName.textContent = file.name;

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = pct + '%';
                    percent.textContent = pct + '%';
                }
            });

            xhr.addEventListener('load', () => {
                // Hapus proses yang sudah selesai dari array
                activeXHRs = activeXHRs.filter(x => x !== xhr);

                if (activeXHRs.length === 0) {
                    progressDiv.classList.add('hidden');
                    progressBar.style.width = '0%';
                }

                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        showToast('File berhasil diupload: ' + file.name, 'success');
                        // Reload hanya jika tidak ada upload yang tersisa
                        if (activeXHRs.length === 0) reloadFiles();
                    } else {
                        showToast(res.message, 'error');
                    }
                } catch (e) {
                    showToast('Terjadi kesalahan upload', 'error');
                }
            });

            xhr.addEventListener('error', () => {
                activeXHRs = activeXHRs.filter(x => x !== xhr);
                if (activeXHRs.length === 0) progressDiv.classList.add('hidden');
                showToast('Gagal upload file', 'error');
            });

            xhr.open('POST', 'upload.php');
            xhr.send(formData);
        }

        // Tambahkan fungsi baru ini untuk menangani klik tombol "Batal"
        function cancelUpload() {
            if (activeXHRs.length > 0) {
                // Batalkan semua proses upload yang sedang berjalan
                activeXHRs.forEach(xhr => xhr.abort());
                activeXHRs = []; // Kosongkan array

                const progressDiv = document.getElementById('uploadProgress');
                const progressBar = document.getElementById('progressBar');

                // Sembunyikan dan reset progress bar
                progressDiv.classList.add('hidden');
                progressBar.style.width = '0%';
                document.getElementById('uploadPercent').textContent = '0%';

                showToast('Upload dibatalkan', 'info');

                // Reset input file agar bisa memilih file yang sama lagi jika perlu
                document.getElementById('fileInput').value = '';
            }
        }

        // Create Folder
        function showCreateFolderModal() {
            document.getElementById('folderModal').classList.remove('hidden');
            document.getElementById('folderName').value = '';
            setTimeout(() => document.getElementById('folderName').focus(), 100);
        }

        function closeFolderModal() {
            document.getElementById('folderModal').classList.add('hidden');
        }

        function createFolder() {
            const name = document.getElementById('folderName').value.trim();
            if (!name) {
                showToast('Nama folder harus diisi', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            if (currentFolderId) formData.append('parent_id', currentFolderId);

            fetch('folder_create.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        closeFolderModal();
                        showToast('Folder berhasil dibuat', 'success');
                        reloadFiles();
                    } else {
                        showToast(res.message, 'error');
                    }
                })
                .catch(() => showToast('Terjadi kesalahan ', 'error'));
        }

        // Context Menu
        function showContextMenu(e, type, id, name) {
            e.preventDefault();
            e.stopPropagation();
            ctxType = type;
            ctxId = id;
            ctxName = name;

            const menu = document.getElementById('contextMenu');
            const ctxOpen = document.getElementById('ctxOpen');
            const ctxDownload = document.getElementById('ctxDownload');

            if (type === 'folder') {
                ctxOpen.classList.remove('hidden');
                ctxDownload.classList.add('hidden');
            } else {
                ctxOpen.classList.add('hidden');
                ctxDownload.classList.remove('hidden');
            }

            // Position the menu
            const menuWidth = 192;
            const menuHeight = 200;
            let x = e.clientX;
            let y = e.clientY;

            // For touch events
            if (e.touches) {
                x = e.touches[0].clientX;
                y = e.touches[0].clientY;
            }

            if (x + menuWidth > window.innerWidth) {
                x = window.innerWidth - menuWidth - 10;
            }
            if (y + menuHeight > window.innerHeight) {
                y = y - menuHeight;
            }
            if (x < 10) x = 10;
            if (y < 10) y = 10;

            menu.style.top = y + 'px';
            menu.style.left = x + 'px';
            menu.classList.remove('hidden');
        }

        document.addEventListener('click', () => {
            document.getElementById('contextMenu').classList.add('hidden');
        });

        function contextAction(action) {
            document.getElementById('contextMenu').classList.add('hidden');

            if (action === 'open' && ctxType === 'folder') {
                window.location = 'dashboard.php?folder=' + ctxId;
            } else if (action === 'download' && ctxType === 'file') {
                const a = document.createElement('a');
                a.href = BASE_URL + '/download.php?id=' + ctxId;
                a.download = ctxName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else if (action === 'share') {
                generateShareLink();
            } else if (action === 'delete') {
                if (confirm('Hapus ' + ctxType + ' "' + ctxName + '"?')) {
                    deleteItem();
                }
            }
        }

        // Delete
        function deleteItem() {
            const formData = new FormData();
            formData.append('type', ctxType);
            formData.append('id', ctxId);

            fetch('delete.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast(res.message, 'success');
                        reloadFiles();
                    } else {
                        showToast(res.message, 'error');
                    }
                })
                .catch(() => showToast('Terjadi kesalahan', 'error'));
        }

        // Share Link
        function generateShareLink() {
            const formData = new FormData();
            formData.append('type', ctxType);
            formData.append('id', ctxId);

            fetch('share.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const fullUrl = window.location.origin + res.url;
                        document.getElementById('shareUrl').value = fullUrl;
                        document.getElementById('shareModal').classList.remove('hidden');
                    } else {
                        showToast(res.message, 'error');
                    }
                })
                .catch(() => showToast('Terjadi kesalahan', 'error'));
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
        }

        function copyShareLink() {
            const input = document.getElementById('shareUrl');
            input.select();
            navigator.clipboard.writeText(input.value).then(() => {
                showToast('Link berhasil disalin!', 'success');
            });
        }

        // Preview
        function previewFile(id, name, type, path) {
            const modal = document.getElementById('previewModal');
            const title = document.getElementById('previewTitle');
            const content = document.getElementById('previewContent');
            const downloadBtn = document.getElementById('previewDownload');

            title.textContent = name;
            downloadBtn.href = BASE_URL + '/download.php?id=' + id;
            downloadBtn.setAttribute('download', name);

            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif'];
            const videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', '3gp'];
            const audioExts = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma', 'm4a'];

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

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePreviewModal();
                closeFolderModal();
                closeShareModal();
            }
        });

        // Sync button handler
        function syncFiles() {
            const icon = document.getElementById('syncIcon');
            icon.classList.add('fa-spin');

            fetch('sync.php', {
                    method: 'POST'
                })
                .then(r => r.json())
                .then(res => {
                    icon.classList.remove('fa-spin');
                    if (res.success) {
                        showToast('Sinkronisasi berhasil', 'success');
                        reloadFiles();
                    } else {
                        showToast(res.message || 'Gagal sinkronisasi', 'error');
                    }
                })
                .catch(() => {
                    icon.classList.remove('fa-spin');
                    showToast('Gagal sinkronisasi', 'error');
                });
        }

        // AJAX Reload
        function reloadFiles() {
            const loader = document.getElementById('filesLoader');
            if (loader) {
                loader.classList.remove('opacity-0', 'pointer-events-none');
                loader.classList.add('opacity-100');
            }

            fetch(window.location.href)
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('itemsContainer');
                    if (newContainer) {
                        document.getElementById('itemsContainer').innerHTML = newContainer.innerHTML;
                        setView(currentView); // Tetap di mode terakhir (Grid/List)
                    }
                })
                .catch(err => {
                    console.error('AJAX Error:', err);
                    location.reload(); // Fallback jika gagal
                });
        }

        // Toast
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const colors = {
                success: 'bg-emerald-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${colors[type]} text-white px-4 sm:px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 text-sm font-medium`;
            toast.innerHTML = `<i class="fas ${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>

</html>