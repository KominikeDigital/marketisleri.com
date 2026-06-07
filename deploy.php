<?php
// Secure deployment script for marketisleri.com
// This script automates git pull on cPanel and automatically resolves config.php / .htaccess conflicts.

define('DEPLOY_KEY', 'emre_deploy_161224'); // Secure key to prevent unauthorized execution

if (!isset($_GET['key']) || $_GET['key'] !== DEPLOY_KEY) {
    header('HTTP/1.0 403 Forbidden');
    die('Yetkisiz erişim.');
}

header("Content-Type: text/plain; charset=utf-8");
echo "=== OTOMATİK DEPLOYMENT BAŞLADI ===\n\n";

chdir(__DIR__);
echo "Çalışma dizini: " . getcwd() . "\n";

// 1. Discard local changes to config.php and .htaccess to prevent merge conflicts
echo "\n1. Çakışan yerel dosyalar sıfırlanıyor (git checkout)...\n";
$res_config = shell_exec("git checkout -- config.php 2>&1");
echo "config.php: " . ($res_config ? trim($res_config) : "Temiz/Sıfırlandı") . "\n";

$res_htaccess = shell_exec("git checkout -- .htaccess 2>&1");
echo ".htaccess: " . ($res_htaccess ? trim($res_htaccess) : "Temiz/Sıfırlandı") . "\n";

// 2. Run git pull
echo "\n2. GitHub'dan güncellemeler çekiliyor (git pull)...\n";
$pull_output = shell_exec("git pull 2>&1");
echo $pull_output . "\n";

echo "=== DEPLOYMENT TAMAMLANDI ===\n";
