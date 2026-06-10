<?php
/**
 * git_pull.php
 * cPanel Git Deployer & Conflict Resolver Dashboard
 * Standalone file to check git status, fetch, reset and pull.
 */
session_start();

// Simple auth check to prevent unauthorized execution
// Using a simple password query parameter: ?key=161224
$auth_key = "161224";
$is_authenticated = isset($_GET['key']) && $_GET['key'] === $auth_key;

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Deploy & Sync Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-center items-center p-4">

    <div class="max-w-3xl w-full bg-slate-950 rounded-3xl border border-slate-800 shadow-2xl p-6 md:p-8 space-y-8 relative overflow-hidden">
        <!-- Glowing accents -->
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-red-500/5 blur-[80px] pointer-events-none"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-rose-500/5 blur-[80px] pointer-events-none"></div>

        <div class="flex items-center justify-between border-b border-slate-800 pb-6">
            <div class="flex items-center gap-3">
                <span class="w-12 h-12 rounded-2xl bg-red-500/10 text-red-500 flex items-center justify-center material-symbols-outlined text-2xl border border-red-500/20">sync_alt</span>
                <div>
                    <h1 class="font-title text-xl font-black text-white">Git Deploy & Sync Panel</h1>
                    <p class="text-xs text-slate-500">cPanel için Güvenli Git Yönetim Konsolu</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $is_authenticated ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
                <?= $is_authenticated ? 'Oturum Açık' : 'Yetkisiz Erişim' ?>
            </span>
        </div>

        <?php if (!$is_authenticated): ?>
            <!-- Auth Form -->
            <div class="py-12 text-center max-w-md mx-auto space-y-6">
                <span class="material-symbols-outlined text-5xl text-red-500 animate-pulse">lock</span>
                <div class="space-y-2">
                    <h2 class="font-title text-lg font-bold text-white">Güvenlik Doğrulaması</h2>
                    <p class="text-sm text-slate-400">Bu panele erişmek için lütfen şifreyi girin veya URL'ye <code>?key=SIFRE</code> parametresini ekleyin.</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <input type="password" name="key" required
                           class="flex-1 bg-slate-900 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3 outline-none text-sm placeholder:text-slate-600"
                           placeholder="Şifreyi yazın...">
                    <button type="submit" 
                            class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-3 rounded-xl transition text-sm">
                        Giriş Yap
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Control Panel -->
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Git Status / Info -->
                    <div class="bg-slate-900/50 rounded-2xl border border-slate-800 p-5 space-y-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-red-500">info</span>
                            Mevcut Git Durumu
                        </h3>
                        <div class="space-y-2 text-xs text-slate-400">
                            <p><strong>Çalışma Dizini:</strong> <code class="bg-slate-950 p-1 rounded"><?= htmlspecialchars(__DIR__) ?></code></p>
                            <p><strong>PHP Kullanıcısı:</strong> <code class="bg-slate-950 p-1 rounded"><?= htmlspecialchars(get_current_user()) ?></code></p>
                            <p><strong>Git Versiyonu:</strong> 
                                <code class="bg-slate-950 p-1 rounded">
                                    <?php 
                                    $git_ver = @shell_exec('git --version');
                                    echo htmlspecialchars($git_ver ? trim($git_ver) : 'Git kurulu değil veya PHP exec() kapalı');
                                    ?>
                                </code>
                            </p>
                        </div>
                    </div>

                    <!-- Actions Panel -->
                    <div class="bg-slate-900/50 rounded-2xl border border-slate-800 p-5 space-y-4">
                        <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm text-red-500">build</span>
                            Eylemler
                        </h3>
                        <div class="flex flex-col gap-2">
                            <a href="?key=<?= $auth_key ?>&action=status" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs py-3 px-4 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">troubleshoot</span> Git Durumunu Sorgula
                            </a>
                            <a href="?key=<?= $auth_key ?>&action=pull" class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs py-3 px-4 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">cloud_download</span> Değişiklikleri Çek (Git Pull)
                            </a>
                            <a href="?key=<?= $auth_key ?>&action=force_reset" onclick="return confirm('UYARI: Sunucudaki tüm değişiklikler sıfırlanacak ve GitHub ile eşitlenecektir. Emin misiniz?')" class="bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs py-3 px-4 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">published_with_changes</span> Çakışmaları Zorla Düzelt (Hard Reset & Pull)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Terminal / Execution Log Output -->
                <?php
                if (isset($_GET['action'])) {
                    $action = $_GET['action'];
                    $output = [];
                    $commands = [];

                    if ($action === 'status') {
                        $commands[] = 'git status 2>&1';
                        $commands[] = 'git branch -a 2>&1';
                        $commands[] = 'git log -n 5 2>&1';
                    } elseif ($action === 'pull') {
                        $commands[] = 'git fetch origin 2>&1';
                        $commands[] = 'git pull origin main 2>&1';
                        $commands[] = 'git status 2>&1';
                    } elseif ($action === 'force_reset') {
                        $commands[] = 'git fetch --all 2>&1';
                        $commands[] = 'git reset --hard origin/main 2>&1';
                        $commands[] = 'git clean -fd 2>&1'; // cleans untracked folders if needed, except ignored
                        $commands[] = 'git status 2>&1';
                    }

                    echo '<div class="space-y-2">';
                    echo '<h3 class="text-sm font-bold text-white flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-red-500">terminal</span> İşlem Çıktıları</h3>';
                    echo '<div class="bg-slate-950 rounded-2xl border border-slate-800 p-4 font-mono text-xs text-slate-300 space-y-4 overflow-x-auto max-h-96">';
                    
                    foreach ($commands as $cmd) {
                        echo "<div class='space-y-1'>";
                        echo "<span class='text-red-400 font-bold'>$ </span><span class='text-slate-100 font-bold'>$cmd</span>";
                        $res = @shell_exec($cmd);
                        echo "<pre class='text-slate-400 bg-slate-900/30 p-2 rounded border border-slate-900/50 mt-1 whitespace-pre-wrap'>" . htmlspecialchars($res ?: '(Çıktı yok veya komut çalıştırılamadı)') . "</pre>";
                        echo "</div>";
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                ?>

                <!-- Self Destruction Reminder -->
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-sm mt-0.5 shrink-0">warning</span>
                    <div>
                        <strong>GÜVENLİK NOTU:</strong> Git güncelleme işlemleri bittikten sonra bu <code>git_pull.php</code> dosyasını sunucudan silmeyi veya ismini tahmin edilemeyecek bir şeyle değiştirmeyi unutmayın!
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center text-xs text-slate-600 border-t border-slate-850 pt-6">
            &copy; 2026 marketisleri.com &bull; Git Deploy Utility
        </div>
    </div>

</body>
</html>
