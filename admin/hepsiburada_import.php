<?php
require '../config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

function hepsiburada_ensure_market(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'hepsiburada' LIMIT 1");
    $market = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($market) {
        return $market;
    }

    $pdo->exec("INSERT INTO markets (name, slug, logo, description, category_id, is_popular, scraper_active)
                VALUES ('Hepsiburada', 'hepsiburada', NULL, 'Hepsiburada affiliate ürün fırsatları ve kampanyaları.', 3, 1, 0)");
    $stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'hepsiburada' LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function hepsiburada_curl(string $url, string $user_agent, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: ' . $user_agent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.7,en;q=0.6',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ],
    ]);

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $final_url = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err = curl_error($ch);

    $headers = is_string($raw) ? substr($raw, 0, $header_size) : '';
    $html = is_string($raw) ? substr($raw, $header_size) : '';

    return [
        'html' => ($code >= 200 && $code < 400 && is_string($html)) ? $html : '',
        'headers' => $headers,
        'code' => $code,
        'final_url' => $final_url ?: $url,
        'error' => $err,
    ];
}

function hepsiburada_abs_url(string $url, string $base): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $url = stripcslashes($url);
    if ($url === '') return '';
    if (str_starts_with($url, '//')) return 'https:' . $url;
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return $url;
    $parts = parse_url($base);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.hepsiburada.com');
    if (str_starts_with($url, '/')) return $origin . $url;
    return rtrim($origin, '/') . '/' . ltrim($url, '/');
}

function hepsiburada_query_value(string $url, string $key): string {
    $query = parse_url($url, PHP_URL_QUERY) ?: '';
    if ($query === '') return '';
    parse_str($query, $params);
    return isset($params[$key]) && is_scalar($params[$key]) ? trim((string)$params[$key]) : '';
}

function hepsiburada_header_location(string $headers): string {
    if (preg_match_all('/^Location:\s*(.+)$/mi', $headers, $matches)) {
        return trim((string)end($matches[1]));
    }
    return '';
}

