<?php
require '../config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

function amazon_ensure_market(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'amazon' LIMIT 1");
    $market = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($market) {
        return $market;
    }

    $pdo->exec("INSERT INTO markets (name, slug, logo, description, category_id, is_popular, scraper_active)
                VALUES ('Amazon', 'amazon', 'amazon.png', 'Amazon affiliate ürün fırsatları ve kampanyaları.', 3, 1, 0)");
    $stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'amazon' LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function amazon_fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.7,en;q=0.6',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ],
    ]);

    $html = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);

    return [
        'html' => ($code >= 200 && $code < 400 && is_string($html)) ? $html : '',
        'code' => $code,
        'final_url' => $final_url ?: $url,
        'error' => $err,
    ];
}

function amazon_extract_asin(string $url): string {
    $decoded = urldecode($url);
    $patterns = [
        '~/(?:dp|gp/product|product)/([A-Z0-9]{10})(?:[/?#]|$)~i',
        '~/(B[A-Z0-9]{9})(?:[/?#]|$)~i',
        '~[?&]asin=([A-Z0-9]{10})~i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $decoded, $m)) {
            return strtoupper($m[1]);
        }
    }

    return '';
}

function amazon_abs_url(string $url, string $base): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') return '';
    if (str_starts_with($url, '//')) return 'https:' . $url;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
    $parts = parse_url($base);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.amazon.com.tr');
    if (str_starts_with($url, '/')) return $origin . $url;
    return rtrim($origin, '/') . '/' . ltrim($url, '/');
}

