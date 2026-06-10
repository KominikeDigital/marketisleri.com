<?php
// admin/list_models.php - list available Gemini models for the saved API key
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__) . '/config.php';

// Auth check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    die("Oturum kapalı.");
}

header("Content-Type: text/html; charset=utf-8");
echo "<h2>Gemini API Desteklenen Modeller Listesi</h2>";

// Fetch key from settings
$api_key_stmt = $pdo->query("SELECT value_text FROM settings WHERE key_name = 'gemini_api_key'");
$gemini_key   = $api_key_stmt ? trim((string)$api_key_stmt->fetchColumn()) : '';

if (!$gemini_key) {
    die("<p style='color:red;'>API anahtarı bulunamadı.</p>");
}

// 1. Test v1beta models list
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$gemini_key}";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>v1beta Modelleri (HTTP $http_code):</h3>";
if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        echo "<ul>";
        foreach ($data['models'] as $m) {
            echo "<li><code>" . htmlspecialchars($m['name']) . "</code> (Desteklenen metotlar: " . implode(', ', $m['supportedGenerationMethods']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
} else {
    echo "<pre style='color:red;'>" . htmlspecialchars($response) . "</pre>";
}

// 2. Test v1 models list
$url2 = "https://generativelanguage.googleapis.com/v1/models?key={$gemini_key}";
$ch2 = curl_init($url2);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "<h3>v1 Modelleri (HTTP $http_code2):</h3>";
if ($http_code2 === 200) {
    $data2 = json_decode($response2, true);
    if (isset($data2['models'])) {
        echo "<ul>";
        foreach ($data2['models'] as $m) {
            echo "<li><code>" . htmlspecialchars($m['name']) . "</code></li>";
        }
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars(substr($response2, 0, 500)) . "</pre>";
    }
} else {
    echo "<pre style='color:red;'>" . htmlspecialchars($response2) . "</pre>";
}
