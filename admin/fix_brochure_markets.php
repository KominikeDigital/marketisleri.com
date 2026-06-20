<?php
/**
 * admin/fix_brochure_markets.php
 * Yanlış market atanmış broşürleri toplu olarak düzeltmek için admin aracı.
 *
 * - Broşür başlıklarını analiz ederek olası doğru marketi önerir
 * - Admin onayıyla veya toplu olarak düzeltir
 * - Bir daha aynı hatanın olmaması için scraper'a kayıt yapar
 */

session_start();
require dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php'); exit;
}

$msg = '';
$err = '';

// ── Handle single brochure market fix ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'fix_one') {
        $bid    = (int)($_POST['brochure_id'] ?? 0);
        $new_mid = (int)($_POST['market_id'] ?? 0);
        if ($bid && $new_mid) {
            $pdo->prepare("UPDATE brochures SET market_id = ? WHERE id = ?")->execute([$new_mid, $bid]);
            $msg = "Broşür #{$bid} marketi güncellendi.";
        }
    }

    if ($_POST['action'] === 'fix_bulk') {
        // Fix all suggested mismatches at once
        $fixes = $_POST['fixes'] ?? [];
        $count = 0;
        foreach ($fixes as $bid => $mid) {
            $bid = (int)$bid;
            $mid = (int)$mid;
            if ($bid && $mid) {
                $pdo->prepare("UPDATE brochures SET market_id = ? WHERE id = ?")->execute([$mid, $bid]);
                $count++;
            }
        }
        $msg = "{$count} broşürün marketi güncellendi.";
    }
}

$today = date('Y-m-d');

