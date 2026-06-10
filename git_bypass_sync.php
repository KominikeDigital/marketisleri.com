<?php
// git_bypass_sync.php - Direct GitHub File Deployer (Bypasses Git CLI)
header("Content-Type: text/html; charset=utf-8");
echo "<h2>marketisleri.com Direkt Güncelleyici (Git Çözücü)</h2>";

$files = [
    'config.php' => 'config.php',
    'viewer.php' => 'viewer.php',
    'admin/analyze_brochures.php' => 'admin/analyze_brochures.php',
    'admin/index.php' => 'admin/index.php',
    'admin/apply_scrapers.php' => 'admin/apply_scrapers.php',
    'api/analyze_page.php' => 'api/analyze_page.php',
    'api/price_alert.php' => 'api/price_alert.php',
    'api/price_compare.php' => 'api/price_compare.php'
];

$repo_base = "https://raw.githubusercontent.com/KominikeDigital/marketisleri.com/main/";

foreach ($files as $repo_path => $local_path) {
    $url = $repo_base . $repo_path;
    $local_full_path = __DIR__ . '/' . $local_path;
    
    // Ensure directory exists
    $dir = dirname($local_full_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Fetch content with custom user agent to prevent github block
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP-Github-Deployer\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $content = @file_get_contents($url, false, $context);
    
    if ($content !== false && strlen($content) > 0) {
        if (@file_put_contents($local_full_path, $content) !== false) {
            echo "<p style='color: green;'>✔ <b>$local_path</b> başarıyla indirildi ve güncellendi.</p>";
        } else {
            echo "<p style='color: red;'>✘ <b>$local_path</b> dosyasına yazılamadı (Yetki sorunu!).</p>";
        }
    } else {
        echo "<p style='color: red;'>✘ GitHub'dan indirilemedi: $url</p>";
    }
}

echo "<h3>Güncelleme Bitti!</h3>";
echo "<p>Siteniz ve Admin paneliniz artık tamamen günceldir. Bu dosyayı (git_bypass_sync.php) sunucudan silebilirsiniz.</p>";
