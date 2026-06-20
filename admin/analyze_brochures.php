<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * admin/analyze_brochures.php
 * Broşür sayfalarını Gemini Vision API ile analiz etmek için admin paneli.
 */
session_start();
require dirname(__DIR__) . '/config.php';

// Auth check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php'); exit;
}

$is_mysql = ($active_db_driver === 'mysql');

// Ensure gemini_api_key row exists in settings (migration safety for existing DBs)
try {
    $check = $pdo->query("SELECT COUNT(*) FROM settings WHERE key_name = 'gemini_api_key'");
    if ($check && (int)$check->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO settings (key_name, value_text) VALUES ('gemini_api_key', '')");
    }
} catch (Exception $e) { /* ignore */ }

// Save Gemini API key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_key') {
    $key = trim((string)($_POST['gemini_api_key'] ?? ''));
    try {
        if ($is_mysql) {
            $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES ('gemini_api_key', ?)
                           ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)")
                ->execute([$key]);
        } else {
            $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value_text) VALUES ('gemini_api_key', ?)")
                ->execute([$key]);
        }
        $_SESSION['flash'] = 'Gemini API anahtarı kaydedildi.';
    } catch (Exception $e) {
        $_SESSION['flash_err'] = 'Kayıt hatası: ' . $e->getMessage();
    }
    header('Location: analyze_brochures.php'); exit;
}

// Fetch current key
try {
    $key_stmt    = $pdo->query("SELECT value_text FROM settings WHERE key_name = 'gemini_api_key'");
    $current_key = $key_stmt ? trim((string)($key_stmt->fetchColumn() ?: '')) : '';
} catch (Exception $e) {
    $current_key = '';
}

// Fetch stats (safe fallbacks)
$total_pages    = 0;
$analyzed_pages = 0;
$total_products = 0;
$total_alerts   = 0;
try { $total_pages    = (int)$pdo->query("SELECT COUNT(*) FROM brochure_pages")->fetchColumn(); }    catch(Exception $e){}
try { $analyzed_pages = (int)$pdo->query("SELECT COUNT(DISTINCT brochure_id * 10000 + page_number) FROM brochure_products")->fetchColumn(); } catch(Exception $e){}
try { $total_products = (int)$pdo->query("SELECT COUNT(*) FROM brochure_products")->fetchColumn(); } catch(Exception $e){}
try { $total_alerts   = (int)$pdo->query("SELECT COUNT(*) FROM price_alerts WHERE is_active = 1")->fetchColumn(); } catch(Exception $e){}

