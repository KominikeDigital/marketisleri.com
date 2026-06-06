<?php
require '../config.php';

// If already logged in, redirect to index
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header("Location: index.php");
    exit;
}

if (isset($_POST['login'])) {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    
    if ($user === $admin_user && $pass === $admin_pass) {
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Hatalı kullanıcı adı veya şifre!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel Girişi - marketisleri.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen flex items-center justify-center relative overflow-hidden px-4">
    <!-- Decorative background elements -->
    <div class="absolute top-[-20%] left-[-20%] w-[60%] h-[60%] rounded-full bg-red-900/20 blur-[150px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-20%] w-[60%] h-[60%] rounded-full bg-rose-900/20 blur-[150px] pointer-events-none"></div>

    <div class="w-full max-w-md z-10">
        <div class="text-center mb-8">
            <h1 class="font-title text-3xl font-black tracking-tight text-white mb-2">
                marketisleri<span class="text-red-500">.com</span>
            </h1>
            <p class="text-slate-400 text-sm">Yönetici Paneli Girişi</p>
        </div>

        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl relative">
            <?php if (isset($error)): ?>
                <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-xl flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shrink-0"></span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" class="space-y-6">
                <div>
                    <label for="user" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kullanıcı Adı</label>
                    <input type="text" id="user" name="user" required 
                           class="w-full bg-slate-950/50 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3.5 outline-none transition"
                           placeholder="admin">
                </div>

                <div>
                    <label for="pass" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Şifre</label>
                    <input type="password" id="pass" name="pass" required 
                           class="w-full bg-slate-950/50 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3.5 outline-none transition"
                           placeholder="••••••••">
                </div>

                <button type="submit" name="login" 
                        class="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-bold py-3.5 rounded-xl shadow-lg hover:shadow-red-600/20 transition-all duration-300">
                    Giriş Yap
                </button>
            </form>
        </div>
        
        <div class="text-center mt-6">
            <a href="../" class="text-sm text-slate-500 hover:text-slate-400 transition">← Siteye Geri Dön</a>
        </div>
    </div>
</body>
</html>