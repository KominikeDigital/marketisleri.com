<?php
require 'config.php';

// Check if run from web browser or CLI
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    header("Content-Type: text/plain; charset=utf-8");
}

echo "=== MARKET LOGOLARI VERİTABANI AKTARIMI BAŞLADI ===\n\n";

function generate_turkish_slug($text) {
    $find = array('Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ı', 'ö', 'ñ', 'â', 'ê', 'î', 'ô', 'û');
    $replace = array('c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'i', 'o', 'n', 'a', 'e', 'i', 'o', 'u');
    $text = str_replace($find, $replace, $text);
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', ' ', $text);
    $text = trim($text);
    $text = str_replace(' ', '-', $text);
    return strtolower($text);
}

$directory = __DIR__ . '/uploads/markets';
if (!is_dir($directory)) {
    die("Hata: uploads/markets dizini bulunamadı.\n");
}

$files = scandir($directory);
$inserted_count = 0;
$skipped_count = 0;

// Category ID 1 is "Süpermarket"
$category_id = 1;

foreach ($files as $file) {
    if ($file === '.' || $file === '..' || $file === '.DS_Store') {
        continue;
    }
    
    $path = $directory . '/' . $file;
    if (is_file($path)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $market_name = pathinfo($file, PATHINFO_FILENAME);
            $slug = generate_turkish_slug($market_name);
            
            // Check if market already exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ?");
            $check_stmt->execute([$slug]);
            $exists = $check_stmt->fetchColumn() > 0;
            
            if (!$exists) {
                try {
                    $description = $market_name . " İndirim ve Fırsatları";
                    $insert_stmt = $pdo->prepare("INSERT INTO markets (name, slug, logo, description, category_id) VALUES (?, ?, ?, ?, ?)");
                    $insert_stmt->execute([$market_name, $slug, $file, $description, $category_id]);
                    echo "Başarılı: '{$market_name}' eklendi. (Slug: {$slug}, Logo: {$file})\n";
                    $inserted_count++;
                } catch (PDOException $e) {
                    echo "Hata: '{$market_name}' eklenirken hata oluştu: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Pas geçildi: '{$market_name}' zaten kayıtlı. (Slug: {$slug})\n";
                $skipped_count++;
            }
        }
    }
}

echo "\n=== İŞLEM TAMAMLANDI ===\n";
echo "Eklenen Market Sayısı: {$inserted_count}\n";
echo "Zaten Kayıtlı Olan Sayısı: {$skipped_count}\n";
