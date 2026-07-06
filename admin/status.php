<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch last run info from settings
function get_setting_val(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT value_text FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    } catch (PDOException $e) {
        return $default;
    }
}

$last_run    = get_setting_val($pdo, 'scraper_last_run', 'Henüz çalışmadı');
$last_result = get_setting_val($pdo, 'scraper_last_result', '-');

// System Status Queries
$status_data = [
    'db_status' => 'Aktif',
    'db_driver' => $db_driver,
    'db_size' => 'Bilinmiyor',
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'disk_free' => 'Bilinmiyor',
    'disk_total' => 'Bilinmiyor',
    
    // Counts
    'active_brochures' => 0,
    'active_scrapers' => 0,
    'total_subscribers' => 0,
    'total_brochures' => 0,
    'total_products' => 0,
    
    // 24 Hours
    'brochures_24h' => 0,
    'products_24h' => 0,
    'subscribers_24h' => 0,
    
    // 7 Days
    'brochures_7d' => 0,
    'products_7d' => 0,
    'subscribers_7d' => 0,
    
    // 30 Days
    'brochures_30d' => 0,
    'products_30d' => 0,
    'subscribers_30d' => 0,
];

// Check database connection and size
try {
    if ($db_driver === 'sqlite') {
        if (file_exists($db_path)) {
            $size_bytes = filesize($db_path);
            $status_data['db_size'] = round($size_bytes / 1024 / 1024, 2) . ' MB';
        }
    } else {
        // MySQL database size query
        $size_query = $pdo->prepare("
            SELECT SUM(data_length + index_length) 
            FROM information_schema.TABLES 
            WHERE table_schema = ?
        ");
        $size_query->execute([$db_name]);
        $size_bytes = $size_query->fetchColumn();
        if ($size_bytes) {
            $status_data['db_size'] = round($size_bytes / 1024 / 1024, 2) . ' MB';
        }
    }
} catch (Exception $e) {
    $status_data['db_status'] = 'Hata: ' . $e->getMessage();
}

// Disk space checks
try {
    $free = disk_free_space(__DIR__);
    $total = disk_total_space(__DIR__);
    if ($free !== false && $total !== false) {
        $status_data['disk_free'] = round($free / 1024 / 1024 / 1024, 2) . ' GB';
        $status_data['disk_total'] = round($total / 1024 / 1024 / 1024, 2) . ' GB';
    }
} catch (Exception $e) {}

// Calculate Date thresholds
$now = date('Y-m-d H:i:s');
$time_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
$time_7d = date('Y-m-d H:i:s', strtotime('-7 days'));
$time_30d = date('Y-m-d H:i:s', strtotime('-30 days'));
$today_date = date('Y-m-d');

try {
    // Active brochures
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE start_date <= ? AND end_date >= ?");
    $stmt->execute([$today_date, $today_date]);
    $status_data['active_brochures'] = (int)$stmt->fetchColumn();
    
    // Active scrapers
    $status_data['active_scrapers'] = (int)$pdo->query("SELECT COUNT(*) FROM markets WHERE scraper_active = 1 AND scraper_url IS NOT NULL AND scraper_url != ''")->fetchColumn();
    
    // Totals
    $status_data['total_subscribers'] = (int)$pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $status_data['total_brochures'] = (int)$pdo->query("SELECT COUNT(*) FROM brochures")->fetchColumn();
    $status_data['total_products'] = (int)$pdo->query("SELECT COUNT(*) FROM brochure_products")->fetchColumn();

    // 24 Hours
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE created_at >= ?");
    $stmt->execute([$time_24h]);
    $status_data['brochures_24h'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochure_products WHERE analyzed_at >= ?");
    $stmt->execute([$time_24h]);
    $status_data['products_24h'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE created_at >= ?");
    $stmt->execute([$time_24h]);
    $status_data['subscribers_24h'] = (int)$stmt->fetchColumn();

    // 7 Days
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE created_at >= ?");
    $stmt->execute([$time_7d]);
    $status_data['brochures_7d'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochure_products WHERE analyzed_at >= ?");
    $stmt->execute([$time_7d]);
    $status_data['products_7d'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE created_at >= ?");
    $stmt->execute([$time_7d]);
    $status_data['subscribers_7d'] = (int)$stmt->fetchColumn();

    // 30 Days
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE created_at >= ?");
    $stmt->execute([$time_30d]);
    $status_data['brochures_30d'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM brochure_products WHERE analyzed_at >= ?");
    $stmt->execute([$time_30d]);
    $status_data['products_30d'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE created_at >= ?");
    $stmt->execute([$time_30d]);
    $status_data['subscribers_30d'] = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    // DB error logged in status page
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Durumu (Status) - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .pulse-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #22c55e;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
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
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="hepsiburada_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">local_mall</span> Hepsiburada Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span>
                Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span>
                Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span>
                Broşür AI Analizi
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
            <a href="status.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">monitoring</span>
                Sistem Durumu (Status)
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span>
                Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <!-- Header -->
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white">Sistem Durumu &amp; Metrikler</h1>
            <div class="flex items-center gap-4">
                <a href="../" target="_blank" class="flex items-center gap-2 text-sm bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl transition">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Siteyi Görüntüle
                </a>
            </div>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-7xl w-full mx-auto">
            
            <!-- Real-time / Current Status Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-400 text-2xl">sensors</span>
                        <h3 class="font-title text-lg font-bold text-white">Gerçek Zamanlı Durum (Live Status)</h3>
                    </div>
                    <div class="flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-full text-xs font-semibold">
                        <span class="pulse-dot"></span>
                        Sistem Çevrimiçi
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800/80 space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Veritabanı Bağlantısı</span>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <span class="text-lg font-bold text-white"><?= htmlspecialchars($status_data['db_status']) ?></span>
                        </div>
                        <span class="text-xs text-slate-500 block">Sürücü: <?= strtoupper($status_data['db_driver']) ?></span>
                    </div>

                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800/80 space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Veritabanı Boyutu</span>
                        <span class="text-2xl font-black text-white block"><?= htmlspecialchars($status_data['db_size']) ?></span>
                        <span class="text-xs text-slate-500 block">Toplam Tablo Verisi</span>
                    </div>

                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800/80 space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Aktif Broşürler</span>
                        <span class="text-2xl font-black text-white block"><?= $status_data['active_brochures'] ?> <span class="text-slate-500 text-sm font-normal">/ <?= $status_data['total_brochures'] ?></span></span>
                        <span class="text-xs text-slate-500 block">Yayında olan güncel içerikler</span>
                    </div>

                    <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800/80 space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Aktif Scraperlar</span>
                        <span class="text-2xl font-black text-white block"><?= $status_data['active_scrapers'] ?></span>
                        <span class="text-xs text-slate-500 block">Otomasyona bağlı marketler</span>
                    </div>
                </div>

                <!-- Scraper Cron Logs -->
                <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800/80 space-y-3">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-300">
                        <span class="material-symbols-outlined text-red-500 text-lg">schedule</span>
                        Son Scraper / Cron Çalışma Durumu
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        <div class="bg-slate-900 p-3.5 rounded-xl border border-slate-800 flex justify-between">
                            <span class="text-slate-400">Son Çalışma Zamanı:</span>
                            <strong class="text-white"><?= htmlspecialchars($last_run) ?></strong>
                        </div>
                        <div class="bg-slate-900 p-3.5 rounded-xl border border-slate-800 flex justify-between">
                            <span class="text-slate-400">Son Sonuç / Çıktı:</span>
                            <strong class="text-white"><?= htmlspecialchars($last_result) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time intervals grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- 24 Hours Status Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">today</span>
                        <h3 class="font-title text-base font-bold text-white">Son 24 Saat</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Eklenen Broşür</span>
                            <span class="text-lg font-extrabold text-red-500"><?= $status_data['brochures_24h'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">AI ile Analiz Edilen Ürün</span>
                            <span class="text-lg font-extrabold text-emerald-400"><?= $status_data['products_24h'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Bülten Abonesi</span>
                            <span class="text-lg font-extrabold text-blue-400"><?= $status_data['subscribers_24h'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- 1 Week Status Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">date_range</span>
                        <h3 class="font-title text-base font-bold text-white">Son 7 Gün (1 Hafta)</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Eklenen Broşür</span>
                            <span class="text-lg font-extrabold text-red-500"><?= $status_data['brochures_7d'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">AI ile Analiz Edilen Ürün</span>
                            <span class="text-lg font-extrabold text-emerald-400"><?= $status_data['products_7d'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Bülten Abonesi</span>
                            <span class="text-lg font-extrabold text-blue-400"><?= $status_data['subscribers_7d'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- 1 Month Status Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">calendar_month</span>
                        <h3 class="font-title text-base font-bold text-white">Son 30 Gün (1 Ay)</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Eklenen Broşür</span>
                            <span class="text-lg font-extrabold text-red-500"><?= $status_data['brochures_30d'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">AI ile Analiz Edilen Ürün</span>
                            <span class="text-lg font-extrabold text-emerald-400"><?= $status_data['products_30d'] ?></span>
                        </div>
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800/60 flex items-center justify-between">
                            <span class="text-xs text-slate-400 font-semibold">Yeni Bülten Abonesi</span>
                            <span class="text-lg font-extrabold text-blue-400"><?= $status_data['subscribers_30d'] ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Server Diagnostics -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                    <span class="material-symbols-outlined text-red-500">developer_board</span>
                    <h3 class="font-title text-lg font-bold text-white">Sunucu &amp; PHP Konfigürasyonu</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 flex flex-col justify-between space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">PHP Sürümü &amp; Bellek Limiti</span>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>PHP Sürümü:</span>
                                <strong class="text-white"><?= htmlspecialchars($status_data['php_version']) ?></strong>
                            </div>
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Bellek Limiti (Memory Limit):</span>
                                <strong class="text-white"><?= htmlspecialchars($status_data['memory_limit']) ?></strong>
                            </div>
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Max. Çalışma Süresi (Exec Time):</span>
                                <strong class="text-white"><?= htmlspecialchars($status_data['max_execution_time']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 flex flex-col justify-between space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Disk Kullanımı</span>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Boş Alan (Free Space):</span>
                                <strong class="text-white"><?= htmlspecialchars($status_data['disk_free']) ?></strong>
                            </div>
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Toplam Alan (Total Disk):</span>
                                <strong class="text-white"><?= htmlspecialchars($status_data['disk_total']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 flex flex-col justify-between space-y-2">
                        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Uygulama İçi Sayılar</span>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Toplam Ürün Sayısı:</span>
                                <strong class="text-white"><?= number_format($status_data['total_products']) ?></strong>
                            </div>
                            <div class="flex justify-between text-xs text-slate-300">
                                <span>Toplam Bülten Abonesi:</span>
                                <strong class="text-white"><?= number_format($status_data['total_subscribers']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
