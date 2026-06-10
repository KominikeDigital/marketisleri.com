<?php
// admin/merge_duplicate_markets.php
// Script to clean up duplicate markets in the database.

session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

require dirname(__DIR__) . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== MARKET DEDUPLICATION MERGE START ===\n\n";

$merges = [
    // target_id => [duplicate_ids...]
    86 => [7],     // Akyurt Süpermarket <- Akyurt
    87 => [10],    // Altun Market <- Altun
    88 => [11],    // Altunbilekler Market <- Altunbilekler
    89 => [13],    // Arden Market <- Arden
    90 => [14],    // Aypa Market <- Aypa
    91 => [15],    // Barış Gross Market <- Barış Gross
    93 => [19],    // Bizim Toptan <- Bizim Toptan Satış Mağazaları
    92 => [20],    // Biçen Market <- Biçen
    5  => [6, 70, 71], // Carrefoursa <- duplicates
    96 => [22],    // Damla Hipermarket <- Damla Hipermarketleri
    97 => [23],    // Egeşok Market <- Egeşok
    98 => [24],    // Esenlik Market <- Esenlik
    99 => [25],    // Essen Market <- Essen
    100 => [26],   // Etik Hipermarket <- Etik
    101 => [35],   // Hakmar <- Hakmar Alışveriş Merkezleri
    102 => [36],   // Hakmar Ekspres <- Hakmar Express
    116 => [47],   // Namlı Hipermarket <- Namlı Hipermarketleri
    103 => [49],   // Onur Market <- Onur
    117 => [50],   // Oruç Market <- Oruç
    105 => [57],   // Serra Grup Market <- Serra Grup
    107 => [58],   // Seyhanlar Market <- Seyhanlar
    108 => [60],   // Show Hipermarket <- Show
    109 => [62],   // Sultan Market <- Sultan
    110 => [64],   // Tema Market <- Tema Mağazalar Zinciri
    113 => [66],   // Yunus Market <- Yunus
    114 => [67],   // Zırhlı Toptan Market (zirhlı-toptan) <- Zırhlı Toptan Market (zirhli-toptan-market)
    2   => [68],   // A101 <- duplicate
    1   => [69],   // BİM <- duplicate
    4   => [72],   // Migros <- duplicate
    3   => [73],   // ŞOK <- duplicate
    94  => [75],   // Çağdaş Market <- Çağdaş
    95  => [76],   // Çağrı Market <- Çağrı
    104 => [78],   // Özhan Marketler <- Özhan
    111 => [80],   // Üçler Market <- Üçler
    115 => [82],   // Şehzade Market <- Şehzade
    106 => [83],   // Şevikoğlu Market <- Şevikoğlu
];

$pdo->beginTransaction();

try {
    foreach ($merges as $target_id => $dup_ids) {
        // Fetch target info
        $target_stmt = $pdo->prepare("SELECT * FROM markets WHERE id = ?");
        $target_stmt->execute([$target_id]);
        $target = $target_stmt->fetch();
        if (!$target) {
            echo "Warning: Target Market ID {$target_id} not found. Skipping.\n";
            continue;
        }

        echo "Merging into [ID: {$target_id}] {$target['name']} ({$target['slug']}):\n";

        foreach ($dup_ids as $dup_id) {
            $dup_stmt = $pdo->prepare("SELECT * FROM markets WHERE id = ?");
            $dup_stmt->execute([$dup_id]);
            $dup = $dup_stmt->fetch();
            if (!$dup) {
                echo "  - Duplicate ID {$dup_id} not found in DB. Skipping.\n";
                continue;
            }

            echo "  <- [ID: {$dup_id}] {$dup['name']} ({$dup['slug']})\n";

            // 1. Move brochures
            $update_brochures = $pdo->prepare("UPDATE brochures SET market_id = ? WHERE market_id = ?");
            $update_brochures->execute([$target_id, $dup_id]);
            $affected_brochures = $update_brochures->rowCount();
            if ($affected_brochures > 0) {
                echo "    * Moved {$affected_brochures} brochures.\n";
            }

            // 2. Move price alerts
            $update_alerts = $pdo->prepare("UPDATE price_alerts SET market_id = ? WHERE market_id = ?");
            $update_alerts->execute([$target_id, $dup_id]);
            $affected_alerts = $update_alerts->rowCount();
            if ($affected_alerts > 0) {
                echo "    * Moved {$affected_alerts} price alerts.\n";
            }

            // 3. Keep logo if duplicate has one and target does not
            if (empty($target['logo']) && !empty($dup['logo'])) {
                $update_logo = $pdo->prepare("UPDATE markets SET logo = ? WHERE id = ?");
                $update_logo->execute([$dup['logo'], $target_id]);
                $target['logo'] = $dup['logo'];
                echo "    * Copied logo: {$dup['logo']}\n";
            }

            // 4. Keep description if duplicate has one and target does not
            if (empty($target['description']) && !empty($dup['description'])) {
                $update_desc = $pdo->prepare("UPDATE markets SET description = ? WHERE id = ?");
                $update_desc->execute([$dup['description'], $target_id]);
                $target['description'] = $dup['description'];
                echo "    * Copied description.\n";
            }

            // 5. Keep scraper settings if duplicate has active ones and target does not
            if (empty($target['scraper_url']) && !empty($dup['scraper_url'])) {
                $update_scraper = $pdo->prepare("
                    UPDATE markets SET 
                        scraper_url = ?, scraper_container = ?, scraper_title = ?, 
                        scraper_cover = ?, scraper_detail_link = ?, scraper_page_image = ?, 
                        scraper_active = ? 
                    WHERE id = ?");
                $update_scraper->execute([
                    $dup['scraper_url'], $dup['scraper_container'], $dup['scraper_title'],
                    $dup['scraper_cover'], $dup['scraper_detail_link'], $dup['scraper_page_image'],
                    $dup['scraper_active'], $target_id
                ]);
                echo "    * Copied scraper settings.\n";
            }

            // 6. Delete duplicate
            $delete_dup = $pdo->prepare("DELETE FROM markets WHERE id = ?");
            $delete_dup->execute([$dup_id]);
            echo "    * Deleted duplicate record.\n";
        }
        echo "\n";
    }

    $pdo->commit();
    echo "=== DEDUPLICATION COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
}