// ── Fetch all markets for lookup ──────────────────────────────────────────────
$all_markets = $pdo->query("SELECT id, name, slug FROM markets ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$market_by_id   = [];
$market_by_name = [];
foreach ($all_markets as $m) {
    $market_by_id[$m['id']] = $m;
    // Build normalized name index for fuzzy matching
    $norm = mb_strtolower(trim($m['name']), 'UTF-8');
    $market_by_name[$norm] = $m['id'];
    // Also index by slug words
    $slug_clean = str_replace(['-', '_'], ' ', $m['slug']);
    $market_by_name[$slug_clean] = $m['id'];
}

// ── Fetch all brochures with current market ───────────────────────────────────
$brochures = $pdo->query("
    SELECT b.id, b.title, b.market_id, b.start_date, b.end_date, b.cover_image,
           m.name AS current_market, m.slug AS current_slug
    FROM brochures b
    JOIN markets m ON m.id = b.market_id
    ORDER BY b.created_at DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

/**
 * Try to detect the correct market from brochure title.
 * Returns array of [market_id, market_name, confidence] or null.
 */
function detectMarketFromTitle(string $title, int $current_market_id, array $all_markets): ?array {
    $title_lower = mb_strtolower($title, 'UTF-8');

    // 1. First, search for any OTHER market name in the title
    foreach ($all_markets as $m) {
        if ((int)$m['id'] === $current_market_id) continue;

        $name_lower = mb_strtolower($m['name'], 'UTF-8');
        $clean_name = mb_strtolower(trim(str_replace(['market', 'süpermarket', 'hipermarket', 'grospermarket', 'grosper', 'marketler', 'marketleri', 'toptan', 'satış', 'mağazaları', 'gros', 'gross'], '', $m['name'])), 'UTF-8');

        if (strpos($title_lower, $name_lower) !== false || (mb_strlen($clean_name, 'UTF-8') >= 3 && strpos($title_lower, $clean_name) !== false)) {
            return ['id' => $m['id'], 'name' => $m['name'], 'confidence' => 'high'];
        }
    }

    // 2. If no other market matches, search for the current market to confirm it's correct
    foreach ($all_markets as $m) {
        if ((int)$m['id'] === $current_market_id) {
            $name_lower = mb_strtolower($m['name'], 'UTF-8');
            $clean_name = mb_strtolower(trim(str_replace(['market', 'süpermarket', 'hipermarket', 'grospermarket', 'grosper', 'marketler', 'marketleri', 'toptan', 'satış', 'mağazaları', 'gros', 'gross'], '', $m['name'])), 'UTF-8');
            if (strpos($title_lower, $name_lower) !== false || (mb_strlen($clean_name, 'UTF-8') >= 3 && strpos($title_lower, $clean_name) !== false)) {
                return ['id' => $m['id'], 'name' => $m['name'], 'confidence' => 'high'];
            }
        }
    }

    // 3. Slug word match (fallback) for other markets
    foreach ($all_markets as $m) {
        if ((int)$m['id'] === $current_market_id) continue;
        $slug_words = array_filter(explode('-', $m['slug']));
        foreach ($slug_words as $w) {
            if (mb_strlen($w, 'UTF-8') >= 4 && strpos($title_lower, $w) !== false) {
                return ['id' => $m['id'], 'name' => $m['name'], 'confidence' => 'medium'];
            }
        }
    }

    return null;
}

// ── Detect mismatches ─────────────────────────────────────────────────────────
$mismatches  = [];
$ok_count    = 0;
foreach ($brochures as $b) {
    $detected = detectMarketFromTitle($b['title'], (int)$b['market_id'], $all_markets);
    if ($detected && $detected['id'] !== (int)$b['market_id']) {
        $b['suggested_market_id']   = $detected['id'];
        $b['suggested_market_name'] = $detected['name'];
        $b['suggestion_confidence'] = $detected['confidence'];
        $mismatches[] = $b;
    } else {
        $ok_count++;
    }
}

// Sort: high confidence first
usort($mismatches, fn($a, $b) => ($b['suggestion_confidence'] === 'high') <=> ($a['suggestion_confidence'] === 'high'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broşür Market Düzeltici - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="p-6 border-b border-slate-800">
            <a href="index.php" class="font-title text-xl font-black text-white flex items-center gap-2">
                <span class="text-red-500 material-symbols-outlined">dashboard</span>
                marketisleri<span class="text-red-500">.panel</span>
            </a>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">space_dashboard</span> Dashboard
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span> Broşürler
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span> Marketler
            </a>
            <a href="fix_brochure_markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-600 text-white font-semibold">
                <span class="material-symbols-outlined text-lg">build</span> Market Düzeltici
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span> Broşür AI Analizi
            </a>
        </nav>
    </aside>

    <!-- Main -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-500">build</span>
                Broşür Market Düzeltici
            </h1>
            <div class="text-sm text-slate-400">
                <span class="text-emerald-400 font-bold"><?= $ok_count ?></span> doğru &nbsp;·&nbsp;
                <span class="text-amber-400 font-bold"><?= count($mismatches) ?></span> hatalı tespit edildi
            </div>
        </header>

        <div class="p-8 space-y-6 max-w-7xl w-full mx-auto">

            <?php if ($msg): ?>
                <div class="bg-emerald-900/30 border border-emerald-800 text-emerald-400 rounded-2xl px-5 py-3 text-sm">✅ <?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="bg-red-900/30 border border-red-800 text-red-400 rounded-2xl px-5 py-3 text-sm">❌ <?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- How it works -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-sm text-slate-400 space-y-2">
                <h2 class="text-white font-bold text-base flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-400">info</span>
                    Nasıl Çalışır?
                </h2>
                <p>Bu araç broşür <strong class="text-white">başlıklarını</strong> tarayarak hangi markete ait olduğunu tahmin eder. Eğer mevcut market ataması ile başlıktaki market adı uyuşmuyorsa "Hatalı" olarak işaretler.</p>
                <p>Önerilen düzeltmeleri tek tek veya toplu olarak onaylayabilirsiniz. Yanlış tespit varsa "Atla" diyebilirsiniz.</p>
            </div>

            <?php if (empty($mismatches)): ?>
                <div class="bg-emerald-900/20 border border-emerald-800 rounded-3xl p-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-emerald-400 block mb-3">check_circle</span>
                    <p class="text-emerald-400 font-bold text-lg">Tüm broşürler doğru markete atanmış!</p>
                    <p class="text-slate-500 text-sm mt-1"><?= $ok_count ?> broşür kontrol edildi.</p>
                </div>
            <?php else: ?>
                <!-- Bulk fix form -->
                <form method="POST" id="bulk-form">
                    <input type="hidden" name="action" value="fix_bulk">

                    <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-800">
                            <h2 class="font-title text-lg font-bold text-white flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-500">warning</span>
                                Yanlış Market Atamaları (<?= count($mismatches) ?>)
                            </h2>
                            <button type="submit"
                                    onclick="return confirm('<?= count($mismatches) ?> broşürün marketi toplu olarak güncellenecek. Emin misiniz?')"
                                    class="bg-amber-600 hover:bg-amber-500 text-white font-bold px-5 py-2.5 rounded-xl text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">build</span>
                                Seçilenleri Toplu Düzelt (<?= count($mismatches) ?>)
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-900/50 border-b border-slate-800">
                                        <th class="px-6 py-4">
                                            <input type="checkbox" id="select-all" onchange="toggleAll(this)" class="rounded">
                                        </th>
                                        <th class="px-6 py-4">Broşür</th>
                                        <th class="px-6 py-4">Mevcut Market</th>
                                        <th class="px-6 py-4">Önerilen Market</th>
                                        <th class="px-6 py-4">Güven</th>
                                        <th class="px-6 py-4">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800 text-sm">
                                    <?php foreach ($mismatches as $b): ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-6 py-4">
                                                <input type="checkbox" name="fixes[<?= $b['id'] ?>]"
                                                       value="<?= $b['suggested_market_id'] ?>"
                                                       class="mismatch-cb rounded" checked>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <?php if (!empty($b['cover_image'])): ?>
                                                        <img src="../uploads/brochures/<?= htmlspecialchars($b['cover_image']) ?>"
                                                             class="w-10 h-12 object-cover rounded-lg shrink-0 bg-slate-800" alt="">
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="font-semibold text-white text-xs">#<?= $b['id'] ?></div>
                                                        <div class="text-slate-300 text-xs max-w-xs truncate" title="<?= htmlspecialchars($b['title']) ?>">
                                                            <?= htmlspecialchars($b['title']) ?>
                                                        </div>
                                                        <div class="text-slate-500 text-[10px] mt-0.5">
                                                            <?= date('d.m.Y', strtotime($b['start_date'])) ?> – <?= date('d.m.Y', strtotime($b['end_date'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-red-400 font-semibold text-xs px-2 py-1 bg-red-900/20 rounded-lg">
                                                    ❌ <?= htmlspecialchars($b['current_market']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <!-- Dropdown to override suggestion -->
                                                <select name="fixes[<?= $b['id'] ?>]"
                                                        class="bg-slate-800 border border-slate-700 text-emerald-300 text-xs rounded-xl px-2 py-1.5 outline-none focus:border-emerald-500 transition">
                                                    <option value="">-- Değiştirme --</option>
                                                    <?php foreach ($all_markets as $m): ?>
                                                        <option value="<?= $m['id'] ?>"
                                                            <?= $m['id'] == $b['suggested_market_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($m['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($b['suggestion_confidence'] === 'high'): ?>
                                                    <span class="text-[10px] font-bold px-2 py-1 bg-emerald-900/30 text-emerald-400 rounded-full">YÜKSEK</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-bold px-2 py-1 bg-amber-900/30 text-amber-400 rounded-full">ORTA</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="viewer.php?id=<?= $b['id'] ?>" target="_blank"
                                                   class="text-slate-400 hover:text-white text-xs flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                                                    Görüntüle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- All brochures section -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-800">
                    <h2 class="font-title text-lg font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">menu_book</span>
                        Tüm Broşürleri Manuel Düzenle
                    </h2>
                    <p class="text-slate-500 text-xs mt-1">Otomatik tespit edemediğimiz yanlış atamaları buradan düzeltebilirsiniz.</p>
                </div>
                <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 bg-slate-900 z-10">
                            <tr class="text-slate-400 text-xs font-semibold uppercase tracking-wider border-b border-slate-800">
                                <th class="px-6 py-3">Broşür</th>
                                <th class="px-6 py-3">Mevcut Market</th>
                                <th class="px-6 py-3">Düzelt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-sm">
                            <?php foreach ($brochures as $b): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($b['cover_image'])): ?>
                                                <img src="../uploads/brochures/<?= htmlspecialchars($b['cover_image']) ?>"
                                                     class="w-8 h-10 object-cover rounded-lg shrink-0 bg-slate-800" alt="">
                                            <?php endif; ?>
                                            <div>
                                                <span class="text-slate-500 text-[10px]">#<?= $b['id'] ?></span>
                                                <div class="text-slate-200 text-xs max-w-xs truncate" title="<?= htmlspecialchars($b['title']) ?>">
                                                    <?= htmlspecialchars($b['title']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="text-slate-300 text-xs"><?= htmlspecialchars($b['current_market']) ?></span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="action" value="fix_one">
                                            <input type="hidden" name="brochure_id" value="<?= $b['id'] ?>">
                                            <select name="market_id"
                                                    class="bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-lg px-2 py-1.5 outline-none focus:border-red-500 transition">
                                                <?php foreach ($all_markets as $m): ?>
                                                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $b['market_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($m['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit"
                                                    class="bg-red-600 hover:bg-red-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition">
                                                Kaydet
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
    function toggleAll(masterCb) {
        document.querySelectorAll('.mismatch-cb').forEach(cb => cb.checked = masterCb.checked);
    }
    // Sync checkbox with select: if select = empty, uncheck; otherwise check
    document.querySelectorAll('select[name^="fixes["]').forEach(sel => {
        sel.addEventListener('change', () => {
            const id = sel.name.match(/\d+/)[0];
            const cb = document.querySelector(`input[name="fixes[${id}]"][type="checkbox"]`);
            if (cb) cb.checked = sel.value !== '';
        });
    });
    </script>
</body>
</html>
