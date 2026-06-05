<?php
session_start();
$db_host = 'localhost';
$db_name = 'VERITABANI_ADINIZ'; 
$db_user = 'VERITABANI_KULLANICINIZ'; 
$db_pass = 'VERITABANI_SIFRENIZ'; 

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

$admin_user = "admin";
$admin_pass = "161224";
$site_url = "https://marketisleri.com";
?>