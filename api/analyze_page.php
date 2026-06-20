<?php
/**
 * api/analyze_page.php
 * Gemini Vision API ile broşür sayfasındaki ürünleri tespit eder.
 * 
 * GET/POST params:
 *   brochure_id (int) - required
 *   page_number  (int) - required (1-indexed)
 *   force        (bool) - tekrar analiz için (default false)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require dirname(__DIR__) . '/config.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function json_err(string $msg, int $code = 200): never {
    http_response_code(200); // Always return HTTP 200 to prevent web server (Apache/cPanel) from intercepting and returning custom HTML error pages
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function json_ok(array $data): never {
    echo json_encode(['success' => true, ...$data]);
    exit;
}

// ── Params ────────────────────────────────────────────────────────────────────
$brochure_id = (int)($_REQUEST['brochure_id'] ?? 0);
$page_number  = max(1, (int)($_REQUEST['page_number'] ?? 1));
$force        = filter_var($_REQUEST['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$brochure_id) json_err('brochure_id gerekli');

// ── Fetch page record (fall back to cover image for cover-only brochures) ────
$page_stmt = $pdo->prepare(
    "SELECT bp.*, b.market_id FROM brochure_pages bp 
     JOIN brochures b ON b.id = bp.brochure_id
     WHERE bp.brochure_id = ? AND bp.page_number = ?"
);
$page_stmt->execute([$brochure_id, $page_number]);
$page = $page_stmt->fetch();

// Fall back to cover image if no page record found
$using_cover = false;
if (!$page) {
    $cover_stmt = $pdo->prepare("SELECT id, market_id, cover_image FROM brochures WHERE id = ?");
    $cover_stmt->execute([$brochure_id]);
    $bro = $cover_stmt->fetch();
    if ($bro && !empty($bro['cover_image'])) {
        $using_cover = true;
        $page = [
            'brochure_id'  => $brochure_id,
            'page_number'  => 1,
            'market_id'    => $bro['market_id'],
            'image_path'   => null, // will use cover path below
            'cover_image'  => $bro['cover_image'],
        ];
        $page_number = 1;
    } else {
        json_err("Sayfa bulunamadı: broşür #{$brochure_id}, sayfa #{$page_number}", 404);
    }
}

// ── Check if already analyzed ─────────────────────────────────────────────────
if (!$force) {
    $existing = $pdo->prepare(
        "SELECT COUNT(*) FROM brochure_products WHERE brochure_id = ? AND page_number = ?"
    );
    $existing->execute([$brochure_id, $page_number]);
    if ($existing->fetchColumn() > 0) {
        try {
            $pages_count = (int)$pdo->query("SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = {$brochure_id}")->fetchColumn();
            $analyzed_count = (int)$pdo->query("SELECT COUNT(DISTINCT page_number) FROM brochure_products WHERE brochure_id = {$brochure_id}")->fetchColumn();
            if ($pages_count === 0 || ($pages_count > 0 && $analyzed_count >= $pages_count)) {
                $pdo->prepare("UPDATE brochures SET analyzed_at = COALESCE(analyzed_at, CURRENT_TIMESTAMP) WHERE id = ?")->execute([$brochure_id]);
            }
        } catch (Exception $e) { /* ignore */ }

        // Return cached results
        $products = $pdo->prepare(
            "SELECT * FROM brochure_products WHERE brochure_id = ? AND page_number = ? ORDER BY y_pct ASC, x_pct ASC"
        );
        $products->execute([$brochure_id, $page_number]);
        json_ok(['products' => $products->fetchAll(), 'cached' => true]);
    }
}

// ── Fetch Gemini API key ──────────────────────────────────────────────────────
$api_key_stmt = $pdo->query("SELECT value_text FROM settings WHERE key_name = 'gemini_api_key'");
$gemini_key   = $api_key_stmt ? trim((string)$api_key_stmt->fetchColumn()) : '';

// Allow override from config constant or env
if (defined('GEMINI_API_KEY') && GEMINI_API_KEY) $gemini_key = GEMINI_API_KEY;
if (!$gemini_key) $gemini_key = (string)getenv('GEMINI_API_KEY');

if (!$gemini_key) {
    json_err('Gemini API anahtarı ayarlanmamış. Admin > Ayarlar > Gemini API Key alanını doldurun.', 503);
}

// ── Load page image (page record or cover image) ─────────────────────────────
if ($using_cover) {
    $img_path = dirname(__DIR__) . '/uploads/brochures/' . $page['cover_image'];
} else {
    $img_path = dirname(__DIR__) . '/uploads/brochures/pages/' . $page['image_path'];
}
if (!file_exists($img_path)) {
    json_err('Sayfa görseli bulunamadı: ' . basename($img_path), 404);
}

$img_data    = base64_encode(file_get_contents($img_path));
$img_mime    = 'image/jpeg';
// Detect webp
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected = finfo_file($finfo, $img_path);
finfo_close($finfo);
if ($detected && str_contains($detected, 'webp')) $img_mime = 'image/webp';
if ($detected && str_contains($detected, 'png'))  $img_mime = 'image/png';

// ── Build Gemini prompt ───────────────────────────────────────────────────────
$prompt = <<<'PROMPT'
Bu bir Türkçe market broşürü sayfasıdır. Sayfadaki her ürünü tespit et.