function amazon_meta(DOMXPath $xp, string $name): string {
    $queries = [
        '//meta[@property="' . $name . '"]/@content',
        '//meta[@name="' . $name . '"]/@content',
    ];
    foreach ($queries as $query) {
        $node = $xp->query($query)->item(0);
        if ($node) {
            return trim(html_entity_decode($node->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    return '';
}

function amazon_first_text(DOMXPath $xp, array $queries): string {
    foreach ($queries as $query) {
        $node = $xp->query($query)->item(0);
        if ($node && trim($node->textContent) !== '') {
            return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
    }
    return '';
}

function amazon_first_attr(DOMXPath $xp, array $queries, string $attr): string {
    foreach ($queries as $query) {
        $node = $xp->query($query)->item(0);
        if ($node instanceof DOMElement) {
            $value = trim($node->getAttribute($attr));
            if ($value !== '') return $value;
        }
    }
    return '';
}

function amazon_dynamic_image(DOMXPath $xp): string {
    $img = $xp->query('//*[@id="landingImage"] | //img[@data-a-dynamic-image]')->item(0);
    if (!$img instanceof DOMElement) return '';

    $dynamic = $img->getAttribute('data-a-dynamic-image');
    if ($dynamic !== '') {
        $decoded = json_decode(html_entity_decode($dynamic, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($decoded) && $decoded) {
            return (string)array_key_first($decoded);
        }
    }

    foreach (['data-old-hires', 'src'] as $attr) {
        $value = trim($img->getAttribute($attr));
        if ($value !== '' && !str_contains($value, 'transparent-pixel')) {
            return $value;
        }
    }

    return '';
}

function amazon_jsonld_products(DOMXPath $xp): array {
    $products = [];
    foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
        $raw = trim($script->textContent);
        if ($raw === '') continue;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) continue;

        $stack = [$decoded];
        while ($stack) {
            $item = array_pop($stack);
            if (!is_array($item)) continue;

            $type = $item['@type'] ?? '';
            $types = is_array($type) ? $type : [$type];
            if (in_array('Product', $types, true)) {
                $products[] = $item;
            }

            foreach (['@graph', 'itemListElement'] as $key) {
                if (isset($item[$key]) && is_array($item[$key])) {
                    foreach ($item[$key] as $child) {
                        $stack[] = $child;
                    }
                }
            }
        }
    }
    return $products;
}

function amazon_jsonld_value(array $products, string $field): string {
    foreach ($products as $product) {
        if (!empty($product[$field])) {
            $value = $product[$field];
            if (is_array($value)) {
                $value = reset($value);
            }
            if (is_string($value) || is_numeric($value)) {
                return trim((string)$value);
            }
        }
    }
    return '';
}

function amazon_jsonld_offer_price(array $products): ?float {
    foreach ($products as $product) {
        $offers = $product['offers'] ?? null;
        if (!$offers) continue;
        if (isset($offers['price'])) {
            return mi_parse_price($offers['price']);
        }
        if (is_array($offers)) {
            foreach ($offers as $offer) {
                if (is_array($offer) && isset($offer['price'])) {
                    return mi_parse_price($offer['price']);
                }
            }
        }
    }
    return null;
}

function amazon_jsonld_rating(array $products): array {
    foreach ($products as $product) {
        $rating = $product['aggregateRating'] ?? null;
        if (is_array($rating)) {
            return [
                'rating' => isset($rating['ratingValue']) ? (string)$rating['ratingValue'] : '',
                'review_count' => isset($rating['reviewCount']) ? (string)$rating['reviewCount'] : '',
            ];
        }
    }
    return ['rating' => '', 'review_count' => ''];
}

function amazon_bullet_summary(DOMXPath $xp): string {
    $parts = [];
    foreach ($xp->query('//*[@id="feature-bullets"]//li//span[normalize-space()]') as $node) {
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
        if ($text !== '' && !str_contains($text, 'Daha fazla bilgi')) {
            $parts[] = $text;
        }
        if (count($parts) >= 2) break;
    }
    return implode(' ', $parts);
}

function amazon_parse_product(string $html, string $requested_url, string $final_url): array {
    if (stripos($html, 'Robot Check') !== false || stripos($html, 'captcha') !== false) {
        throw new RuntimeException('Amazon bot/CAPTCHA koruması döndürdü. Linke tarayıcıdan erişilebildiğini kontrol edip tekrar deneyin.');
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xp = new DOMXPath($doc);
    $json_products = amazon_jsonld_products($xp);

    $title = amazon_first_text($xp, [
        '//*[@id="productTitle"]',
        '//span[contains(@class, "product-title-word-break")]',
        '//h1',
    ]);
    if ($title === '') {
        $title = amazon_jsonld_value($json_products, 'name') ?: amazon_meta($xp, 'og:title') ?: amazon_meta($xp, 'twitter:title');
    }
    $title = trim(preg_replace('/\s*\|\s*Amazon.*$/iu', '', $title));

    $price = amazon_jsonld_offer_price($json_products);
    if ($price === null) {
        $price_text = amazon_meta($xp, 'product:price:amount') ?: amazon_first_text($xp, [
            '//*[@id="corePrice_feature_div"]//*[contains(@class, "a-offscreen")]',
            '//*[@id="priceblock_ourprice"]',
            '//*[@id="priceblock_dealprice"]',
            '//*[contains(@class, "a-price")]//*[contains(@class, "a-offscreen")]',
        ]);
        $price = mi_parse_price($price_text);
    }

    $image = amazon_jsonld_value($json_products, 'image') ?: amazon_meta($xp, 'og:image') ?: amazon_dynamic_image($xp);
    if (is_string($image) && str_starts_with($image, '[')) {
        $decoded = json_decode($image, true);
        if (is_array($decoded)) $image = (string)reset($decoded);
    }
    $image = amazon_abs_url((string)$image, $final_url);

    $rating_data = amazon_jsonld_rating($json_products);
    $rating = $rating_data['rating'];
    if ($rating === '') {
        $rating_text = amazon_first_attr($xp, ['//*[@id="acrPopover"]'], 'title')
            ?: amazon_first_text($xp, ['//*[contains(@class, "a-icon-alt")]']);
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $rating_text, $m)) {
            $rating = str_replace(',', '.', $m[1]);
        }
    }

    $review_count = $rating_data['review_count'];
    if ($review_count === '') {
        $review_count = amazon_first_text($xp, ['//*[@id="acrCustomerReviewText"]']);
        $review_count = trim(preg_replace('/[^0-9.]+/', '', $review_count));
    }

    $description = amazon_bullet_summary($xp);
    if ($description === '') {
        $description = amazon_jsonld_value($json_products, 'description') ?: amazon_meta($xp, 'description');
    }
    $description = mb_substr(trim(preg_replace('/\s+/', ' ', $description)), 0, 280, 'UTF-8');

    $asin = amazon_extract_asin($final_url) ?: amazon_extract_asin($requested_url);
    if ($title === '' || $image === '') {
        throw new RuntimeException('Ürün adı veya görseli okunamadı. Amazon sayfası bot koruması, geçersiz link veya desteklenmeyen sayfa yapısı döndürmüş olabilir.');
    }

    return [
        'asin' => $asin,
        'title' => $title,
        'price' => $price,
        'image_url' => $image,
        'rating' => $rating,
        'review_count' => $review_count,
        'description' => $description,
        'affiliate_url' => $requested_url,
        'final_url' => $final_url,
    ];
}

function amazon_download_image(string $url, string $source_key): ?string {
    $safe_key = preg_replace('/[^a-z0-9]+/i', '-', strtolower($source_key)) ?: md5($url);
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }

    $file_name = 'amazon-' . $safe_key . '-' . time() . '.' . $ext;
    $dest = dirname(__DIR__) . '/uploads/brochures/' . $file_name;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }

    $fp = fopen($dest, 'wb');
    if (!$fp) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Referer: https://www.amazon.com.tr/',
        ],
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);

    if (!$ok || $code >= 400 || !is_file($dest) || filesize($dest) < 500) {
        @unlink($dest);
        return null;
    }

    return $file_name;
}

