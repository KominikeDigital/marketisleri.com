<?php
// quick_fix.php - cPanel Git URL Güncelleyici ve Çakışma Temizleyici
header("Content-Type: text/html; charset=utf-8");

echo "<h2>cPanel Git Hızlı Onarım</h2>";

// 1. .git/config URL Düzeltme
$config_path = __DIR__ . '/.git/config';
if (file_exists($config_path)) {
    $content = file_get_contents($config_path);
    if (strpos($content, 'marketisler.com') !== false) {
        $new_content = str_replace('marketisler.com', 'marketisleri.com', $content);
        if (file_put_contents($config_path, $new_content) !== false) {
            echo "<p style='color: green;'>✔ Git deposu URL adresi güncellendi (marketisleri.com yapıldı).</p>";
        } else {
            echo "<p style='color: red;'>✘ .git/config dosyasına yazılamadı. Yetki sorunu olabilir.</p>";
        }
    } else {
        echo "<p style='color: blue;'>i Git depo URL adresi zaten güncel.</p>";
    }
} else {
    echo "<p style='color: red;'>✘ .git/config dosyası bulunamadı! Bu dizinde Git kurulu olduğundan emin olun.</p>";
}

// 2. Çakışma Çıkarabilecek Dosyaları Silme (Git Güncellemesinin önünü açar)
$files_to_delete = [
    'config.php',
    'admin/analyze_brochures.php',
    '.htaccess'
];

foreach ($files_to_delete as $file) {
    $path = __DIR__ . '/' . $file;
    if (is_file($path)) {
        if (@unlink($path)) {
            echo "<p style='color: green;'>✔ Çakışma önlemek için $file silindi.</p>";
        } else {
            echo "<p style='color: red;'>✘ $file silinemedi.</p>";
        }
    }
}

echo "<h3>Şimdi yapılacak işlem:</h3>";
echo "<p>cPanel'de <b>Git™ Version Control</b> sayfasına gidip <b>Update from Source</b> (veya Pull/Deploy) yapın. Siteniz ve API anahtarı alanınız tamamen güncellenmiş olarak geri gelecektir.</p>";
