<?php
// admin/check_db_public.php - Public Database Diagnostic with token
require '../config.php';

if (($_GET['token'] ?? '') !== 'antigravity_secret_123') {
    die("Yetkisiz erişim.");
}

header("Content-Type: application/json; charset=utf-8");

$result = [
    'driver' => $active_db_driver,
    'host' => $db_host,
    'name' => $db_name,
    'columns' => [],
    'markets' => [],
    'brochures_count' => 0
];

try {
    // 1. Columns
    if ($active_db_driver === 'mysql') {
        $q = $pdo->query("DESCRIBE markets");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $result['columns'][] = $row['Field'];
        }
    } else {
        $q = $pdo->query("PRAGMA table_info(markets)");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $result['columns'][] = $row['name'];
        }
    }
    
    // 2. Markets
    $q = $pdo->query("SELECT id, name, slug, scraper_url, scraper_active, scraper_container FROM markets");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $result['markets'][] = $row;
    }
    
    // 3. Count
    $result['brochures_count'] = (int) $pdo->query("SELECT COUNT(*) FROM brochures")->fetchColumn();
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
