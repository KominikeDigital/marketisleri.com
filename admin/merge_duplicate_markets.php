<?php
require '../config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

function market_relation_counts(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT m.*,
               (SELECT COUNT(*) FROM brochures b WHERE b.market_id = m.id) AS brochure_count,
               (SELECT COUNT(*) FROM price_alerts pa WHERE pa.market_id = m.id) AS alert_count
        FROM markets m
        ORDER BY m.name ASC, m.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['canonical_key'] = mi_market_canonical_key($row);
    }
    unset($row);

    return $rows;
}

function duplicate_groups(array $markets): array {
    $groups = [];
    foreach ($markets as $market) {
        $key = $market['canonical_key'];
        if ($key === '') continue;
        $groups[$key][] = $market;
    }

    $groups = array_filter($groups, fn($items) => count($items) > 1);

    foreach ($groups as $key => &$items) {
        usort($items, function($a, $b) use ($key) {
            $a_score = 0;
            $b_score = 0;
            if (($a['slug'] ?? '') === $key) $a_score += 1000;
            if (($b['slug'] ?? '') === $key) $b_score += 1000;
            $a_score += ((int)($a['is_popular'] ?? 0)) * 100;
            $b_score += ((int)($b['is_popular'] ?? 0)) * 100;
            $a_score += ((int)$a['brochure_count']) * 2;
            $b_score += ((int)$b['brochure_count']) * 2;
            if ($a_score === $b_score) {
                return (int)$a['id'] <=> (int)$b['id'];
            }
            return $b_score <=> $a_score;
        });
    }
    unset($items);

    ksort($groups);
    return $groups;
}

function merge_market(PDO $pdo, array $target, array $duplicate): array {
    $target_id = (int)$target['id'];
    $dup_id = (int)$duplicate['id'];
    if ($target_id === $dup_id) {
        return ['brochures' => 0, 'alerts' => 0];
    }

    $brochure_stmt = $pdo->prepare("UPDATE brochures SET market_id = ? WHERE market_id = ?");
    $brochure_stmt->execute([$target_id, $dup_id]);

    $alert_stmt = $pdo->prepare("UPDATE price_alerts SET market_id = ? WHERE market_id = ?");
    $alert_stmt->execute([$target_id, $dup_id]);

    $updates = [];
    $params = [];
    foreach (['logo', 'description', 'scraper_url', 'scraper_container', 'scraper_title', 'scraper_cover', 'scraper_detail_link', 'scraper_page_image'] as $field) {
        if (empty($target[$field]) && !empty($duplicate[$field])) {
            $updates[] = "$field = ?";
            $params[] = $duplicate[$field];
        }
    }
    if ((int)($target['scraper_active'] ?? 0) === 0 && (int)($duplicate['scraper_active'] ?? 0) === 1) {
        $updates[] = "scraper_active = 1";
    }
    if ((int)($target['is_popular'] ?? 0) === 0 && (int)($duplicate['is_popular'] ?? 0) === 1) {
        $updates[] = "is_popular = 1";
    }
    if ($updates) {
        $params[] = $target_id;
        $pdo->prepare("UPDATE markets SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
    }

    $pdo->prepare("DELETE FROM markets WHERE id = ?")->execute([$dup_id]);

    return [
        'brochures' => $brochure_stmt->rowCount(),
        'alerts' => $alert_stmt->rowCount(),
    ];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'merge_all') {
    try {
        $pdo->beginTransaction();
        $groups = duplicate_groups(market_relation_counts($pdo));
        $merged_markets = 0;
        $moved_brochures = 0;
        $moved_alerts = 0;

        foreach ($groups as $items) {
            $target = $items[0];
            foreach (array_slice($items, 1) as $duplicate) {
                $result = merge_market($pdo, $target, $duplicate);
                $merged_markets++;
                $moved_brochures += $result['brochures'];
                $moved_alerts += $result['alerts'];
            }
        }

        $pdo->commit();
        $message = "{$merged_markets} çift kayıt birleştirildi. {$moved_brochures} broşür ve {$moved_alerts} fiyat alarmı taşındı.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Birleştirme tamamlanamadı: " . $e->getMessage();
    }
}

$groups = duplicate_groups(market_relation_counts($pdo));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çift Market Birleştir - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <main class="max-w-6xl mx-auto p-8 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="markets.php" class="text-sm text-slate-400 hover:text-white inline-flex items-center gap-1 mb-3">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                    Marketlere dön
                </a>
                <h1 class="font-title text-3xl font-black text-white">Çift Market Birleştir</h1>
                <p class="text-slate-400 mt-2">Aynı marketin farklı ad/slug ile açılmış kayıtlarını tespit eder; broşürleri ve fiyat alarmlarını ana kayda taşır.</p>
            </div>
            <?php if ($groups): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="merge_all">
                    <button type="submit"
                            onclick="return confirm('Tespit edilen tüm çift marketler birleştirilecek. Devam edilsin mi?')"
                            class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-500 text-white px-5 py-3 rounded-xl font-bold">
                        <span class="material-symbols-outlined">call_merge</span>
                        Tümünü Birleştir
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm p-4 rounded-2xl"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-2xl"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$groups): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-12 text-center">
                <span class="material-symbols-outlined text-5xl text-emerald-400 block mb-3">check_circle</span>
                <p class="font-title text-xl font-bold text-white">Çift market kaydı bulunmadı.</p>
                <p class="text-slate-500 mt-1">Yeni importlar da aynı normalizasyonu kullanacağı için tekrar çift kayıt üretmez.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($groups as $key => $items): $target = $items[0]; ?>
                    <section class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-slate-500">Eşleşme anahtarı</div>
                                <div class="font-mono text-amber-300"><?= htmlspecialchars($key) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-wider text-slate-500">Hedef kayıt</div>
                                <div class="font-bold text-white">#<?= (int)$target['id'] ?> <?= htmlspecialchars($target['name']) ?></div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-xs uppercase tracking-wider text-slate-500 bg-slate-950/40">
                                    <tr>
                                        <th class="px-6 py-3">Durum</th>
                                        <th class="px-6 py-3">ID</th>
                                        <th class="px-6 py-3">Market</th>
                                        <th class="px-6 py-3">Slug</th>
                                        <th class="px-6 py-3">Broşür</th>
                                        <th class="px-6 py-3">Alarm</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <?php foreach ($items as $idx => $market): ?>
                                        <tr class="<?= $idx === 0 ? 'bg-emerald-950/10' : '' ?>">
                                            <td class="px-6 py-3">
                                                <?php if ($idx === 0): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 text-xs font-bold">Kalacak</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-300 border border-amber-500/20 text-xs font-bold">Birleşecek</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-3 font-mono text-slate-400">#<?= (int)$market['id'] ?></td>
                                            <td class="px-6 py-3 font-bold text-white"><?= htmlspecialchars($market['name']) ?></td>
                                            <td class="px-6 py-3 font-mono text-slate-400"><?= htmlspecialchars($market['slug']) ?></td>
                                            <td class="px-6 py-3"><?= (int)$market['brochure_count'] ?></td>
                                            <td class="px-6 py-3"><?= (int)$market['alert_count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
