<?php
/**
 * Cloud Sekolah - Login Page
 */
session_start();

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/database.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: ../admin/dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Cloud Sekolah</title>
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
        .login-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .glow-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .float-animation { animation: float 3s ease-in-out infinite; }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Background orbs -->
    <div class="orb w-96 h-96 bg-blue-500 -top-48 -left-48" style="position:fixed;"></div>
    <div class="orb w-80 h-80 bg-purple-500 -bottom-40 -right-40" style="position:fixed;"></div>
    <div class="orb w-64 h-64 bg-cyan-500 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" style="position:fixed;"></div>

    <div class="glass-card rounded-2xl p-8 w-full max-w-md relative z-10">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-2xl flex items-center justify-center mx-auto mb-4 float-animation shadow-lg shadow-blue-500/25">
                <i class="fas fa-cloud text-white text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Cloud Sekolah</h1>
            <p class="text-slate-400 mt-1 text-sm">Masuk ke panel administrator</p>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
            <i class="fas fa-exclamation-circle"></i>
            <span class="text-sm"><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-2">Username</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" name="username" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-xl pl-12 pr-4 py-3 focus:outline-none glow-input focus:border-blue-500 transition-all placeholder-slate-500"
                        placeholder="Masukkan username">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 text-sm font-medium mb-2">Password</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" name="password" required id="passwordInput"
                        class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-xl pl-12 pr-12 py-3 focus:outline-none glow-input focus:border-blue-500 transition-all placeholder-slate-500"
                        placeholder="Masukkan password">
                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" 
                class="w-full bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-500 hover:to-cyan-400 text-white font-semibold py-3 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-lg hover:shadow-blue-500/25 active:scale-95">
                <i class="fas fa-sign-in-alt mr-2"></i>Masuk
            </button>
        </form>

        <!-- Back to Guest -->
        <div class="text-center mt-6">
            <a href="../guest/index.php" class="text-slate-400 hover:text-blue-400 text-sm transition-colors">
                <i class="fas fa-arrow-left mr-1"></i>Kembali ke halaman utama
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