function amazon_source_uid(array $product): string {
    if (!empty($product['asin'])) {
        return 'amazon:' . $product['asin'];
    }
    return 'amazon:' . md5(($product['final_url'] ?? '') . '|' . ($product['affiliate_url'] ?? ''));
}

$amazon_market = amazon_ensure_market($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $affiliate_url = trim((string)($_POST['affiliate_url'] ?? ''));
    $start_date = trim((string)($_POST['start_date'] ?? date('Y-m-d')));
    $end_date = trim((string)($_POST['end_date'] ?? date('Y-m-d', strtotime('+14 days'))));
    $show_on_homepage = isset($_POST['show_on_homepage']) ? 1 : 0;

    if ($affiliate_url === '' || !filter_var($affiliate_url, FILTER_VALIDATE_URL)) {
        $error = 'Lütfen geçerli bir Amazon affiliate ürün linki girin.';
    } else {
        try {
            $fetch = amazon_fetch($affiliate_url);
            if ($fetch['html'] === '') {
                throw new RuntimeException('Amazon sayfası alınamadı. HTTP: ' . $fetch['code'] . ($fetch['error'] ? ' - ' . $fetch['error'] : ''));
            }

            $product = amazon_parse_product($fetch['html'], $affiliate_url, $fetch['final_url']);
            $source_uid = amazon_source_uid($product);
            $cover_image = amazon_download_image($product['image_url'], $product['asin'] ?: md5($product['image_url'])) ?: $product['image_url'];

            $pdo->beginTransaction();

            $existing = $pdo->prepare("SELECT id, cover_image FROM brochures WHERE source_uid = ? LIMIT 1");
            $existing->execute([$source_uid]);
            $existing_row = $existing->fetch(PDO::FETCH_ASSOC);
            $brochure_id = $existing_row ? (int)$existing_row['id'] : 0;

            if ($brochure_id > 0) {
                $old_cover = (string)($existing_row['cover_image'] ?? '');
                if ($old_cover !== '' && !str_starts_with($old_cover, 'http') && $old_cover !== $cover_image) {
                    $old_path = dirname(__DIR__) . '/' . mi_brochure_cover_src($old_cover);
                    if (is_file($old_path)) @unlink($old_path);
                }

                $stmt = $pdo->prepare("
                    UPDATE brochures
                    SET market_id = ?, title = ?, cover_image = ?, start_date = ?, end_date = ?,
                        show_on_homepage = ?, analyzed_at = CURRENT_TIMESTAMP,
                        source_name = 'amazon', source_url = ?, source_uid = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $amazon_market['id'],
                    $product['title'],
                    $cover_image,
                    $start_date ?: date('Y-m-d'),
                    $end_date ?: date('Y-m-d', strtotime('+14 days')),
                    $show_on_homepage,
                    $affiliate_url,
                    $source_uid,
                    $brochure_id,
                ]);
                $pdo->prepare("DELETE FROM brochure_products WHERE brochure_id = ?")->execute([$brochure_id]);
                $action = 'güncellendi';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO brochures
                        (market_id, title, cover_image, start_date, end_date, show_on_homepage, analyzed_at, source_name, source_url, source_uid)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'amazon', ?, ?)
                ");
                $stmt->execute([
                    $amazon_market['id'],
                    $product['title'],
                    $cover_image,
                    $start_date ?: date('Y-m-d'),
                    $end_date ?: date('Y-m-d', strtotime('+14 days')),
                    $show_on_homepage,
                    $affiliate_url,
                    $source_uid,
                ]);
                $brochure_id = (int)$pdo->lastInsertId();
                $action = 'oluşturuldu';
            }

            $product_stmt = $pdo->prepare("
                INSERT INTO brochure_products
                    (brochure_id, page_number, product_name, price, product_url, product_image, rating, review_count, description, analyzed_at)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $product_stmt->execute([
                $brochure_id,
                $product['title'],
                $product['price'],
                $affiliate_url,
                $cover_image,
                $product['rating'],
                $product['review_count'],
                $product['description'],
            ]);

            $pdo->commit();
            $price_text = mi_price_label($product['price']) ?: 'Fiyat okunamadı';
            $success = 'Amazon ürün kartı ' . $action . ': <strong>' . htmlspecialchars($product['title']) . '</strong> (' . htmlspecialchars($price_text) . '). '
                . '<a href="../index.php?market=' . (int)$amazon_market['id'] . '" target="_blank" class="underline font-bold text-white ml-1">Sitede görüntüle</a>';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Broşür Oluşturucu - marketisleri.com</title>
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
<body class="bg-slate-950 text-slate-100 flex min-h-screen">
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
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span> Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span> Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
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

    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <div>
                <h1 class="font-title text-2xl font-bold text-white">Amazon Broşür Oluşturucu</h1>
                <p class="text-sm text-slate-400 mt-1">Affiliate ürün linkinden sitede görünen satın alma kartı oluşturur.</p>
            </div>
        </header>

        <div class="p-8 max-w-3xl w-full mx-auto space-y-6">
            <?php if ($success): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm p-4 rounded-2xl flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-400 mt-0.5">check_circle</span>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-2xl flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-400 mt-0.5">error</span>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
                <div class="border-b border-slate-800 pb-4">
                    <h2 class="font-title text-xl font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">shopping_basket</span>
                        Affiliate Linkinden Ürün Kartı Oluştur
                    </h2>
                    <p class="text-sm text-slate-400 mt-2">
                        Amazon ürün linkini ekleyin; sistem ürün adını, fiyatını, görselini, puanını ve kısa açıklamasını okuyup broşür listesinde satın alma butonlu kart olarak yayınlar.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Amazon Affiliate Ürün Linki *</label>
                    <input type="url" name="affiliate_url" required value="<?= htmlspecialchars($_POST['affiliate_url'] ?? '') ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3 outline-none transition text-sm"
                           placeholder="https://www.amazon.com.tr/dp/... veya https://amzn.to/...">
                    <p class="text-xs text-slate-500 mt-2">Aynı ürün tekrar eklenirse yeni kayıt açmak yerine mevcut Amazon kartı güncellenir.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlangıç Tarihi</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Bitiş Tarihi</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3 outline-none transition text-sm">
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="show_on_homepage" value="1" checked
                           class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-red-600 focus:ring-red-500 focus:ring-offset-slate-900">
                    Anasayfada ve Amazon market sayfasında göster
                </label>

                <div class="flex justify-end pt-4 border-t border-slate-800">
                    <button type="submit" name="import"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-3 rounded-xl transition shadow-lg shadow-red-600/10 inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">auto_fix_high</span>
                        Ürünü Çek ve Yayınla
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
