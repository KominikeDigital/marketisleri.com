<?php
// admin/debug_db.php - Production Database Diagnostic
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

header("Content-Type: text/plain; charset=utf-8");
echo "=== PRODUCTION DATABASE DIAGNOSTIC ===\n\n";
echo "Driver: " . $active_db_driver . "\n";
echo "DB Host: " . $db_host . "\n";
echo "DB Name: " . $db_name . "\n\n";

try {
    // 1. Check columns of markets table
    echo "--- markets columns ---\n";
    if ($active_db_driver === 'mysql') {
        $q = $pdo->query("DESCRIBE markets");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . "\n";
        }
    } else {
        $q = $pdo->query("PRAGMA table_info(markets)");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            echo "Field: " . $row['name'] . " | Type: " . $row['type'] . "\n";
        }
    }
    
    // 2. Check markets rows
    echo "\n--- markets rows ---\n";
    $q = $pdo->query("SELECT id, name, slug, scraper_url, scraper_active FROM markets");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Slug: " . $row['slug'] . " | Scraper Active: " . $row['scraper_active'] . " | Scraper URL: " . $row['scraper_url'] . "\n";
    }
    
    // 3. Count brochures
    echo "\n--- brochure count ---\n";
    $count = $pdo->query("SELECT COUNT(*) FROM brochures")->fetchColumn();
    echo "Total Brochures: " . $count . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
