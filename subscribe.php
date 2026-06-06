<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Lütfen bir e-posta adresi girin.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz e-posta adresi formatı.']);
    exit;
}

try {
    // Check if already subscribed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Bu e-posta adresi zaten bültenimize kayıtlı!']);
        exit;
    }

    // Insert subscriber
    $insert_stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?)");
    $insert_stmt->execute([$email]);

    echo json_encode(['success' => true, 'message' => 'Bültenimize başarıyla abone oldunuz! Teşekkür ederiz.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu. Lütfen tekrar deneyin.']);
}
exit;
?>
