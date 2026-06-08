<?php
// Secure deployment script for marketisleri.com
// This script automates git pull on cPanel and automatically resolves config.php / .htaccess conflicts.

// Enable error reporting to diagnose fatal errors or blocked functions
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Disable output buffering to send output to the browser in real time
if (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

define('DEPLOY_KEY', 'emre_deploy_161224'); // Secure key to prevent unauthorized execution

if (!isset($_GET['key']) || $_GET['key'] !== DEPLOY_KEY) {
    header('HTTP/1.0 403 Forbidden');
    die('Yetkisiz erişim.');
}

header("Content-Type: text/plain; charset=utf-8");
echo "=== OTOMATİK DEPLOYMENT BAŞLADI ===\n\n";

chdir(__DIR__);
echo "Çalışma dizini: " . getcwd() . "\n";

// Helper function to safely execute shell commands with fallbacks
function run_cmd($command) {
    $disabled_functions = explode(',', ini_get('disable_functions'));
    $disabled_functions = array_map('trim', $disabled_functions);
    
    echo "Çalıştırılan Komut: $command\n";

    if (function_exists('shell_exec') && !in_array('shell_exec', $disabled_functions)) {
        $output = shell_exec($command . " 2>&1");
        return $output !== null ? $output : "Command returned null / no output.";
    }
    
    if (function_exists('exec') && !in_array('exec', $disabled_functions)) {
        $output = [];
        $result = 0;
        exec($command . " 2>&1", $output, $result);
        return implode("\n", $output) . "\n(Exit code: $result)";
    }
    
    if (function_exists('system') && !in_array('system', $disabled_functions)) {
        ob_start();
        $result = system($command . " 2>&1");
        $output = ob_get_clean();
        return $output . "\n(Result: $result)";
    }
    
    if (function_exists('passthru') && !in_array('passthru', $disabled_functions)) {
        ob_start();
        passthru($command . " 2>&1");
        return ob_get_clean();
    }
    
    return "Hata: Sunucu güvenlik ayarları nedeniyle php.ini içinde shell_exec, exec, system ve passthru fonksiyonlarının hepsi engellenmiş. Lütfen cPanel Git Version Control sayfasından manuel güncelleme yapın.";
}

// Check if git is available in PATH
echo "Sistem Bilgisi: " . php_uname() . "\n";
echo "Git kontrol ediliyor...\n";
$git_check = run_cmd("git --version");
echo "Git Versiyon: " . trim($git_check) . "\n\n";

// 1. Discard local changes to config.php and .htaccess to prevent merge conflicts
echo "1. Çakışan yerel dosyalar sıfırlanıyor (git checkout)...\n";
$res_config = run_cmd("git checkout -- config.php");
echo "config.php: " . trim($res_config) . "\n\n";

$res_htaccess = run_cmd("git checkout -- .htaccess");
echo ".htaccess: " . trim($res_htaccess) . "\n\n";

// 2. Run git pull
echo "2. GitHub'dan güncellemeler çekiliyor (git pull)...\n";
$pull_output = run_cmd("git pull");
echo $pull_output . "\n";

echo "=== DEPLOYMENT TAMAMLANDI ===\n";