function hepsiburada_extract_fallback_url(string $value): string {
    $fallback = hepsiburada_query_value($value, 'adj_fallback');
    if ($fallback !== '') {
        return html_entity_decode($fallback, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (preg_match('/adj_fallback=([^"&\s]+)/i', $value, $m)) {
        return html_entity_decode(urldecode($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

function hepsiburada_probe_fallback_url(string $url): string {
    $mobile_ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    $current = $url;

    for ($i = 0; $i < 5; $i++) {
        $fallback = hepsiburada_extract_fallback_url($current);
        if ($fallback !== '') return $fallback;

        $fetch = hepsiburada_curl($current, $mobile_ua, false);
        $fallback = hepsiburada_extract_fallback_url($fetch['final_url'] . "\n" . $fetch['headers'] . "\n" . $fetch['html']);
        if ($fallback !== '') return $fallback;

        $location = hepsiburada_header_location($fetch['headers']);
        if ($location === '') break;
        if (!str_starts_with($location, 'http://') && !str_starts_with($location, 'https://')) break;
        $current = hepsiburada_abs_url($location, $current);
    }

    return '';
}

function hepsiburada_fetch(string $url): array {
    $desktop_ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    $primary = hepsiburada_curl($url, $desktop_ua, true);
    $fallback_url = hepsiburada_extract_fallback_url($primary['final_url']) ?: hepsiburada_probe_fallback_url($url);
    $product_url = $fallback_url ?: $primary['final_url'];

    if ($primary['html'] !== '' && !hepsiburada_is_blocked_html($primary['html']) && !hepsiburada_is_redirect_html($primary['html'])) {
        $primary['product_url'] = $product_url;
        return $primary;
    }

    if ($fallback_url !== '') {
        $fallback = hepsiburada_curl($fallback_url, $desktop_ua, true);
        $fallback['product_url'] = $fallback_url;
        $fallback['fallback_url'] = $fallback_url;
        return $fallback;
    }

    $primary['product_url'] = $product_url;
    return $primary;
}

function hepsiburada_is_blocked_html(string $html): bool {
    return stripos($html, 'Access Denied') !== false
        || stripos($html, 'akamai') !== false
        || stripos($html, 'Forbidden') !== false;
}

function hepsiburada_is_redirect_html(string $html): bool {
    return stripos($html, 'Proceed to the app store') !== false
        || stripos($html, 'apps.apple.com/app/id481035064') !== false
        || stripos($html, 'window.location.href') !== false && stripos($html, 'Download App') !== false;
}

function hepsiburada_extract_sku(string $url, string $html = ''): string {
    $source = urldecode($url . "\n" . $html);
    $patterns = [
        '~(?:-p-|[?&]sku=)(HBCV[0-9A-Z]+|[A-Z0-9]{8,})(?:[/?#&]|$)~i',
        '~["\']sku["\']\s*:\s*["\']([^"\']{6,})["\']~i',
        '~["\']productId["\']\s*:\s*["\']?([^,"\'}\s]{6,})~i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $source, $m)) {
            return strtoupper(trim($m[1]));
        }
    }
    return '';
}

function hepsiburada_title_from_url(string $url): string {
    $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: ''), '/');
    $last = urldecode(basename($path));
    $last = preg_replace('~-p-[A-Z0-9]+$~i', '', $last) ?? $last;
    $last = preg_replace('/[-_]+/u', ' ', $last) ?? $last;
    $last = preg_replace('/\s+/', ' ', $last) ?? $last;
    return trim(mb_convert_case($last, MB_CASE_TITLE, 'UTF-8'));
}

function hepsiburada_fallback_product(string $requested_url, string $final_url, string $html = ''): array {
    $title = hepsiburada_title_from_url($final_url);
    if ($title === '') {
        $title = 'Hepsiburada Ürün Fırsatı';
    }

    $sku = hepsiburada_extract_sku($final_url, $html) ?: hepsiburada_extract_sku($requested_url, $html);

    return [
        'sku' => $sku,
        'title' => $title,
        'price' => null,
        'image_url' => '',
        'image_urls' => [],
        'rating' => '',
        'review_count' => '',
        'description' => '',
        'affiliate_url' => $requested_url,
        'final_url' => $final_url,
        'partial' => true,
    ];
}

function hepsiburada_canonical_product_url(string $url): string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return $url;
    $scheme = $parts['scheme'] ?? 'https';
    $path = $parts['path'] ?? '';
    return $scheme . '://' . $parts['host'] . $path;
}

function hepsiburada_reader_fetch(string $url): array {
    $reader_url = 'https://r.jina.ai/' . $url;
    $ch = curl_init($reader_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (compatible; marketisleri.com/1.0)',
        ],
    ]);

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $data = is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

    return [
        'code' => $code,
        'error' => $err,
        'title' => trim((string)($data['title'] ?? '')),
        'description' => trim((string)($data['description'] ?? '')),
        'content' => trim((string)($data['content'] ?? '')),
    ];
}

function hepsiburada_reader_fetch_html(string $url): array {
    $reader_url = 'https://r.jina.ai/' . $url;
    $ch = curl_init($reader_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'X-Return-Format: html',
            'User-Agent: Mozilla/5.0 (compatible; marketisleri.com/1.0)',
        ],
    ]);

    $content = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    return [
        'code' => $code,
        'error' => $err,
        'content' => is_string($content) ? $content : '',
    ];
}

function hepsiburada_markdown_clean(string $text): string {
    $text = preg_replace('/!\[[^\]]*]\([^)]+\)/u', ' ', $text) ?? $text;
    $text = preg_replace('/\[([^\]]+)]\([^)]+\)/u', '$1', $text) ?? $text;
    $text = preg_replace('/[*_`#>]+/u', ' ', $text) ?? $text;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
}

