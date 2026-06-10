<?php
/**
 * api/price_compare.php
 * Belirli bir ürün adına göre aktif broşürlerdeki fiyatları karşılaştırır.
 *
 * GET params:
 *   product_name (string) - ürün adı
 *   exclude_brochure_id (int) - hariç tutulacak broşür (mevcut broşür)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require dirname(__DIR__) . '/config.php';

$product_name       = trim((string)($_GET['product_name'] ?? ''));
$exclude_brochure   = (int)($_GET['exclude_brochure_id'] ?? 0);

if (!$product_name) {
    echo json_encode(['success' => false, 'error' => 'product_name gerekli']);
    exit;
}

$today = date('Y-m-d');

// Fuzzy search: normalize ismi ve LIKE ile ara
// Türkçe karakterleri basitleştirmez; veritabanı utf8mb4_unicode_ci zaten case-insensitive eşleştirir
$terms  = array_filter(explode(' ', preg_replace('/\s+/', ' ', $product_name)));
$words  = array_slice($terms, 0, 4); // En fazla 4 kelime al, kısa kelimeleri atla
$words  = array_filter($words, fn($w) => mb_strlen($w) >= 3);

if (empty($words)) {
    // Tüm cümleyi LIKE ile dene
    $words = [$product_name];
}

// Her kelime için LIKE koşulu oluştur
$conditions = array_map(fn($w) => "bp.product_name LIKE ?", $words);
$where      = implode(' AND ', $conditions);
$params     = array_map(fn($w) => "%{$w}%", $words);

// Hariç tutulan broşür ve süresi dolmuş broşürler dışında
$exclude_sql = $exclude_brochure ? "AND bp.brochure_id != {$exclude_brochure}" : '';

$sql = "SELECT 
            bp.id,
            bp.product_name,
            bp.price,
            bp.original_price,
            bp.unit,
            bp.x_pct, bp.y_pct,
            bp.brochure_id,
            bp.page_number,
            b.title AS brochure_title,
            b.start_date,
            b.end_date,
            m.name AS market_name,
            m.slug AS market_slug,
            m.logo AS market_logo
        FROM brochure_products bp
        JOIN brochures b ON b.id = bp.brochure_id
        JOIN markets m ON m.id = b.market_id
        WHERE ({$where})
          AND b.start_date <= '{$today}'
          AND b.end_date   >= '{$today}'
          {$exclude_sql}
          AND bp.price IS NOT NULL
        ORDER BY bp.price ASC
        LIMIT 20";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Add logo URL
foreach ($results as &$r) {
    $logo_file = __DIR__ . '/../uploads/markets/' . $r['market_logo'];
    $r['market_logo_url'] = file_exists($logo_file)
        ? 'uploads/markets/' . $r['market_logo']
        : null;
    $r['brochure_url'] = 'viewer.php?id=' . $r['brochure_id'];
    // Days left
    $days_left = (int)round((strtotime($r['end_date']) - time()) / 86400);
    $r['days_left'] = max(0, $days_left);
}
unset($r);

echo json_encode(['success' => true, 'results' => $results, 'query' => $product_name]);
