<?php
/**
 * Akakçe bulk brochure importer.
 *
 * CLI:
 *   php admin/akakce_import.php --run --limit=20 --max-pages=12
 *   php admin/akakce_import.php --run --dry-run --limit=5
 */

$is_cli = (php_sapi_name() === 'cli');
if ($is_cli) {
    chdir(dirname(__DIR__));
    require 'config.php';
} else {
    require '../config.php';
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        header("Location: login.php");
        exit;
    }
}

const AKAKCE_LIST_URL = 'https://www.akakce.com/brosurler/?l=1';
const AKAKCE_BASE_URL = 'https://www.akakce.com';
const AKAKCE_CDN_URL  = 'https://cdn.akakce.com';
define('MI_ROOT_PATH', dirname(__DIR__));

function akakce_abs_url(string $url, string $base = AKAKCE_BASE_URL): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') return '';
    if (str_starts_with($url, '//')) return 'https:' . $url;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
    if (str_starts_with($url, '/_bro/')) return AKAKCE_CDN_URL . $url;
    if (str_starts_with($url, '/')) return rtrim($base, '/') . $url;
    return rtrim($base, '/') . '/' . ltrim($url, '/');
}

function akakce_fetch(string $url, int $timeout = 30): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.7',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    if ($body === false || $code >= 400 || $body === '') {
        throw new RuntimeException("Akakçe sayfası alınamadı ({$code}): {$url} {$err}");
    }
    return $body;
}

function akakce_download(string $url, string $dest, string $referer): bool {
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (is_file($dest) && filesize($dest) > 500) {
        return true;
    }

    $fp = fopen($dest, 'wb');
    if (!$fp) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.7',
            'Referer: ' . $referer,
        ],
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);

    if (!$ok || $code >= 400 || !is_file($dest) || filesize($dest) < 500) {
        @unlink($dest);
        return false;
    }

    return true;
}

function akakce_dom_text(DOMXPath $xp, string $query, DOMNode $ctx): string {
    $node = $xp->query($query, $ctx)->item(0);
    return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';
}

