<?php
// admin/test_key.php - Gemini API Key Test Script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__) . '/config.php';

// Auth check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    die("Oturum kapalı. Lütfen önce admin paneline giriş yapın.");
}

header("Content-Type: text/html; charset=utf-8");
echo "<h2>Gemini API Anahtarı Doğrulama Testi</h2>";

// Fetch key from settings
$api_key_stmt = $pdo->query("SELECT value_text FROM settings WHERE key_name = 'gemini_api_key'");
$gemini_key   = $api_key_stmt ? trim((string)$api_key_stmt->fetchColumn()) : '';

if (!$gemini_key) {
    die("<p style='color:red;'><b>HATA:</b> Veritabanında kayıtlı bir Gemini API anahtarı bulunamadı. Lütfen önce anahtarı kaydedin.</p>");
}

echo "<p><b>Kayıtlı Anahtar:</b> <code style='background:#f1f5f9;padding:3px 6px;border-radius:4px;'>" . htmlspecialchars(substr($gemini_key, 0, 8) . str_repeat('•', 24) . substr($gemini_key, -6)) . "</code></p>";
echo "<p>Google API sunucularına test isteği gönderiliyor...</p>";

$api_url  = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$gemini_key}";
$payload  = json_encode([
    'contents' => [[
        'parts' => [['text' => 'Merhaba, bu bir test mesajıdır. Tek kelimeyle "Aktif" de.']]
    ]]
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

echo "<h3>Test Sonucu:</h3>";
if ($curl_err) {
    echo "<p style='color:red;'><b>CURL Bağlantı Hatası:</b> $curl_err</p>";
} else {
    echo "<p><b>HTTP Durum Kodu:</b> " . ($http_code === 200 ? "<span style='color:green;font-weight:bold;'>200 OK (Başarılı)</span>" : "<span style='color:red;font-weight:bold;'>$http_code (Hata)</span>") . "</p>";
    echo "<b>Google API Yanıtı:</b>";
    echo "<pre style='background:#0f172a;color:#cbd5e1;padding:15px;border-radius:8px;overflow:auto;font-family:monospace;font-size:12px;margin-top:5px;'>" . htmlspecialchars($response) . "</pre>";
    
    $data = json_decode($response, true);
    if ($http_code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<h3 style='color:green;'>✔ Gemini API Anahtarınız Aktif ve Sorunsuz Çalışıyor!</h3>";
    } else {
        echo "<h3 style='color:red;'>✘ Gemini API Anahtarınız Geçersiz veya Süresi Dolmuş!</h3>";
        echo "<p>Lütfen Google AI Studio hesabınızdan yeni bir anahtar oluşturup sisteme girin. Kopyalarken başında veya sonunda boşluk kalmadığından emin olun.</p>";
    }
}
