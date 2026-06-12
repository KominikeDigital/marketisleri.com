<?php
// git_bypass_sync.php - Direct GitHub File Deployer (Bypasses Git CLI)
header("Content-Type: text/html; charset=utf-8");
echo "<h2>marketisleri.com Direkt Güncelleyici (Git Çözücü)</h2>";

// Self-update git_bypass_sync.php first
$self_url = "https://raw.githubusercontent.com/KominikeDigital/marketisleri.com/main/git_bypass_sync.php";
$self_path = __FILE__;
$opts_self = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: PHP-Github-Deployer-Self\r\n"
    ]
];
$context_self = stream_context_create($opts_self);
$self_content = @file_get_contents($self_url, false, $context_self);
if ($self_content !== false && strlen($self_content) > 0) {
    $current_content = @file_get_contents($self_path);
    if (md5($current_content) !== md5($self_content)) {
        if (@file_put_contents($self_path, $self_content) !== false) {
            echo "<p style='color: blue; font-weight: bold;'>🔄 Güncelleyici (git_bypass_sync.php) kendini güncelledi! Lütfen sayfayı yenileyin (F5 veya Yenile).</p>";
            exit;
        }
    }
}

$files = [
    'config.php' => 'config.php',
    'index.php' => 'index.php',
    'market.php' => 'market.php',
    'marketler.php' => 'marketler.php',
    'viewer.php' => 'viewer.php',
    'iletisim.php' => 'iletisim.php',
    'robots.txt' => 'robots.txt',
    'info_marketisleri.md' => 'info_marketisleri.md',
    'cerez-politikasi.php' => 'cerez-politikasi.php',
    'gizlilik-politikasi.php' => 'gizlilik-politikasi.php',
    'kullanim-kosullari.php' => 'kullanim-kosullari.php',
    'subscribe.php' => 'subscribe.php',
    'sitemap.php' => 'sitemap.php',
    'api/analyze_page.php' => 'api/analyze_page.php',
    'api/price_alert.php' => 'api/price_alert.php',
    'api/price_compare.php' => 'api/price_compare.php',
    'admin/index.php' => 'admin/index.php',
    'admin/login.php' => 'admin/login.php',
    'admin/logout.php' => 'admin/logout.php',
    'admin/markets.php' => 'admin/markets.php',
    'admin/brochures.php' => 'admin/brochures.php',
    'admin/magic_import.php' => 'admin/magic_import.php',
    'admin/cron_setup.php' => 'admin/cron_setup.php',
    'admin/apply_scrapers.php' => 'admin/apply_scrapers.php',
    'admin/analyze_brochures.php' => 'admin/analyze_brochures.php',
    'admin/subscribers.php' => 'admin/subscribers.php',
    'admin/settings.php' => 'admin/settings.php',
    'admin/delete.php' => 'admin/delete.php',
    'admin/list_models.php' => 'admin/list_models.php',
    'admin/test_key.php' => 'admin/test_key.php',
    'admin/run_scraper.php' => 'admin/run_scraper.php',
    'admin/auto_scraper.php' => 'admin/auto_scraper.php',
    'admin/merge_duplicate_markets.php' => 'admin/merge_duplicate_markets.php',
    'admin/debug_db.php' => 'admin/debug_db.php',
    'admin/check_db_public.php' => 'admin/check_db_public.php',
    'run_git_reset.php' => 'run_git_reset.php'
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
