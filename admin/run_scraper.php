<?php
// admin/run_scraper.php - Web-based Scraper Trigger
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Disable time limit for long-running scraper
@set_time_limit(0);

// Flush output buffers to show progress in real-time
if (function_exists('ob_implicit_flush')) {
    ob_implicit_flush(true);
}
while (ob_get_level()) {
    ob_end_clean();
}

header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Scraper Çalıştırıcı</title>
    <style>
        body { background: #0f172a; color: #38bdf8; font-family: monospace; padding: 20px; line-height: 1.5; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .info { color: #fbbf24; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h2>🚀 Broşür Kazıcı (Scraper) Başlatılıyor...</h2>
    <hr style="border-color: #334155; margin-bottom: 20px;">
    <pre>
<?php
echo "Sistem zamanı: " . date('Y-m-d H:i:s') . "\n";
echo "Çalışma dizini: " . __DIR__ . "\n";

// Path to scraper
$scraper_path = realpath(__DIR__ . '/../scraper/index.js');
if (!$scraper_path) {
    echo "<span class='error'>Hata: scraper/index.js bulunamadı!</span>\n";
    exit;
}

echo "Scraper dosyası: $scraper_path\n";

// Find Node.js path (some servers don't have it in PHP's path, we try to detect typical locations)
$node_bin = 'node';
$possible_paths = [
    'node',
    '/usr/local/bin/node',
    '/usr/bin/node',
];

// 1. EasyApache Node.js paths
$ea_paths = glob('/opt/cpanel/ea-nodejs*/bin/node');
if ($ea_paths) {
    $possible_paths = array_merge($possible_paths, $ea_paths);
}

// 2. CloudLinux alt-nodejs paths
$alt_paths = glob('/opt/alt/alt-nodejs*/root/usr/bin/node');
if ($alt_paths) {
    $possible_paths = array_merge($possible_paths, $alt_paths);
}

// 3. CloudLinux virtual environment (nodevenv) paths
$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($doc_root) {
    $home_dir = dirname($doc_root); // e.g. /home/marketis
    if (is_dir($home_dir . '/nodevenv')) {
        $venv_nodes = glob($home_dir . '/nodevenv/*/*/bin/node');
        if ($venv_nodes) {
            $possible_paths = array_merge($possible_paths, $venv_nodes);
        }
        $venv_nodes_alt = glob($home_dir . '/nodevenv/*/*/*/bin/node');
        if ($venv_nodes_alt) {
            $possible_paths = array_merge($possible_paths, $venv_nodes_alt);
        }
    }
}

$node_found = false;
foreach (array_unique($possible_paths) as $path) {
    $version_out = [];
    $ret = -1;
    @exec("$path -v 2>&1", $version_out, $ret);
    if ($ret === 0 && !empty($version_out)) {
        $node_bin = $path;
        $node_found = true;
        echo "Node.js tespit edildi: $path (" . $version_out[0] . ")\n";
        break;
    }
}

if (!$node_found) {
    echo "<span class='error'>Hata: Sunucuda Node.js komutu çalıştırılamadı! PHP exec yetkisi kapalı olabilir veya Node.js kurulu değildir.</span>\n";
    echo "Eğer Node.js özel bir dizindeyse, lütfen admin/run_scraper.php dosyasındaki \$possible_paths dizisine ekleyin.\n";
    echo "Denenen yollar:\n";
    foreach (array_unique($possible_paths) as $path) {
        echo " - $path\n";
    }
    exit;
}

echo "Kazıma işlemi başlatılıyor (bu işlem birkaç dakika sürebilir, lütfen sayfayı kapatmayın)...\n\n";
flush();

// Run scraper using popen to stream output in real-time
$handle = popen("$node_bin " . escapeshellarg($scraper_path) . " 2>&1", 'r');
if ($handle) {
    while (!feof($handle)) {
        $buffer = fgets($handle);
        echo htmlspecialchars($buffer);
        flush();
    }
    $return_val = pclose($handle);
} else {
    echo "<span class='error'>İşlem başlatılamadı (popen başarısız).</span>\n";
    $return_val = -1;
}

echo "\n----------------------------------------\n";
if ($return_val === 0) {
    echo "<span class='success'>✔ Kazıma işlemi başarıyla tamamlandı!</span>\n";
} else {
    echo "<span class='error'>✘ Kazıma işlemi hata ile sonlandı (Hata kodu: $return_val).</span>\n";
}
?>
    </pre>
</body>
</html>
