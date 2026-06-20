<?php
/**
 * api/price_compare.php
 * Belirli bir ürün adına göre aktif broşürlerdeki benzer ürün fiyatlarını karşılaştırır.
 *
 * GET params:
 *   product_name (string) - ürün adı
 *   exclude_brochure_id (int) - hariç tutulacak broşür (mevcut broşür)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require dirname(__DIR__) . '/config.php';

$product_name = trim((string)($_GET['product_name'] ?? ''));
$exclude_brochure = (int)($_GET['exclude_brochure_id'] ?? 0);

if ($product_name === '') {
    echo json_encode(['success' => false, 'error' => 'product_name gerekli']);
    exit;
}

$today = date('Y-m-d');

$raw_words = preg_split('/\s+/u', $product_name) ?: [];
$raw_words = array_map(fn($w) => trim($w, " \t\n\r\0\x0B.,;:()[]{}'\""), $raw_words);
$raw_words = array_filter($raw_words, fn($w) => mb_strlen($w, 'UTF-8') >= 3);
$normalized_words = mi_product_tokens($product_name);
$like_words = array_values(array_unique(array_slice(array_merge($raw_words, $normalized_words), 0, 10)));

$base_select = "SELECT
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
        WHERE b.start_date <= ?
          AND b.end_date >= ?
          AND bp.price IS NOT NULL";

$params = [$today, $today];
if ($exclude_brochure > 0) {
    $base_select .= " AND bp.brochure_id != ?";
    $params[] = $exclude_brochure;
}

$rows = [];

if ($like_words) {
    $conditions = [];
    $like_params = [];
    foreach ($like_words as $word) {
        $conditions[] = "bp.product_name LIKE ?";
        $like_params[] = '%' . $word . '%';
    }

    $sql = $base_select . " AND (" . implode(' OR ', $conditions) . ")
            ORDER BY b.end_date ASC, bp.price ASC
            LIMIT 800";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, $like_params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Accent-sensitive SQLite or very specific product names can miss candidates.
// Fall back to a bounded active-product pool and score in PHP.
if (count($rows) < 5) {
    $sql = $base_select . " ORDER BY b.end_date ASC, bp.id DESC LIMIT 5000";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fallback_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $seen = [];
        foreach (array_merge($rows, $fallback_rows) as $row) {
            $seen[$row['id']] = $row;
        }
        $rows = array_values($seen);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$results = [];
foreach ($rows as $row) {
    $score = mi_product_match_score($product_name, (string)$row['product_name']);
    if ($score < 50) {
        continue;
    }

    $row['match_score'] = $score;
    $row['match_label'] = $score >= 82 ? 'Yüksek uyum' : ($score >= 65 ? 'Orta uyum' : 'Yakın eşleşme');
    $row['price'] = (float)$row['price'];
    if ($row['original_price'] !== null) {
        $row['original_price'] = (float)$row['original_price'];
    }
    $results[] = $row;
}

usort($results, function($a, $b) {
    if ($a['price'] == $b['price']) {
        return $b['match_score'] <=> $a['match_score'];
    }
    return $a['price'] <=> $b['price'];
});

$deduped = [];
foreach ($results as $row) {
    $key = $row['brochure_id'] . '|' . mi_compact_key($row['product_name']) . '|' . number_format((float)$row['price'], 2, '.', '');
    if (isset($deduped[$key])) {
        continue;
    }
    $deduped[$key] = $row;
    if (count($deduped) >= 20) {
        break;
    }
}
$results = array_values($deduped);

foreach ($results as &$r) {
    $logo_file = dirname(__DIR__) . '/uploads/markets/' . $r['market_logo'];
    $r['market_logo_url'] = !empty($r['market_logo']) && file_exists($logo_file)
        ? 'uploads/markets/' . $r['market_logo']
        : null;
    $r['brochure_url'] = 'viewer.php?id=' . $r['brochure_id'];
    $days_left = (int)round((strtotime($r['end_date']) - time()) / 86400);
    $r['days_left'] = max(0, $days_left);
}
unset($r);

echo json_encode([
    'success' => true,
    'results' => $results,
    'query' => $product_name,
    'search_tokens' => $like_words,
    'candidate_count' => count($rows),
], JSON_UNESCAPED_UNICODE);