function hepsiburada_reader_images(string $content): array {
    $images = [];
    if (preg_match_all('~https?:\\\\?/\\\\?/productimages\.hepsiburada\.net[^\s)\]"<>\\\\]+~i', $content, $matches)) {
        foreach ($matches[0] as $url) {
            $url = html_entity_decode(str_replace('\/', '/', $url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = rtrim($url, '.,');
            $url = preg_replace('~/format:[a-z0-9]+$~i', '', $url) ?? $url;
            if (str_contains($url, '/48-64/') || str_contains($url, '/80/') || str_contains($url, '/40/')) {
                continue;
            }
            $images[] = $url;
        }
    }

    usort($images, function($a, $b) {
        $score = fn($url) => (str_contains($url, '/424-600/') ? 30 : 0)
            + (str_contains($url, '/550/') ? 20 : 0)
            + (str_contains($url, '/375/') ? 10 : 0);
        return $score($b) <=> $score($a);
    });

    return array_values(array_unique($images));
}

function hepsiburada_reader_price(string $content): ?float {
    $lines = preg_split('/\R/u', $content) ?: [];
    $start = 0;
    foreach ($lines as $i => $line) {
        if (str_starts_with(trim($line), '# ') && stripos($line, 'Hepsiburada') === false) {
            $start = $i;
        }
    }

    for ($i = $start; $i < min(count($lines), $start + 90); $i++) {
        $line = trim($lines[$i]);
        if ($line === '') continue;
        if (preg_match('/(peşin fiyatına|ek garanti|koruma paketi|satıcı puanı)/iu', $line)) continue;
        if (preg_match('/\b\d+\s*x\s*\d/u', $line)) continue;
        if (preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2})\s*TL/u', $line, $m)) {
            return mi_parse_price($m[1]);
        }
    }

    if (preg_match('/(\d{1,3}(?:\.\d{3})*,\d{2})\s*TL/u', $content, $m)) {
        return mi_parse_price($m[1]);
    }

    return null;
}

function hepsiburada_parse_reader_product(array $reader, string $requested_url, string $final_url): array {
    $content = (string)($reader['content'] ?? '');
    $title = '';

    if (preg_match_all('/^#\s+(.+)$/mu', $content, $matches)) {
        foreach ($matches[1] as $candidate) {
            $clean = hepsiburada_markdown_clean($candidate);
            if ($clean !== '' && stripos($clean, 'Hepsiburada') === false && stripos($clean, 'Değerlendirmeleri') === false) {
                $title = $clean;
            }
        }
    }

    if ($title === '') {
        $title = preg_replace('/\s+Fiyatı\s*$/iu', '', trim((string)($reader['title'] ?? ''))) ?: hepsiburada_title_from_url($final_url);
    }

    $images = hepsiburada_reader_images($content);
    $review_count = '';
    if (preg_match('/(?:Tüm\s+)?Değerlendirmeler\s*\((\d+)\)/iu', $content, $m)
        || preg_match('/\*\*(\d+)\*\*\s*Değerlendirme/iu', $content, $m)) {
        $review_count = $m[1];
    }

    $rating = '';
    if (preg_match('/(\d+(?:[,.]\d+)?)\s*\/\s*5/u', $content, $m)) {
        $rating = str_replace(',', '.', $m[1]);
    }

    return [
        'sku' => hepsiburada_extract_sku($final_url, $content) ?: hepsiburada_extract_sku($requested_url, $content),
        'title' => $title ?: 'Hepsiburada Ürün Fırsatı',
        'price' => hepsiburada_reader_price($content),
        'image_url' => $images[0] ?? '',
        'image_urls' => $images,
        'rating' => $rating,
        'review_count' => $review_count,
        'description' => mb_substr(trim((string)($reader['description'] ?? '')), 0, 280, 'UTF-8'),
        'affiliate_url' => $requested_url,
        'final_url' => $final_url,
        'partial' => false,
    ];
}

