<?php
require '../config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch last run info from settings
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT value_text FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Ensure scraper settings keys exist
$setting_keys = ['scraper_last_run', 'scraper_last_result', 'scraper_cron_enabled'];
foreach ($setting_keys as $key) {
    try {
        $pdo->prepare("INSERT IGNORE INTO settings (key_name, value_text) VALUES (?, '')")->execute([$key]);
    } catch (PDOException $e) {
        try { $pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value_text) VALUES (?, '')")->execute([$key]); } catch (PDOException $e2) {}
    }
}

$last_run    = get_setting($pdo, 'scraper_last_run', 'Henüz çalışmadı');
$last_result = get_setting($pdo, 'scraper_last_result', '-');

// Build the secret token for the cron URL
$secret_token = md5($admin_pass . 'scraper_secret_2026');
$base_url     = current_site_url();
$scraper_url  = $base_url . '/admin/auto_scraper.php?run=1&secret=' . $secret_token;

// Count active scrapers
$active_count = (int)$pdo->query("SELECT COUNT(*) FROM markets WHERE scraper_active = 1 AND scraper_url IS NOT NULL AND scraper_url != ''")->fetchColumn();
$total_markets = (int)$pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otomasyon & Cron - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .log-box {
            background: #020617;
            color: #4ade80;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            padding: 1.25rem;
            border-radius: 12px;
            border: 1px solid #1e3a2f;
            min-height: 200px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .pulse-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #22c55e;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }
        .copy-btn:active { transform: scale(0.96); }
        .step-badge {
            width: 28px; height: 28px; border-radius: 50%;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: white; font-weight: 800; font-size: 13px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="p-6 border-b border-slate-800">
            <a href="index.php" class="font-title text-xl font-black text-white flex items-center gap-2">
                <?php if (file_exists('../uploads/logo.png')): ?>
                    <img src="../uploads/logo.png" alt="marketisleri.com" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <span class="text-red-500 material-symbols-outlined">dashboard</span>
                    marketisleri<span class="text-red-500">.panel</span>
                <?php endif; ?>
            </a>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">space_dashboard</span>
                Dashboard
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span>
                Marketler
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span>
                Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span>
                Sihirli Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span>
                Otomasyon & Cron
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">article</span>
                Blog Yazıları
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span>
                Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">settings</span>
                Ayarlar
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span>
                Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <div>
                <h1 class="font-title text-2xl font-bold text-white">Otomasyon & Cron Yönetimi</h1>
                <p class="text-slate-400 text-sm mt-0.5">Broşür kazıyıcıyı otomatik çalıştırın</p>
            </div>
            <button id="run-now-btn" onclick="runScraper()" 
                    class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-emerald-600/20">
                <span class="material-symbols-outlined text-lg">play_arrow</span>
                Şimdi Çalıştır
            </button>
        </header>

        <div class="p-8 space-y-8 max-w-5xl w-full mx-auto">

            <!-- Status Cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Aktif Scraper</div>
                    <div class="text-3xl font-black text-emerald-400"><?= $active_count ?></div>
                    <div class="text-slate-500 text-sm mt-1">/ <?= $total_markets ?> market</div>
                </div>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Son Çalışma</div>
                    <div class="text-base font-bold text-white"><?= htmlspecialchars($last_run) ?></div>
                </div>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Son Sonuç</div>
                    <div class="text-sm font-semibold text-slate-200"><?= htmlspecialchars($last_result) ?></div>
                </div>
            </div>

            <!-- Live Run Panel -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-400">terminal</span>
                        <h3 class="font-title text-xl font-bold text-white">Canlı Çalışma Günlüğü</h3>
                    </div>
                    <div id="status-badge" class="hidden items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <div class="pulse-dot"></div>
                        Çalışıyor...
                    </div>
                </div>
                <div class="p-6">
                    <div id="log-output" class="log-box">Başlatmak için "Şimdi Çalıştır" butonuna basın...</div>
                    <div id="run-summary" class="hidden mt-4 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-300 text-sm font-semibold"></div>
                </div>
            </div>

            <!-- Cron URL -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-amber-400">link</span>
                        <h3 class="font-title text-xl font-bold text-white">Scraper URL'si (Cron için)</h3>
                    </div>
                    <p class="text-slate-400 text-sm mt-2">Bu URL'yi cPanel Cron Job veya harici bir zamanlayıcıyla çağırabilirsiniz.</p>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex gap-2">
                        <input type="text" id="scraper-url" readonly value="<?= htmlspecialchars($scraper_url) ?>"
                               class="flex-1 bg-slate-950 border border-slate-700 text-slate-200 font-mono text-sm rounded-xl px-4 py-3 outline-none">
                        <button onclick="copyUrl()" id="copy-btn" 
                                class="copy-btn flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-3 rounded-xl font-semibold text-sm transition">
                            <span class="material-symbols-outlined text-lg">content_copy</span>
                            Kopyala
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">⚠️ Bu URL'yi gizli tutun. Admin şifreniz değişirse URL de değişir.</p>
                </div>
            </div>

            <!-- cPanel Setup Instructions -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-blue-400">help_outline</span>
                        <h3 class="font-title text-xl font-bold text-white">cPanel Cron Job Kurulumu</h3>
                    </div>
                    <p class="text-slate-400 text-sm mt-2">Haftada 2 defa otomatik çalışması için aşağıdaki adımları izleyin.</p>
                </div>
                <div class="p-6 space-y-5">

                    <!-- Steps -->
                    <div class="flex gap-4 items-start">
                        <div class="step-badge">1</div>
                        <div>
                            <div class="font-semibold text-white">cPanel'e giriş yapın</div>
                            <div class="text-slate-400 text-sm mt-1">Hosting kontrol panelinize giriş yapın ve <span class="text-amber-400 font-mono">Advanced → Cron Jobs</span> bölümünü açın.</div>
                        </div>
                    </div>

                    <div class="flex gap-4 items-start">
                        <div class="step-badge">2</div>
                        <div>
                            <div class="font-semibold text-white">"Add New Cron Job" bölümüne inin</div>
                            <div class="text-slate-400 text-sm mt-1">Sıklık için <span class="text-emerald-400 font-bold">Twice Weekly</span> seçeneğini kullanın veya elle girin.</div>
                        </div>
                    </div>

                    <div class="flex gap-4 items-start">
                        <div class="step-badge">3</div>
                        <div>
                            <div class="font-semibold text-white">Cron ifadesini ve komutu girin</div>
                            <div class="text-slate-400 text-sm mt-1 mb-3">Salı ve Cuma günleri saat 03:00'da çalışması için:</div>
                            
                            <div class="space-y-3">
                                <div>
                                    <div class="text-xs text-slate-500 uppercase tracking-wider mb-1">Cron İfadesi (Minute Hour Day Month Weekday)</div>
                                    <div class="flex gap-2">
                                        <code class="flex-1 bg-slate-950 border border-slate-700 text-emerald-400 font-mono text-sm rounded-lg px-4 py-2.5">0 3 * * 2,5</code>
                                        <button onclick="copyText('0 3 * * 2,5')" class="copy-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg text-xs transition">
                                            <span class="material-symbols-outlined text-sm">content_copy</span>
                                        </button>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">Salı (2) ve Cuma (5) 03:00'da</div>
                                </div>

                                <div>
                                    <div class="text-xs text-slate-500 uppercase tracking-wider mb-1">Komut (PHP CLI yöntemi — önerilir)</div>
                                    <div class="flex gap-2">
                                        <code id="php-cmd" class="flex-1 bg-slate-950 border border-slate-700 text-blue-300 font-mono text-xs rounded-lg px-4 py-2.5 break-all">
                                            /opt/alt/alt-nodejs24/root/usr/bin/php /home/marketis/public_html/admin/auto_scraper.php >> /home/marketis/scraper.log 2>&1
                                        </code>
                                        <button onclick="copyText(document.getElementById('php-cmd').innerText.trim())" class="copy-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg text-xs transition">
                                            <span class="material-symbols-outlined text-sm">content_copy</span>
                                        </button>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">PHP yolunu hosting sağlayıcınıza göre ayarlayın. Genellikle <code class="text-amber-400">/usr/bin/php</code> veya <code class="text-amber-400">/usr/local/bin/php</code></div>
                                </div>

                                <div>
                                    <div class="text-xs text-slate-500 uppercase tracking-wider mb-1">Komut (URL yöntemi — alternatif)</div>
                                    <div class="flex gap-2">
                                        <code class="flex-1 bg-slate-950 border border-slate-700 text-purple-300 font-mono text-xs rounded-lg px-4 py-2.5 break-all">curl -s "<?= htmlspecialchars($scraper_url) ?>" >> /home/marketis/scraper.log 2>&1</code>
                                        <button onclick="copyText('curl -s \"<?= htmlspecialchars($scraper_url) ?>\" >> /home/marketis/scraper.log 2>&1')" class="copy-btn bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg text-xs transition">
                                            <span class="material-symbols-outlined text-sm">content_copy</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4 items-start">
                        <div class="step-badge">4</div>
                        <div>
                            <div class="font-semibold text-white">"Add New Cron Job" butonuna basın</div>
                            <div class="text-slate-400 text-sm mt-1">Kaydet. Cron artık her Salı ve Cuma 03:00'da otomatik çalışacak ve yeni broşürleri ekleyecek.</div>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-amber-400 text-xl shrink-0">info</span>
                        <div>
                            <div class="font-semibold text-amber-300 text-sm">PHP yolunu nasıl bulurum?</div>
                            <div class="text-slate-400 text-xs mt-1">cPanel'de <span class="text-amber-400">Terminal</span> veya <span class="text-amber-400">SSH</span> açın ve <code class="text-emerald-400">which php</code> yazın. 
                            Yada <a href="../admin/run_scraper.php" class="text-blue-400 hover:text-blue-300 underline">Scraper Çalıştırıcı sayfanızdaki</a> "Node.js tespit edildi" mesajına bakın – benzer bir yol kullanılıyor.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        const scrapeUrl = <?= json_encode($scraper_url) ?>;

        async function runScraper() {
            const btn    = document.getElementById('run-now-btn');
            const log    = document.getElementById('log-output');
            const badge  = document.getElementById('status-badge');
            const summary = document.getElementById('run-summary');

            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">refresh</span> Çalışıyor...';
            badge.classList.remove('hidden');
            badge.classList.add('flex');
            summary.classList.add('hidden');
            log.textContent = '⏳ Başlatılıyor...\n';

            try {
                const resp = await fetch(scrapeUrl, { method: 'GET' });
                const reader = resp.body.getReader();
                const decoder = new TextDecoder();
                log.textContent = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    log.textContent += decoder.decode(value);
                    log.scrollTop = log.scrollHeight;
                }

                // Extract summary from log
                const lines = log.textContent.split('\n');
                const addedLine = lines.find(l => l.includes('Eklenen Broşür:'));
                if (addedLine) {
                    summary.textContent = '✅ ' + addedLine.trim();
                    summary.classList.remove('hidden');
                }
            } catch (e) {
                log.textContent += '\n❌ Bağlantı hatası: ' + e.message;
            }

            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg">play_arrow</span> Şimdi Çalıştır';
            badge.classList.add('hidden');
            badge.classList.remove('flex');
        }

        function copyUrl() {
            const input = document.getElementById('scraper-url');
            navigator.clipboard.writeText(input.value).then(() => {
                const btn = document.getElementById('copy-btn');
                btn.innerHTML = '<span class="material-symbols-outlined text-lg">check</span> Kopyalandı!';
                btn.classList.add('text-emerald-400');
                setTimeout(() => {
                    btn.innerHTML = '<span class="material-symbols-outlined text-lg">content_copy</span> Kopyala';
                    btn.classList.remove('text-emerald-400');
                }, 2000);
            });
        }

        function copyText(text) {
            navigator.clipboard.writeText(text.trim()).then(() => {
                // Brief visual feedback via tooltip-like approach
                const el = document.createElement('div');
                el.textContent = 'Kopyalandı!';
                el.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#22c55e;color:white;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;z-index:9999;';
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 2000);
            });
        }
    </script>
</body>
</html>