function akakce_parse_list(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xp = new DOMXPath($doc);
    $items = [];

    foreach ($xp->query('//ul[@id="BLI"]//li/a') as $a) {
        /** @var DOMElement $a */
        $href = $a->getAttribute('href');
        if (!preg_match('/\/brosurler\/.+-\d+$/', $href)) {
            continue;
        }

        $img = $xp->query('.//img', $a)->item(0);
        $image = '';
        $alt = '';
        if ($img instanceof DOMElement) {
            $image = $img->getAttribute('data-src') ?: $img->getAttribute('src');
            $alt = $img->getAttribute('alt');
        }

        $market = akakce_dom_text($xp, './/div[contains(concat(" ", normalize-space(@class), " "), " blid ")]/b', $a);
        $brochure_name = akakce_dom_text($xp, './/div[contains(concat(" ", normalize-space(@class), " "), " blid ")]/span[contains(concat(" ", normalize-space(@class), " "), " bn ")]', $a);
        $status = akakce_dom_text($xp, './/span[contains(concat(" ", normalize-space(@class), " "), " b ")]', $a);

        if ($market === '') {
            $market = preg_replace('/\s+(broşürü|brosuru|katalogu|aktuel|aktüel).*$/iu', '', $alt) ?? $alt;
        }

        $items[] = [
            'href' => akakce_abs_url($href),
            'source_uid' => 'akakce:' . basename($href),
            'market_name' => html_entity_decode($market, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'brochure_name' => html_entity_decode($brochure_name ?: $alt, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'cover_url' => akakce_abs_url($image, AKAKCE_CDN_URL),
            'status' => html_entity_decode($status, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }

    $unique = [];
    foreach ($items as $item) {
        $unique[$item['source_uid']] = $item;
    }
    return array_values($unique);
}

function akakce_prop_string(string $decoded, string $field): string {
    if (preg_match('/"' . preg_quote($field, '/') . '":\[0,"([^"]*)"\]/u', $decoded, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function akakce_prop_num(string $text, string $field) {
    if (preg_match('/"' . preg_quote($field, '/') . '":\[0,([0-9.]+)\]/u', $text, $m)) {
        return (float)$m[1];
    }
    return null;
}

function akakce_parse_date(string $value): ?string {
    $value = mi_text_normalize($value);
    $months = [
        'ocak' => 1, 'subat' => 2, 'mart' => 3, 'nisan' => 4, 'mayis' => 5,
        'haziran' => 6, 'temmuz' => 7, 'agustos' => 8, 'eylul' => 9,
        'ekim' => 10, 'kasim' => 11, 'aralik' => 12,
    ];
    if (preg_match('/(\d{1,2})\s+([a-z]+)\s+(20\d{2})/u', $value, $m) && isset($months[$m[2]])) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], $months[$m[2]], (int)$m[1]);
    }
    return null;
}

function akakce_parse_clip(string $clip, float $page_w, float $page_h): ?array {
    $name = akakce_prop_string($clip, 'n');
    $price = mi_parse_price(akakce_prop_string($clip, 'p'));
    if ($name === '' || $price === null) {
        return null;
    }

    $x = akakce_prop_num($clip, 'x');
    $y = akakce_prop_num($clip, 'y');
    $w = akakce_prop_num($clip, 'w');
    $h = akakce_prop_num($clip, 'h');

    return [
        'product_name' => $name,
        'price' => $price,
        'x_pct' => ($x !== null && $page_w > 0) ? round(($x / $page_w) * 100, 3) : null,
        'y_pct' => ($y !== null && $page_h > 0) ? round(($y / $page_h) * 100, 3) : null,
        'w_pct' => ($w !== null && $page_w > 0) ? round(($w / $page_w) * 100, 3) : null,
        'h_pct' => ($h !== null && $page_h > 0) ? round(($h / $page_h) * 100, 3) : null,
    ];
}

function akakce_parse_detail(string $html): array {
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $meta = [
        'market_name' => akakce_prop_string($decoded, 'vn'),
        'brochure_name' => akakce_prop_string($decoded, 'n'),
        'start_label' => akakce_prop_string($decoded, 'vsd'),
        'end_label' => akakce_prop_string($decoded, 'ved'),
        'canonical_path' => akakce_prop_string($decoded, 'su'),
    ];

    $meta['start_date'] = akakce_parse_date($meta['start_label']);
    $meta['end_date'] = akakce_parse_date($meta['end_label']);

    preg_match_all('/"hriURL":\[0,"([^"]+)"\]/u', $decoded, $matches, PREG_OFFSET_CAPTURE);
    $pages = [];
    $count = count($matches[1]);

    for ($i = 0; $i < $count; $i++) {
        $url = $matches[1][$i][0];
        $start = $matches[0][$i][1];
        $end = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($decoded);
        $segment = substr($decoded, $start, $end - $start);

        $page_w = 1000.0;
        $page_h = 1000.0;
        if (preg_match('/"w":\[0,([0-9.]+)\],"h":\[0,([0-9.]+)\]/u', $segment, $wh)) {
            $page_w = (float)$wh[1];
            $page_h = (float)$wh[2];
        }

        $products = [];
        preg_match_all('/\{"ci":\[0,\d+\].*?\}/su', $segment, $clips);
        foreach ($clips[0] as $clip) {
            $product = akakce_parse_clip($clip, $page_w, $page_h);
            if ($product) {
                $products[] = $product;
            }
        }

        $pages[] = [
            'image_url' => akakce_abs_url($url, AKAKCE_CDN_URL),
            'width' => $page_w,
            'height' => $page_h,
            'products' => $products,
        ];
    }

    return ['meta' => $meta, 'pages' => $pages];
}

function akakce_load_markets(PDO $pdo): array {
    return $pdo->query("SELECT * FROM markets ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function akakce_resolve_market(PDO $pdo, string $market_name): array {
    $markets = akakce_load_markets($pdo);
    $found = mi_find_market_by_name($market_name, $markets);
    if ($found) {
        return $found;
    }

    $slug_base = mi_slugify($market_name);
    $slug = $slug_base;
    $i = 2;
    while (true) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ?");
        $check->execute([$slug]);
        if ((int)$check->fetchColumn() === 0) break;
        $slug = $slug_base . '-' . $i++;
    }

    $stmt = $pdo->prepare("INSERT INTO markets (name, slug, description, category_id, scraper_active) VALUES (?, ?, ?, 1, 0)");
    $stmt->execute([
        $market_name,
        $slug,
        $market_name . ' Akakçe broşürleri',
    ]);

    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM markets WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function akakce_existing_brochure(PDO $pdo, string $source_uid, string $source_url, int $market_id, string $title, ?string $start_date): ?int {
    $stmt = $pdo->prepare("SELECT id FROM brochures WHERE source_uid = ? OR source_url = ? LIMIT 1");
    $stmt->execute([$source_uid, $source_url]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    $stmt = $pdo->prepare("SELECT id FROM brochures WHERE market_id = ? AND title = ? AND start_date = ? LIMIT 1");
    $stmt->execute([$market_id, $title, $start_date]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function akakce_clear_brochure_content(PDO $pdo, int $brochure_id): void {
    $pages = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
    $pages->execute([$brochure_id]);
    foreach ($pages->fetchAll(PDO::FETCH_COLUMN) as $image) {
        if ($image && is_file(MI_ROOT_PATH . '/uploads/brochures/pages/' . $image)) {
            @unlink(MI_ROOT_PATH . '/uploads/brochures/pages/' . $image);
        }
    }

    $cover = $pdo->prepare("SELECT cover_image FROM brochures WHERE id = ?");
    $cover->execute([$brochure_id]);
    $cover_image = $cover->fetchColumn();
    if ($cover_image && is_file(MI_ROOT_PATH . '/uploads/brochures/' . $cover_image)) {
        @unlink(MI_ROOT_PATH . '/uploads/brochures/' . $cover_image);
    }

    $pdo->prepare("DELETE FROM brochure_products WHERE brochure_id = ?")->execute([$brochure_id]);
    $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$brochure_id]);
}

function akakce_import_item(PDO $pdo, array $item, array $options): array {
    $detail_html = akakce_fetch($item['href'], 45);
    $detail = akakce_parse_detail($detail_html);
    $meta = $detail['meta'];
    $pages = $detail['pages'];

    $market_name = $meta['market_name'] ?: $item['market_name'];
    $source_uid = $item['source_uid'];
    if (preg_match('/(\d+)$/', $item['href'], $uid_match)) {
        $source_uid = 'akakce:' . $uid_match[1];
    }

    $start_date = $meta['start_date'] ?: date('Y-m-d');
    $end_date = $meta['end_date'] ?: date('Y-m-d', strtotime('+14 days'));
    $brochure_name = $meta['brochure_name'] ?: $item['brochure_name'];

    if (!empty($options['dry_run'])) {
        $market = mi_find_market_by_name($market_name, akakce_load_markets($pdo));
        $market_note = $market ? ('eşleşen market: ' . $market['name']) : 'yeni market oluşturulacak';
        $title = trim($market_name . ' ' . ($meta['start_label'] ?: date('d.m.Y', strtotime($start_date))) . ' ' . $brochure_name);
        return ['status' => 'dry', 'title' => $title, 'message' => count($pages) . ' sayfa, ' . array_sum(array_map(fn($p) => count($p['products']), $pages)) . ' ürün, ' . $market_note];
    }

    $market = akakce_resolve_market($pdo, $market_name);
    $title = trim($market['name'] . ' ' . ($meta['start_label'] ?: date('d.m.Y', strtotime($start_date))) . ' ' . $brochure_name);

    $existing_id = akakce_existing_brochure($pdo, $source_uid, $item['href'], (int)$market['id'], $title, $start_date);
    if ($existing_id && empty($options['refresh'])) {
        return ['status' => 'skipped', 'title' => $title, 'message' => 'Zaten var', 'brochure_id' => $existing_id];
    }

    if (!$pages) {
        throw new RuntimeException("Sayfa görseli bulunamadı: {$item['href']}");
    }

    if ($existing_id) {
        $brochure_id = $existing_id;
        akakce_clear_brochure_content($pdo, $brochure_id);
        $stmt = $pdo->prepare("UPDATE brochures SET market_id = ?, title = ?, start_date = ?, end_date = ?, source_name = 'akakce', source_url = ?, source_uid = ?, show_on_homepage = 1 WHERE id = ?");
        $stmt->execute([(int)$market['id'], $title, $start_date, $end_date, $item['href'], $source_uid, $brochure_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO brochures (market_id, title, cover_image, start_date, end_date, source_name, source_url, source_uid, show_on_homepage) VALUES (?, ?, NULL, ?, ?, 'akakce', ?, ?, 1)");
        $stmt->execute([(int)$market['id'], $title, $start_date, $end_date, $item['href'], $source_uid]);
        $brochure_id = (int)$pdo->lastInsertId();
    }

    $safe_uid = preg_replace('/[^a-z0-9]+/', '_', strtolower($source_uid));
    $max_pages = (int)($options['max_pages'] ?? 0);
    $page_insert = $pdo->prepare("INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)");
    $product_insert = $pdo->prepare("
        INSERT INTO brochure_products
            (brochure_id, page_number, product_name, price, original_price, unit, x_pct, y_pct, w_pct, h_pct)
        VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
    ");

    $downloaded_pages = 0;
    $saved_products = 0;
    $cover_name = null;

    foreach ($pages as $idx => $page) {
        $page_no = $idx + 1;
        if ($max_pages > 0 && $page_no > $max_pages) {
            break;
        }

        $ext = strtolower(pathinfo(parse_url($page['image_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $page_file = "{$safe_uid}_page_{$page_no}.{$ext}";
        $page_dest = MI_ROOT_PATH . '/uploads/brochures/pages/' . $page_file;

        if (!akakce_download($page['image_url'], $page_dest, $item['href'])) {
            continue;
        }

        $page_insert->execute([$brochure_id, $page_no, $page_file]);
        $downloaded_pages++;

        if ($page_no === 1) {
            $cover_name = "{$safe_uid}_cover.{$ext}";
            @copy($page_dest, MI_ROOT_PATH . '/uploads/brochures/' . $cover_name);
            $pdo->prepare("UPDATE brochures SET cover_image = ? WHERE id = ?")->execute([$cover_name, $brochure_id]);
        }

        foreach ($page['products'] as $product) {
            $product_insert->execute([
                $brochure_id,
                $page_no,
                $product['product_name'],
                $product['price'],
                $product['x_pct'],
                $product['y_pct'],
                $product['w_pct'],
                $product['h_pct'],
            ]);
            $saved_products++;
        }
    }

    if ($downloaded_pages === 0) {
        throw new RuntimeException("Görsel indirilemedi: {$item['href']}");
    }

    if ($saved_products > 0) {
        $pdo->prepare("UPDATE brochures SET analyzed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$brochure_id]);
    }

    return [
        'status' => $existing_id ? 'refreshed' : 'imported',
        'title' => $title,
        'message' => "{$downloaded_pages} sayfa, {$saved_products} ürün",
        'brochure_id' => $brochure_id,
        'cover' => $cover_name,
    ];
}

function akakce_import_all(PDO $pdo, array $options): array {
    set_time_limit(0);
    $html = akakce_fetch(AKAKCE_LIST_URL, 45);
    $items = akakce_parse_list($html);
    $limit = (int)($options['limit'] ?? 0);
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    $results = [];
    foreach ($items as $item) {
        try {
            $results[] = akakce_import_item($pdo, $item, $options);
        } catch (Throwable $e) {
            $results[] = [
                'status' => 'error',
                'title' => ($item['market_name'] ?? '') . ' ' . ($item['brochure_name'] ?? ''),
                'message' => $e->getMessage(),
            ];
        }
    }

    return $results;
}

function akakce_cli_options(array $argv): array {
    $options = ['run' => false, 'limit' => 0, 'max_pages' => 0, 'refresh' => false, 'dry_run' => false];
    foreach ($argv as $arg) {
        if ($arg === '--run') $options['run'] = true;
        if ($arg === '--refresh') $options['refresh'] = true;
        if ($arg === '--dry-run') $options['dry_run'] = true;
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $options['limit'] = (int)$m[1];
        if (preg_match('/^--max-pages=(\d+)$/', $arg, $m)) $options['max_pages'] = (int)$m[1];
    }
    return $options;
}

if ($is_cli) {
    $options = akakce_cli_options($argv);
    if (!$options['run']) {
        echo "Kullanım: php admin/akakce_import.php --run [--limit=20] [--max-pages=12] [--refresh] [--dry-run]\n";
        exit(0);
    }
    $results = akakce_import_all($pdo, $options);
    foreach ($results as $result) {
        echo strtoupper($result['status']) . " | " . $result['title'] . " | " . $result['message'] . "\n";
    }
    exit(0);
}

$ran = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run']));
$results = [];
$summary = ['imported' => 0, 'refreshed' => 0, 'skipped' => 0, 'dry' => 0, 'error' => 0];

if ($ran) {
    $options = [
        'limit' => max(0, (int)($_POST['limit'] ?? 0)),
        'max_pages' => max(0, (int)($_POST['max_pages'] ?? 0)),
        'refresh' => isset($_POST['refresh']),
        'dry_run' => isset($_POST['dry_run']),
    ];
    $results = akakce_import_all($pdo, $options);
    foreach ($results as $result) {
        $summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akakçe Broşür İçe Aktar - marketisleri.com</title>
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
        <div>
            <a href="markets.php" class="text-sm text-slate-400 hover:text-white inline-flex items-center gap-1 mb-3">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Marketlere dön
            </a>
            <h1 class="font-title text-3xl font-black text-white">Akakçe Broşür İçe Aktar</h1>
            <p class="text-slate-400 mt-2">Akakçe toplu broşür listesinden broşürleri, sayfa görsellerini ve ürün fiyat noktalarını ilgili marketlere ekler.</p>
        </div>

        <form method="POST" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-5">
            <input type="hidden" name="run" value="1">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-slate-400 mb-2 font-bold">Broşür limiti</label>
                    <input type="number" name="limit" min="0" value="<?= htmlspecialchars($_POST['limit'] ?? '0') ?>"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white">
                    <p class="text-xs text-slate-500 mt-1">0 = tüm liste.</p>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-slate-400 mb-2 font-bold">Sayfa limiti</label>
                    <input type="number" name="max_pages" min="0" value="<?= htmlspecialchars($_POST['max_pages'] ?? '0') ?>"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white">
                    <p class="text-xs text-slate-500 mt-1">0 = broşürdeki tüm sayfalar.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-5">
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="refresh" class="rounded bg-slate-950 border-slate-700">
                    Mevcut Akakçe broşürlerini yenile
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="dry_run" class="rounded bg-slate-950 border-slate-700">
                    Sadece önizleme yap
                </label>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl font-bold">
                <span class="material-symbols-outlined">cloud_download</span>
                İçe Aktar
            </button>
        </form>

        <?php if ($ran): ?>
            <section class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800 flex flex-wrap gap-3 text-sm">
                    <span class="text-emerald-300">Yeni: <?= (int)$summary['imported'] ?></span>
                    <span class="text-blue-300">Yenilenen: <?= (int)$summary['refreshed'] ?></span>
                    <span class="text-slate-400">Atlanan: <?= (int)$summary['skipped'] ?></span>
                    <span class="text-amber-300">Önizleme: <?= (int)$summary['dry'] ?></span>
                    <span class="text-red-300">Hata: <?= (int)$summary['error'] ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wider text-slate-500 bg-slate-950/40">
                            <tr>
                                <th class="px-6 py-3">Durum</th>
                                <th class="px-6 py-3">Broşür</th>
                                <th class="px-6 py-3">Sonuç</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td class="px-6 py-3 font-mono text-xs"><?= htmlspecialchars(strtoupper($result['status'])) ?></td>
                                    <td class="px-6 py-3 font-bold text-white"><?= htmlspecialchars($result['title']) ?></td>
                                    <td class="px-6 py-3 text-slate-300"><?= htmlspecialchars($result['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