// Fetch all markets for filter dropdown
$markets_list = [];
try {
    $markets_list = $pdo->query("SELECT id, name FROM markets ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch brochures with page count, analysis status and analyzed_at date
$filter = $_GET['filter'] ?? 'active';
$market_filter_id = isset($_GET['market_id']) && $_GET['market_id'] !== 'all' ? (int)$_GET['market_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$today = date('Y-m-d');
$where_clauses = [];
$params = [];

if ($filter === 'active') {
    $where_clauses[] = "b.end_date >= :today";
    $params[':today'] = $today;
} elseif ($filter === 'expired') {
    $where_clauses[] = "b.end_date < :today";
    $params[':today'] = $today;
}

if ($market_filter_id !== null) {
    $where_clauses[] = "b.market_id = :market_id";
    $params[':market_id'] = $market_filter_id;
}

if ($search !== '') {
    $where_clauses[] = "(b.title LIKE :search OR m.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

$brochures = [];
try {
    $brochures_stmt = $pdo->prepare("
        SELECT b.id, b.title, b.start_date, b.end_date, b.analyzed_at,
               m.name AS market_name, m.logo AS market_logo,
               COUNT(DISTINCT bp.id) AS page_count,
               COUNT(DISTINCT CASE WHEN pr.brochure_id IS NOT NULL THEN bp.page_number END) AS analyzed_count
        FROM brochures b
        JOIN markets m ON m.id = b.market_id
        LEFT JOIN brochure_pages bp ON bp.brochure_id = b.id
        LEFT JOIN brochure_products pr ON pr.brochure_id = b.id AND pr.page_number = bp.page_number
        $where_sql
        GROUP BY b.id, b.title, b.start_date, b.end_date, b.analyzed_at, b.created_at, m.name, m.logo
        ORDER BY b.created_at DESC
        LIMIT 100
    ");
    $brochures_stmt->execute($params);
    $brochures = $brochures_stmt->fetchAll();
} catch (Exception $e) {
    $brochures = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broşür AI Analizi - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .progress-bar { transition: width 0.4s ease; }
        .log-line { padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 12px; }
        .log-ok  { color: #4ade80; }
        .log-err { color: #f87171; }
        .log-inf { color: #93c5fd; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="p-6 border-b border-slate-800">
            <a href="index.php" class="font-title text-xl font-black text-white flex items-center gap-2">
                <?php if (file_exists('../uploads/logo.png')): ?>
                    <img src="../uploads/logo.png" alt="logo" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <span class="text-red-500 material-symbols-outlined">dashboard</span>
                    marketisleri<span class="text-red-500">.panel</span>
                <?php endif; ?>
            </a>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">space_dashboard</span> Dashboard
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span> Marketler
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span> Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span> Sihirli Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span> Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span> Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">explore</span> Broşür AI Analizi
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">article</span> Blog Yazıları
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span> Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">settings</span> Ayarlar
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span> Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <!-- Header -->
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white">🔍 Broşür AI Analiz Paneli</h1>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-7xl w-full mx-auto">

            <?php if (isset($_SESSION['flash'])): ?>
                <div class="bg-emerald-900/30 border border-emerald-800 text-emerald-400 rounded-2xl px-5 py-3 text-sm">
                    ✅ <?= htmlspecialchars($_SESSION['flash']) ?>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash_err'])): ?>
                <div class="bg-red-900/30 border border-red-800 text-red-400 rounded-2xl px-5 py-3 text-sm">
                    ❌ <?= htmlspecialchars($_SESSION['flash_err']) ?>
                </div>
                <?php unset($_SESSION['flash_err']); ?>
            <?php endif; ?>

            <!-- API Key Section -->
            <div class="bg-slate-900 rounded-3xl p-6 border border-slate-800 shadow-xl space-y-4">
                <div>
                    <h2 class="font-title text-lg font-black text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">key</span>
                        Gemini API Anahtarı
                    </h2>
                    <p class="text-slate-400 text-xs mt-1">
                        <a href="https://aistudio.google.com/apikey" target="_blank" class="text-red-500 hover:text-red-400 underline font-semibold">Google AI Studio</a>'dan ücretsiz anahtar alabilirsiniz. (Dakikada 15 istek sınırı vardır).
                    </p>
                </div>

                <?php if ($current_key): ?>
                    <div class="bg-emerald-900/10 border border-emerald-900/40 text-emerald-400 rounded-2xl px-4 py-3 text-xs flex items-center gap-2 max-w-md">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        Aktif API Anahtarı: <code class="font-mono"><?= str_repeat('•', 24) . substr($current_key, -8) ?></code>
                    </div>
                <?php else: ?>
                    <div class="bg-amber-900/10 border border-amber-900/40 text-amber-400 rounded-2xl px-4 py-3 text-xs flex items-center gap-2 max-w-md">
                        <span class="material-symbols-outlined text-base">warning</span>
                        Henüz API anahtarı kaydedilmedi. Lütfen sisteme girin.
                    </div>
                <?php endif; ?>

                <form method="POST" class="flex gap-3 max-w-2xl">
                    <input type="hidden" name="action" value="save_key">
                    <input type="text" name="gemini_api_key"
                           value="<?= htmlspecialchars($current_key) ?>"
                           placeholder="AIzaSy..."
                           autocomplete="off"
                           class="flex-1 bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 text-sm outline-none transition font-mono">
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl transition text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Kaydet
                    </button>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ([
                    ['Toplam Sayfa',     $total_pages,    'article',              'indigo'],
                    ['Analiz Edilen',    $analyzed_pages, 'analytics',            'emerald'],
                    ['Tespit Edilen Ürün', $total_products,'inventory_2',         'purple'],
                    ['Aktif Alarm',      $total_alerts,   'notifications_active', 'amber'],
                ] as [$label, $val, $icon, $color]): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 flex items-center justify-between shadow-xl">
                        <div>
                            <p class="text-sm text-slate-400 font-semibold mb-1"><?= $label ?></p>
                            <h3 class="text-2xl font-black text-white"><?= number_format((int)$val) ?></h3>
                        </div>
                        <span class="w-12 h-12 rounded-2xl bg-<?= $color ?>-500/10 text-<?= $color ?>-400 flex items-center justify-center material-symbols-outlined text-2xl">
                            <?= $icon ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Brochure List Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="flex flex-col sm:flex-row items-center justify-between px-6 py-5 border-b border-slate-800 gap-4">
                    <h2 class="font-title text-lg font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-500">menu_book</span>
                        <?= ($filter === 'all' ? 'Tüm Broşürler' : ($filter === 'expired' ? 'Süresi Geçmiş Broşürler' : 'Aktif Broşürler')) ?>
                    </h2>
                    <?php if ($current_key && !empty($brochures)): ?>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="analyzeAll(true)" id="analyze-un-btn"
                                    class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition flex items-center gap-2 shadow-lg shadow-emerald-950/20">
                                <span class="material-symbols-outlined text-sm">auto_awesome</span>
                                Analiz Edilmeyenleri Analiz Et
                            </button>
                            <button onclick="analyzeAll(false)" id="analyze-all-btn"
                                    class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition flex items-center gap-2 shadow-lg shadow-blue-950/20">
                                <span class="material-symbols-outlined text-sm">pageview</span>
                                Tümünü Analiz Et (Zorla)
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Filter Form -->
                <form method="GET" class="px-6 py-4 bg-slate-900/40 border-b border-slate-800/80 flex flex-wrap gap-4 items-center">
                    <div class="flex flex-col min-w-[150px]">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Market</label>
                        <select name="market_id" class="bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-red-500 transition">
                            <option value="all">Tüm Marketler</option>
                            <?php foreach ($markets_list as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $market_filter_id === (int)$m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col min-w-[150px]">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Durum / Tarih</label>
                        <select name="filter" class="bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-red-500 transition">
                            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Aktif Broşürler</option>
                            <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Süresi Geçmiş Broşürler</option>
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tüm Broşürler</option>
                        </select>
                    </div>

                    <div class="flex flex-col flex-1 min-w-[200px]">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Arama</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Broşür adı veya market..." 
                               class="bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-red-500 transition">
                    </div>

                    <div class="flex items-end self-stretch pb-0.5">
                        <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-bold px-4 py-2 rounded-xl text-xs transition flex items-center gap-1.5 h-[34px]">
                            <span class="material-symbols-outlined text-sm">filter_list</span>
                            Filtrele
                        </button>
                        <?php if ($market_filter_id !== null || $filter !== 'active' || $search !== ''): ?>
                            <a href="analyze_brochures.php" class="ml-2 text-slate-400 hover:text-slate-200 text-xs flex items-center gap-1 h-[34px] px-2">
                                Sıfırla
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($brochures)): ?>
                    <div class="px-6 py-16 text-center text-slate-500">
                        <span class="material-symbols-outlined text-5xl mb-2 block text-slate-600">find_in_page</span>
                        Aradığınız kriterlere uygun broşür bulunamadı.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-850 text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-900/50">
                                    <th class="px-6 py-4">Market / Broşür</th>
                                    <th class="px-6 py-4 text-center">Sayfa Sayısı</th>
                                    <th class="px-6 py-4 text-center">İlerleme</th>
                                    <th class="px-6 py-4 text-center">Durum</th>
                                    <th class="px-6 py-4 text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-sm">
                                <?php foreach ($brochures as $b): ?>
                                    <?php
                                    $analyzed = (int)$b['analyzed_count'];
                                    $total    = (int)$b['page_count'];
                                    $pct      = $total > 0 ? round($analyzed / $total * 100) : 0;
                                    ?>
                                    <tr class="hover:bg-slate-850/30 transition" id="brow-<?= $b['id'] ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <?php if ($b['market_logo']): ?>
                                                    <img src="../uploads/markets/<?= htmlspecialchars($b['market_logo']) ?>"
                                                         class="w-8 h-8 rounded-lg object-contain bg-white p-0.5 shrink-0" alt="">
                                                <?php endif; ?>
                                                <div>
                                                    <div class="font-bold text-white"><?= htmlspecialchars($b['market_name']) ?></div>
                                                    <div class="text-slate-400 text-xs truncate max-w-sm" title="<?= htmlspecialchars($b['title']) ?>"><?= htmlspecialchars($b['title']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center font-semibold text-slate-300"><?= $total ?></td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 justify-center">
                                                <div class="w-24 h-1.5 bg-slate-800 rounded-full overflow-hidden shrink-0">
                                                    <div class="progress-bar h-full rounded-full <?= $pct == 100 ? 'bg-emerald-500' : 'bg-red-500' ?>"
                                                         style="width: <?= $pct ?>%"
                                                         id="prog-<?= $b['id'] ?>"></div>
                                                </div>
                                                <span class="text-xs font-bold text-slate-400" id="prog-txt-<?= $b['id'] ?>"><?= $analyzed ?>/<?= $total ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center" id="status-cell-<?= $b['id'] ?>">
                                            <?php if ($b['analyzed_at']): ?>
                                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/25" title="Tamamlanma Tarihi">
                                                    <span class="material-symbols-outlined text-[12px] font-black">check_circle</span>
                                                    ✔ Başarılı (<?= date('d.m.Y H:i', strtotime($b['analyzed_at'])) ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                                    <span class="material-symbols-outlined text-[12px]">pending</span>
                                                    Analiz Edilmedi
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($current_key): ?>
                                                <button onclick="analyzeBrochure(<?= $b['id'] ?>, <?= max(1, $total) ?>)"
                                                        id="btn-<?= $b['id'] ?>"
                                                        class="bg-red-600 hover:bg-red-500 disabled:bg-slate-800 disabled:text-slate-600 text-white text-xs font-bold px-3 py-2 rounded-xl transition">
                                                    Analiz Et
                                                </button>
                                            <?php else: ?>
                                                <span class="text-slate-600 text-xs font-bold">API Key Gerekli</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Log Panel -->
            <div id="log-panel" class="hidden bg-slate-900 border border-slate-800 rounded-3xl p-6 font-mono text-xs text-slate-300 shadow-xl space-y-4">
                <div class="text-slate-500 text-xs font-bold flex items-center justify-between border-b border-slate-800 pb-3">
                    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-red-500 text-base">terminal</span> 📋 Analiz Terminal Logu</span>
                    <button onclick="document.getElementById('log-panel').classList.add('hidden')" class="text-slate-500 hover:text-slate-300 text-xs font-bold flex items-center gap-1"><span class="material-symbols-outlined text-sm">close</span> Kapat</button>
                </div>
                <div id="log-content" class="max-h-72 overflow-y-auto space-y-2"></div>
            </div>

        </div>
    </main>

    <script>
    const SITE_BASE = '<?= rtrim($site_url, '/') ?>';
    let isRunning = false;

    function log(msg, type = 'inf') {
        const panel   = document.getElementById('log-panel');
        const content = document.getElementById('log-content');
        panel.classList.remove('hidden');
        const line = document.createElement('div');
        line.className = 'log-line log-' + type;
        line.textContent = msg;
        content.appendChild(line);
        content.scrollTop = content.scrollHeight;
    }

    async function analyzePage(brochureId, pageNum, totalPages, retryCount = 0) {
        log(`  → Sayfa ${pageNum}/${totalPages} analiz ediliyor...`);
        try {
            const r = await fetch(`${SITE_BASE}/api/analyze_page.php?brochure_id=${brochureId}&page_number=${pageNum}`);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                const txt = await r.text();
                throw new Error(`Sunucu JSON döndürmedi (HTTP ${r.status}). Yanıt: ${txt.substring(0,120)}`);
            }
            const data = await r.json();
            if (data.success) {
                log(`    ✅ ${data.count ?? 0} ürün tespit edildi${data.cached ? ' (önbellekten)' : ''}`, 'ok');
                return data.count ?? 0;
            } else {
                // Check for 429 Rate Limit/Quota error
                const errStr = String(data.error);
                if ((errStr.includes('429') || errStr.toLowerCase().includes('quota') || errStr.toLowerCase().includes('limit')) && retryCount < 3) {
                    const waitTime = (retryCount + 1) * 6000; // 6s, 12s, 18s
                    log(`    ⚠️ Hız limiti/kota aşıldı. ${waitTime / 1000} saniye bekleniyor ve tekrar denenecek (Deneme ${retryCount + 1}/3)...`, 'inf');
                    await new Promise(res => setTimeout(res, waitTime));
                    return await analyzePage(brochureId, pageNum, totalPages, retryCount + 1);
                }
                log(`    ❌ ${data.error}`, 'err');
                return 0;
            }
        } catch(e) {
            log(`    ❌ İstek hatası: ${e.message}`, 'err');
            return 0;
        }
    }

    async function analyzeBrochure(brochureId, totalPages) {
        const btn     = document.getElementById(`btn-${brochureId}`);
        const progBar = document.getElementById(`prog-${brochureId}`);
        const progTxt = document.getElementById(`prog-txt-${brochureId}`);
        const statusEl = document.getElementById(`status-cell-${brochureId}`);
        
        if (btn) { btn.disabled = true; btn.textContent = 'Çalışıyor...'; }
        log(`\n📋 Broşür #${brochureId} analiz başladı (${totalPages} sayfa)`, 'inf');

        let done = 0;
        let successCount = 0;
        for (let p = 1; p <= totalPages; p++) {
            const count = await analyzePage(brochureId, p, totalPages);
            done++;
            successCount += count;
            const pct = Math.round(done / totalPages * 100);
            if (progBar) progBar.style.width = pct + '%';
            if (progTxt) progTxt.textContent = `${done}/${totalPages}`;
            await new Promise(res => setTimeout(res, 4100)); // rate limit buffer (min 4s for free tier 15 RPM)
        }

        log(`✅ Broşür #${brochureId} tamamlandı!`, 'ok');
        
        // Update the status cell on UI dynamically after completion
        if (statusEl) {
            const now = new Date();
            const dateStr = now.toLocaleDateString('tr-TR') + ' ' + now.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'});
            statusEl.innerHTML = `
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/25">
                    <span class="material-symbols-outlined text-[12px] font-black">check_circle</span>
                    ✔ Başarılı (${dateStr})
                </span>`;
        }
        
        if (btn) {
            btn.textContent = 'Analiz Et';
            btn.disabled    = false;
        }
        if (progBar) { 
            progBar.classList.remove('bg-red-500'); 
            progBar.classList.add('bg-emerald-500'); 
        }
    }

    async function analyzeAll(onlyUnanalyzed = false) {
        if (isRunning) return;
        isRunning = true;
        
        const allBtn = document.getElementById('analyze-all-btn');
        const unBtn  = document.getElementById('analyze-un-btn');
        
        // Disable bulk buttons and other buttons
        if (allBtn) allBtn.disabled = true;
        if (unBtn) unBtn.disabled = true;
        if (onlyUnanalyzed && unBtn) unBtn.textContent = 'Çalışıyor...';
        if (!onlyUnanalyzed && allBtn) allBtn.textContent = 'Çalışıyor...';

        const rows = document.querySelectorAll('button[id^="btn-"]');
        for (const btn of rows) {
            const id = btn.id.replace('btn-', '');
            const progTxt = document.getElementById(`prog-txt-${id}`);
            const statusCell = document.getElementById(`status-cell-${id}`);
            if (!progTxt) continue;
            
            const [done, total] = progTxt.textContent.split('/').map(Number);
            const isCompleted = statusCell && statusCell.innerText.includes('✔ Başarılı');

            // If only unanalyzed, skip if fully complete
            if (onlyUnanalyzed && isCompleted) {
                continue;
            }
            
            // Disable individual button
            btn.disabled = true;
            await analyzeBrochure(parseInt(id), total);
            await new Promise(res => setTimeout(res, 2000));
        }

        // Restore bulk buttons
        if (allBtn) { allBtn.disabled = false; allBtn.textContent = 'Tümünü Analiz Et (Zorla)'; }
        if (unBtn) { unBtn.disabled = false; unBtn.textContent = 'Analiz Edilmeyenleri Analiz Et'; }
        isRunning = false;
    }
    </script>
</body>
</html>