Her ürün için aşağıdaki JSON formatında bilgi döndür:
- "name": Ürün adı (Türkçe, tam ve eksiksiz)  
- "price": Broşür fiyatı (sadece sayı, TL birimi olmadan). Bulamazsan null.
- "original_price": Üzeri çizili eski/eski fiyat (sadece sayı). Yoksa null.
- "unit": Birim bilgisi, örn: "kg", "adet", "litre", "paket", "2 kg", "500 gr" vb. Yoksa null.
- "x": Ürünün sol kenarının görüntü genişliğine göre yüzdesi (0-100 arası float)
- "y": Ürünün üst kenarının görüntü yüksekliğine göre yüzdesi (0-100 arası float)
- "w": Ürün alanının genişlik yüzdesi (0-100 arası float)
- "h": Ürün alanının yükseklik yüzdesi (0-100 arası float)

Cevabı SADECE geçerli bir JSON array olarak döndür, başka hiçbir metin ekleme.
Eğer hiç ürün yoksa boş array [] döndür.

Örnek format:
[
  {"name": "Tchibo Gold Filtre Kahve 250g", "price": 255.00, "original_price": 349.00, "unit": "250 gr", "x": 15.5, "y": 8.2, "w": 30.1, "h": 45.0},
  {"name": "Activia Yoğurt", "price": 89.90, "original_price": null, "unit": "4x125 gr", "x": 50.0, "y": 8.2, "w": 28.0, "h": 42.0}
]
PROMPT;

// ── Call Gemini API ───────────────────────────────────────────────────────────
$api_url  = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$gemini_key}";
$payload  = json_encode([
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => $img_mime, 'data' => $img_data]],
            ['text' => $prompt],
        ]
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,
        'maxOutputTokens' => 8192,
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err || $http_code !== 200) {
    json_err("Gemini API hatası (HTTP {$http_code}): " . ($curl_err ?: substr($response, 0, 300)), 502);
}

$gemini_data = json_decode($response, true);
$raw_text    = $gemini_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// ── Parse JSON from response ──────────────────────────────────────────────────
// Strip possible markdown code fences
$raw_text = trim(preg_replace('/^```(?:json)?\s*/i', '', $raw_text));
$raw_text = trim(preg_replace('/\s*```$/', '', $raw_text));

$products_raw = json_decode($raw_text, true);
if (!is_array($products_raw)) {
    json_err("Gemini yanıtı geçersiz JSON: " . substr($raw_text, 0, 200), 502);
}

// ── Delete old results for this page (if force or first time) ─────────────────
$pdo->prepare("DELETE FROM brochure_products WHERE brochure_id = ? AND page_number = ?")
    ->execute([$brochure_id, $page_number]);

// ── Insert products ───────────────────────────────────────────────────────────
$insert = $pdo->prepare(
    "INSERT INTO brochure_products 
     (brochure_id, page_number, product_name, price, original_price, unit, x_pct, y_pct, w_pct, h_pct)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$saved = [];
foreach ($products_raw as $p) {
    if (empty($p['name'])) continue;
    $name     = substr(trim((string)($p['name'] ?? '')), 0, 500);
    $price = null;
    if (isset($p['price']) && $p['price'] !== null && $p['price'] !== '') {
        $price_str = str_replace(',', '.', strval($p['price']));
        $price_str = preg_replace('/[^\d.]/', '', $price_str);
        if (is_numeric($price_str)) {
            $price = round((float)$price_str, 2);
        }
    }

    $orig = null;
    if (isset($p['original_price']) && $p['original_price'] !== null && $p['original_price'] !== '') {
        $orig_str = str_replace(',', '.', strval($p['original_price']));
        $orig_str = preg_replace('/[^\d.]/', '', $orig_str);
        if (is_numeric($orig_str)) {
            $orig = round((float)$orig_str, 2);
        }
    }
    $unit     = isset($p['unit']) && $p['unit'] !== '' ? substr(trim((string)$p['unit']), 0, 100) : null;
    $x = isset($p['x']) ? min(100, max(0, (float)$p['x'])) : null;
    $y = isset($p['y']) ? min(100, max(0, (float)$p['y'])) : null;
    $w = isset($p['w']) ? min(100, max(0, (float)$p['w'])) : null;
    $h = isset($p['h']) ? min(100, max(0, (float)$p['h'])) : null;

    $insert->execute([$brochure_id, $page_number, $name, $price, $orig, $unit, $x, $y, $w, $h]);
    $saved[] = [
        'id'             => (int)$pdo->lastInsertId(),
        'product_name'   => $name,
        'price'          => $price,
        'original_price' => $orig,
        'unit'           => $unit,
        'x_pct'          => $x,
        'y_pct'          => $y,
        'w_pct'          => $w,
        'h_pct'          => $h,
    ];
}

// Check if all pages are now analyzed and update brochures.analyzed_at if complete
try {
    $pages_count = (int)$pdo->query("SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = {$brochure_id}")->fetchColumn();
    $analyzed_count = (int)$pdo->query("SELECT COUNT(DISTINCT page_number) FROM brochure_products WHERE brochure_id = {$brochure_id}")->fetchColumn();
    if (($pages_count === 0 && count($saved) > 0) || ($pages_count > 0 && $analyzed_count >= $pages_count)) {
        $pdo->prepare("UPDATE brochures SET analyzed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$brochure_id]);
    }
} catch (Exception $e) { /* ignore */ }

json_ok(['products' => $saved, 'cached' => false, 'count' => count($saved)]);
