<?php
// test_error.php - cPanel PHP Hata Gösterici ve Teşhis Scripti
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: text/html; charset=utf-8");
echo "<h2>marketisleri.com Sunucu Teşhis Raporu</h2>";

$files = [
    'config.php',
    'index.php',
    '.htaccess',
    'admin/analyze_brochures.php'
];

echo "<h3>Dosya Kontrolleri:</h3><ul>";
foreach ($files as $file) {
    $exists = file_exists($file);
    echo "<li><b>$file:</b> " . ($exists ? "<span style='color:green;'>MEVCUT</span>" : "<span style='color:red;'>EKSİK (Silinmiş veya yüklenememiş)</span>") . "</li>";
}
echo "</ul>";

if (file_exists('config.php')) {
    echo "<h3>Veritabanı ve Konfigürasyon Testi:</h3>";
    try {
        echo "config.php yükleniyor...<br>";
        require_once 'config.php';
        echo "<span style='color:green;'>✔ config.php başarıyla yüklendi!</span><br>";
        
        if (isset($pdo)) {
            echo "<span style='color:green;'>✔ Veritabanı bağlantısı aktif.</span><br>";
            $stmt = $pdo->query("SELECT value_text FROM settings WHERE key_name = 'gemini_api_key'");
            $key = $stmt ? $stmt->fetchColumn() : false;
            if ($key !== false) {
                echo "<span style='color:green;'>✔ Gemini API Key ayarı veritabanında mevcut: '" . htmlspecialchars($key) . "'</span><br>";
            } else {
                echo "<span style='color:orange;'>⚠ Gemini API Key veritabanında bulunamadı!</span><br>";
            }
        } else {
            echo "<span style='color:red;'>✘ \$pdo veritabanı değişkeni tanımlanmamış.</span><br>";
        }
    } catch (Throwable $e) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin-top:10px;'>";
        echo "<b>HATA OLUŞTU:</b><br>";
        echo "Dosya: " . $e->getFile() . " (Satır: " . $e->getLine() . ")<br>";
        echo "Mesaj: " . $e->getMessage();
        echo "</div>";
    }
} else {
    echo "<p style='color:red;'><b>ÖNEMLİ:</b> config.php sunucuda bulunamadığı için veritabanı testi yapılamadı. cPanel Git güncellemesini (Update from Source) başarıyla tamamladığınızdan emin olun.</p>";
}