function hepsiburada_merge_product_data(array $primary, array $fallback): array {
    foreach (['sku', 'title', 'rating', 'review_count', 'description'] as $field) {
        if (trim((string)($primary[$field] ?? '')) === '' && trim((string)($fallback[$field] ?? '')) !== '') {
            $primary[$field] = $fallback[$field];
        }
    }

    if (($primary['price'] ?? null) === null && ($fallback['price'] ?? null) !== null) {
        $primary['price'] = $fallback['price'];
    }

    if (empty($primary['image_url']) && !empty($fallback['image_url'])) {
        $primary['image_url'] = $fallback['image_url'];
    }

    $images = array_merge($primary['image_urls'] ?? [], $fallback['image_urls'] ?? []);
    $primary['image_urls'] = array_values(array_unique(array_filter($images)));
    if (($primary['image_url'] ?? '') === '' && $primary['image_urls']) {
        $primary['image_url'] = $primary['image_urls'][0];
    }

    $primary['partial'] = empty($primary['image_urls']) || ($primary['price'] ?? null) === null;
    return $primary;
}

function hepsiburada_meta(DOMXPath $xp, string $name): string {
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

function hepsiburada_first_text(DOMXPath $xp, array $queries): string {
    foreach ($queries as $query) {
        $node = $xp->query($query)->item(0);
        if ($node && trim($node->textContent) !== '') {
            return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
    }
    return '';
}

function hepsiburada_jsonld_products(DOMXPath $xp): array {
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

function hepsiburada_jsonld_value(array $products, string $field): string {
    foreach ($products as $product) {
        if (!empty($product[$field])) {
            $value = $product[$field];
            if (is_array($value)) {
                $value = reset($value);
                if (is_array($value)) {
                    $value = $value['url'] ?? $value['contentUrl'] ?? '';
                }
            }
            if (is_string($value) || is_numeric($value)) {
                return trim((string)$value);
            }
        }
    }
    return '';
}

function hepsiburada_jsonld_offer_price(array $products): ?float {
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

function hepsiburada_jsonld_rating(array $products): array {
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

function hepsiburada_collect_images($value, string $base, array &$images): void {
    if (is_string($value) || is_numeric($value)) {
        $url = hepsiburada_abs_url((string)$value, $base);
        if ($url !== '' && str_contains($url, 'productimages.hepsiburada.net')) {
            $images[] = $url;
        }
        return;
    }

    if (!is_array($value)) return;
    foreach (['url', 'contentUrl', 'imageUrl', 'src'] as $field) {
        if (!empty($value[$field])) {
            hepsiburada_collect_images($value[$field], $base, $images);
        }
    }
    foreach ($value as $child) {
        if (is_array($child)) {
            hepsiburada_collect_images($child, $base, $images);
        }
    }
}

function hepsiburada_jsonld_images(array $products, string $base): array {
    $images = [];
    foreach ($products as $product) {
        if (!empty($product['image'])) {
            hepsiburada_collect_images($product['image'], $base, $images);
        }
    }
    return array_values(array_unique($images));
}

function hepsiburada_html_images(DOMXPath $xp, string $html, string $base): array {
    $images = [];
    foreach ($xp->query('//img[@src or @data-src]') as $img) {
        if (!$img instanceof DOMElement) continue;
        foreach (['data-src', 'src'] as $attr) {
            $value = trim($img->getAttribute($attr));
            if ($value !== '') {
                hepsiburada_collect_images($value, $base, $images);
            }
        }
    }

    if (preg_match_all('~https?:\\\\?/\\\\?/productimages\.hepsiburada\.net[^"\')\s\\\\]+~i', $html, $matches)) {
        foreach ($matches[0] as $url) {
            hepsiburada_collect_images(str_replace('\/', '/', $url), $base, $images);
        }
    }

    return array_values(array_unique($images));
}

function hepsiburada_parse_product(string $html, string $requested_url, string $final_url): array {
    if ($html === '' || hepsiburada_is_blocked_html($html) || hepsiburada_is_redirect_html($html)) {
        return hepsiburada_fallback_product($requested_url, $final_url, $html);
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xp = new DOMXPath($doc);
    $json_products = hepsiburada_jsonld_products($xp);

    $title = hepsiburada_first_text($xp, [
        '//h1[@data-test-id="title"]',
        '//h1[contains(@class, "product-name")]',
        '//h1',
    ]);
    if ($title === '') {
        $title = hepsiburada_jsonld_value($json_products, 'name')
            ?: hepsiburada_meta($xp, 'og:title')
            ?: hepsiburada_meta($xp, 'twitter:title')
            ?: hepsiburada_title_from_url($final_url);
    }
    $title = trim(preg_replace('/\s*\|\s*Hepsiburada.*$/iu', '', $title));

    $price = hepsiburada_jsonld_offer_price($json_products);
    if ($price === null) {
        $price_text = hepsiburada_meta($xp, 'product:price:amount') ?: hepsiburada_first_text($xp, [
            '//*[@data-test-id="price-current-price"]',
            '//*[contains(@class, "current-price")]',
            '//*[contains(@class, "price")]',
        ]);
        $price = mi_parse_price($price_text);
    }

    $image_urls = array_values(array_unique(array_filter(array_merge(
        hepsiburada_jsonld_images($json_products, $final_url),
        [hepsiburada_meta($xp, 'og:image')],
        hepsiburada_html_images($xp, $html, $final_url)
    ))));
    $image_urls = array_map(fn($url) => hepsiburada_abs_url((string)$url, $final_url), $image_urls);
    $image_urls = array_values(array_unique(array_filter($image_urls)));
    $image = $image_urls[0] ?? '';

    $rating_data = hepsiburada_jsonld_rating($json_products);
    $rating = $rating_data['rating'];
    if ($rating === '' && preg_match('/"ratingValue"\s*:\s*"?(\d+(?:[.,]\d+)?)"?/i', $html, $m)) {
        $rating = str_replace(',', '.', $m[1]);
    }

    $review_count = $rating_data['review_count'];
    if ($review_count === '' && preg_match('/"reviewCount"\s*:\s*"?([0-9.]+)"?/i', $html, $m)) {
        $review_count = $m[1];
    }

    $description = hepsiburada_jsonld_value($json_products, 'description') ?: hepsiburada_meta($xp, 'description');
    $description = mb_substr(trim(preg_replace('/\s+/', ' ', $description)), 0, 280, 'UTF-8');

    $sku = hepsiburada_extract_sku($final_url, $html) ?: hepsiburada_extract_sku($requested_url, $html);
    if ($title === '') {
        return hepsiburada_fallback_product($requested_url, $final_url, $html);
    }

    return [
        'sku' => $sku,
        'title' => $title,
        'price' => $price,
        'image_url' => $image,
        'image_urls' => $image_urls,
        'rating' => $rating,
        'review_count' => $review_count,
        'description' => $description,
        'affiliate_url' => $requested_url,
        'final_url' => $final_url,
    ];
}

function hepsiburada_download_image(string $url, string $source_key): ?string {
    $safe_key = preg_replace('/[^a-z0-9]+/i', '-', strtolower($source_key)) ?: md5($url);
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }

    $file_name = 'hepsiburada-' . $safe_key . '-' . substr(md5($url), 0, 8) . '-' . time() . '.' . $ext;
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
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Referer: https://www.hepsiburada.com/',
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

function hepsiburada_source_uid(array $product): string {
    if (!empty($product['sku'])) {
        return 'hepsiburada:' . $product['sku'];
    }
    return 'hepsiburada:' . md5(($product['final_url'] ?? '') . '|' . ($product['affiliate_url'] ?? ''));
}

$hepsiburada_market = hepsiburada_ensure_market($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['import'])) {
    $affiliate_url = trim((string)($_POST['affiliate_url'] ?? ''));
    $start_date = trim((string)($_POST['start_date'] ?? date('Y-m-d')));
    $end_date = trim((string)($_POST['end_date'] ?? date('Y-m-d', strtotime('+14 days'))));
    $show_on_homepage = isset($_POST['show_on_homepage']) ? 1 : 0;

    if ($affiliate_url === '' || !filter_var($affiliate_url, FILTER_VALIDATE_URL)) {
        $error = 'Lütfen geçerli bir Hepsiburada affiliate ürün linki girin.';
    } else {
        try {
            $fetch = hepsiburada_fetch($affiliate_url);
            $product_url = $fetch['product_url'] ?? $fetch['final_url'];
            $product = hepsiburada_parse_product($fetch['html'], $affiliate_url, $product_url);

            if (($product['price'] ?? null) === null || empty($product['image_urls'])) {
                $reader_url = hepsiburada_canonical_product_url($product_url);
                $reader = hepsiburada_reader_fetch($reader_url);
                if (($reader['content'] ?? '') !== '') {
                    $reader_product = hepsiburada_parse_reader_product($reader, $affiliate_url, $reader_url);
                    $product = hepsiburada_merge_product_data($product, $reader_product);
                }
            }

            if (count($product['image_urls'] ?? []) < 5) {
                $reader_url = hepsiburada_canonical_product_url($product_url);
                $reader_html = hepsiburada_reader_fetch_html($reader_url);
                if (($reader_html['content'] ?? '') !== '') {
                    $html_product = hepsiburada_parse_reader_product([
                        'title' => $product['title'] ?? '',
                        'description' => $product['description'] ?? '',
                        'content' => $reader_html['content'],
                    ], $affiliate_url, $reader_url);
                    $product = hepsiburada_merge_product_data($product, $html_product);
                }
            }

            $source_uid = hepsiburada_source_uid($product);

            $stored_images = [];
            $download_candidates = array_values(array_filter($product['image_urls'] ?: [$product['image_url']]));
            foreach (array_slice($download_candidates, 0, 5) as $image_url) {
                $stored_images[] = hepsiburada_download_image($image_url, $product['sku'] ?: md5($image_url)) ?: $image_url;
            }
            $stored_images = array_values(array_unique(array_filter($stored_images)));
            $cover_image = $stored_images[0] ?? '';

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
                        source_name = 'hepsiburada', source_url = ?, source_uid = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $hepsiburada_market['id'],
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
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'hepsiburada', ?, ?)
                ");
                $stmt->execute([
                    $hepsiburada_market['id'],
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
                    (brochure_id, page_number, product_name, price, product_url, product_image, product_images, rating, review_count, description, analyzed_at)
                VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $product_stmt->execute([
                $brochure_id,
                $product['title'],
                $product['price'],
                $affiliate_url,
                $cover_image,
                json_encode($stored_images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $product['rating'],
                $product['review_count'],
                $product['description'],
            ]);

            $pdo->commit();
            $price_text = mi_price_label($product['price']) ?: 'Fiyat okunamadı';
            $success = 'Hepsiburada ürün kartı ' . $action . ': <strong>' . htmlspecialchars($product['title']) . '</strong> (' . htmlspecialchars($price_text) . '). '
                . '<a href="../index.php?market=' . (int)$hepsiburada_market['id'] . '" target="_blank" class="underline font-bold text-white ml-1">Sitede görüntüle</a>';
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
    <title>Hepsiburada Broşür Oluşturucu - marketisleri.com</title>
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
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="hepsiburada_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">local_mall</span> Hepsiburada Broşür Ekle
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
                <h1 class="font-title text-2xl font-bold text-white">Hepsiburada Broşür Oluşturucu</h1>
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
                        <span class="material-symbols-outlined text-amber-500">local_mall</span>
                        Affiliate Linkinden Ürün Kartı Oluştur
                    </h2>
                    <p class="text-sm text-slate-400 mt-2">
                        Hepsiburada affiliate linkini ekleyin; sistem ürün adını, fiyatını, görsellerini ve puanını okuyup broşür listesinde satın alma butonlu kart olarak yayınlar.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Hepsiburada Affiliate Ürün Linki *</label>
                    <input type="url" name="affiliate_url" required value="<?= htmlspecialchars($_POST['affiliate_url'] ?? '') ?>"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-3 outline-none transition text-sm"
                           placeholder="https://app.hb.biz/... veya https://www.hepsiburada.com/...">
                    <p class="text-xs text-slate-500 mt-2">Aynı ürün tekrar eklenirse yeni kayıt açmak yerine mevcut Hepsiburada kartı güncellenir.</p>
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
                    Anasayfada ve Hepsiburada market sayfasında göster
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
