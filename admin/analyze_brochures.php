<?php
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

// Fetch brochures with page count and analysis status
$today     = date('Y-m-d');
$brochures = [];
try {
    $brochures_stmt = $pdo->prepare("
        SELECT b.id, b.title, b.start_date, b.end_date,
               m.name AS market_name, m.logo AS market_logo,
               COUNT(DISTINCT bp.id) AS page_count,
               COUNT(DISTINCT CASE WHEN pr.brochure_id IS NOT NULL THEN bp.page_number END) AS analyzed_count
        FROM brochures b
        JOIN markets m ON m.id = b.market_id
        LEFT JOIN brochure_pages bp ON bp.brochure_id = b.id
        LEFT JOIN brochure_products pr ON pr.brochure_id = b.id AND pr.page_number = bp.page_number
        WHERE b.end_date >= ?
        GROUP BY b.id, b.title, b.start_date, b.end_date, m.name, m.logo
        ORDER BY b.created_at DESC
        LIMIT 100
    ");
    $brochures_stmt->execute([$today]);
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
    <title>Broşür Analizi — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .progress-bar { transition: width 0.4s ease; }
        .log-line { padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 12px; }
        .log-ok  { color: #4ade80; }
        .log-err { color: #f87171; }
        .log-inf { color: #93c5fd; }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen">

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="font-title text-2xl font-black text-white">🔍 Broşür AI Analizi</h1>
            <p class="text-slate-400 text-sm mt-1">Gemini Vision API ile ürün tespiti ve fiyat karşılaştırma</p>
        </div>
        <a href="index.php" class="text-slate-400 hover:text-white transition text-sm flex items-center gap-1">
            <span class="material-symbols-outlined text-base">arrow_back</span> Admin Panel
        </a>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="bg-emerald-900/50 border border-emerald-700 text-emerald-300 rounded-xl px-4 py-3 mb-6 text-sm">
            ✅ <?= htmlspecialchars($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_err'])): ?>
        <div class="bg-red-900/50 border border-red-700 text-red-300 rounded-xl px-4 py-3 mb-6 text-sm">
            ❌ <?= htmlspecialchars($_SESSION['flash_err']) ?>
        </div>
        <?php unset($_SESSION['flash_err']); ?>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         Gemini API Key — ALWAYS VISIBLE
    ════════════════════════════════════════════════════════ -->
    <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 mb-8">
        <h2 class="font-title text-lg font-black text-white mb-1 flex items-center gap-2">
            <span class="material-symbols-outlined text-amber-400">key</span>
            Gemini API Anahtarı
        </h2>
        <p class="text-slate-400 text-xs mb-4">
            <a href="https://aistudio.google.com/apikey" target="_blank" class="text-blue-400 underline hover:text-blue-300">Google AI Studio</a>'dan ücretsiz anahtar alın (Gemini 2.0 Flash — günlük 1500 istek bedava).
        </p>

        <?php if ($current_key): ?>
            <div class="bg-emerald-900/30 border border-emerald-700/50 text-emerald-300 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">check_circle</span>
                API anahtarı aktif: <code class="font-mono"><?= str_repeat('•', 24) . substr($current_key, -8) ?></code>
            </div>
        <?php else: ?>
            <div class="bg-amber-900/30 border border-amber-700/50 text-amber-300 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">warning</span>
                Henüz API anahtarı girilmedi. Aşağıya girerek kaydedin.
            </div>
        <?php endif; ?>

        <form method="POST" class="flex gap-3">
            <input type="hidden" name="action" value="save_key">
            <input type="text" name="gemini_api_key"
                   value="<?= htmlspecialchars($current_key) ?>"
                   placeholder="AIzaSy..."
                   autocomplete="off"
                   class="flex-1 bg-slate-700 border border-slate-600 rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-blue-500 transition font-mono">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold px-6 py-2.5 rounded-xl transition text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-base">save</span>
                Kaydet
            </button>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ([
            ['Toplam Sayfa',     $total_pages,    'article',              'blue'],
            ['Analiz Edilen',    $analyzed_pages, 'analytics',            'emerald'],
            ['Tespit Edilen Ürün', $total_products,'inventory_2',         'purple'],
            ['Aktif Alarm',      $total_alerts,   'notifications_active', 'amber'],
        ] as [$label, $val, $icon, $color]): ?>
            <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700">
                <span class="material-symbols-outlined text-<?= $color ?>-400 text-2xl"><?= $icon ?></span>
                <div class="text-2xl font-black text-white mt-2"><?= number_format((int)$val) ?></div>
                <div class="text-slate-400 text-xs mt-0.5"><?= $label ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Brochure List -->
    <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-6">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700">
            <h2 class="font-title text-lg font-black text-white">Aktif Broşürler</h2>
            <?php if ($current_key && !empty($brochures)): ?>
                <button onclick="analyzeAll()" id="analyze-all-btn"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-sm transition flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">auto_awesome</span>
                    Analiz Edilmeyenleri Analiz Et
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($brochures)): ?>
            <div class="px-6 py-12 text-center text-slate-500">
                <span class="material-symbols-outlined text-4xl mb-2 block">find_in_page</span>
                Aktif broşür bulunamadı veya henüz sayfa yüklenmedi.
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-slate-400 text-xs uppercase tracking-wider border-b border-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left">Market / Broşür</th>
                        <th class="px-6 py-3 text-center">Sayfa</th>
                        <th class="px-6 py-3 text-center">Analiz</th>
                        <th class="px-6 py-3 text-center">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    <?php foreach ($brochures as $b): ?>
                        <?php
                        $analyzed = (int)$b['analyzed_count'];
                        $total    = (int)$b['page_count'];
                        $pct      = $total > 0 ? round($analyzed / $total * 100) : 0;
                        ?>
                        <tr class="hover:bg-slate-750/50 transition" id="brow-<?= $b['id'] ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($b['market_logo']): ?>
                                        <img src="../uploads/markets/<?= htmlspecialchars($b['market_logo']) ?>"
                                             class="w-8 h-8 rounded-lg object-contain bg-white p-0.5 shrink-0" alt="">
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-bold text-white text-sm"><?= htmlspecialchars($b['market_name']) ?></div>
                                        <div class="text-slate-400 text-xs truncate max-w-xs"><?= htmlspecialchars($b['title']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-300"><?= $total ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 justify-center">
                                    <div class="w-24 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="progress-bar h-full rounded-full <?= $pct == 100 ? 'bg-emerald-500' : 'bg-blue-500' ?>"
                                             style="width: <?= $pct ?>%"
                                             id="prog-<?= $b['id'] ?>"></div>
                                    </div>
                                    <span class="text-xs text-slate-400" id="prog-txt-<?= $b['id'] ?>"><?= $analyzed ?>/<?= $total ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($current_key): ?>
                                    <?php if ($analyzed < $total || $total === 0): ?>
                                        <button onclick="analyzeBrochure(<?= $b['id'] ?>, <?= max(1, $total) ?>)"
                                                id="btn-<?= $b['id'] ?>"
                                                class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition">
                                            Analiz Et
                                        </button>
                                    <?php else: ?>
                                        <span class="text-emerald-400 text-xs font-bold">✅ Tamamlandı</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-600 text-xs">API key gerekli</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Log output -->
    <div id="log-panel" class="hidden bg-black rounded-2xl p-5 border border-slate-700 font-mono overflow-y-auto max-h-72">
        <div class="text-slate-500 text-xs mb-2 flex items-center justify-between">
            <span>📋 Analiz Logu</span>
            <button onclick="document.getElementById('log-panel').classList.add('hidden')" class="text-slate-600 hover:text-slate-400 text-xs">Kapat</button>
        </div>
        <div id="log-content"></div>
    </div>

</div>

<script>
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

async function analyzePage(brochureId, pageNum, totalPages) {
    log(`  → Sayfa ${pageNum}/${totalPages} analiz ediliyor...`);
    try {
        const r    = await fetch(`../api/analyze_page.php?brochure_id=${brochureId}&page_number=${pageNum}`);
        const data = await r.json();
        if (data.success) {
            log(`    ✅ ${data.count ?? 0} ürün tespit edildi${data.cached ? ' (önbellekten)' : ''}`, 'ok');
            return data.count ?? 0;
        } else {
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
    if (btn) { btn.disabled = true; btn.textContent = 'Analiz ediliyor...'; }
    log(`\n📋 Broşür #${brochureId} analiz başladı (${totalPages} sayfa)`, 'inf');

    let done = 0;
    for (let p = 1; p <= totalPages; p++) {
        await analyzePage(brochureId, p, totalPages);
        done++;
        const pct = Math.round(done / totalPages * 100);
        if (progBar) progBar.style.width = pct + '%';
        if (progTxt) progTxt.textContent = `${done}/${totalPages}`;
        await new Promise(res => setTimeout(res, 900)); // rate limit buffer
    }

    log(`✅ Broşür #${brochureId} tamamlandı!`, 'ok');
    if (btn) {
        btn.textContent = '✅ Tamamlandı';
        btn.className   = btn.className.replace(/bg-blue-\d+\s*hover:bg-blue-\d+/, 'bg-emerald-700 cursor-default');
        btn.disabled    = true;
    }
    if (progBar) { progBar.classList.remove('bg-blue-500'); progBar.classList.add('bg-emerald-500'); }
}

async function analyzeAll() {
    const allBtn = document.getElementById('analyze-all-btn');
    if (allBtn) { allBtn.disabled = true; allBtn.textContent = 'Analiz ediliyor...'; }

    const rows = document.querySelectorAll('button[id^="btn-"]');
    for (const btn of rows) {
        if (btn.disabled) continue;
        const id      = btn.id.replace('btn-', '');
        const progTxt = document.getElementById(`prog-txt-${id}`);
        if (!progTxt) continue;
        const [done, total] = progTxt.textContent.split('/').map(Number);
        if (done < total) {
            await analyzeBrochure(parseInt(id), total);
            await new Promise(res => setTimeout(res, 2000));
        }
    }

    if (allBtn) { allBtn.disabled = false; allBtn.textContent = '✅ Tümü Tamamlandı'; }
}
</script>
</body>
</html>
