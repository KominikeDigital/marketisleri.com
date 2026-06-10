<?php
/**
 * api/price_alert.php
 * Fiyat alarmı oluşturur veya iptal eder.
 *
 * POST (action=create):
 *   email, product_name, target_price (opsiyonel), market_id (opsiyonel)
 *
 * GET (action=cancel):
 *   token
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require dirname(__DIR__) . '/config.php';

function json_response(bool $ok, array $data): never {
    if (!$ok) http_response_code(400);
    echo json_encode(['success' => $ok, ...$data]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'create';

// ── CANCEL alarm ──────────────────────────────────────────────────────────────
if ($action === 'cancel') {
    $token = trim((string)($_GET['token'] ?? ''));
    if (!$token) json_response(false, ['error' => 'Token gerekli']);

    $stmt = $pdo->prepare("UPDATE price_alerts SET is_active = 0 WHERE token = ?");
    $stmt->execute([$token]);
    if ($stmt->rowCount() === 0) json_response(false, ['error' => 'Alarm bulunamadı veya zaten iptal edilmiş']);

    json_response(true, ['message' => 'Fiyat alarmınız iptal edildi.']);
}

// ── CREATE alarm ──────────────────────────────────────────────────────────────
$email        = trim(strtolower((string)($_POST['email'] ?? '')));
$product_name = trim((string)($_POST['product_name'] ?? ''));
$target_price = isset($_POST['target_price']) && is_numeric($_POST['target_price'])
    ? (float)$_POST['target_price']
    : null;
$market_id    = isset($_POST['market_id']) && (int)$_POST['market_id'] > 0
    ? (int)$_POST['market_id']
    : null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, ['error' => 'Geçerli bir e-posta adresi girin']);
}
if (!$product_name) {
    json_response(false, ['error' => 'Ürün adı gerekli']);
}

// Check duplicate active alert for same email+product
$check = $pdo->prepare(
    "SELECT id FROM price_alerts WHERE email = ? AND product_name = ? AND is_active = 1"
);
$check->execute([$email, $product_name]);
if ($check->fetchColumn()) {
    json_response(false, ['error' => 'Bu ürün için zaten aktif bir alarm kaydınız var.']);
}

// Generate cancel token
$token = bin2hex(random_bytes(24));

$insert = $pdo->prepare(
    "INSERT INTO price_alerts (email, product_name, target_price, market_id, token, is_active)
     VALUES (?, ?, ?, ?, ?, 1)"
);
$insert->execute([$email, $product_name, $target_price, $market_id, $token]);

// Send confirmation email
$site_url_val = current_site_url();
$cancel_url   = $site_url_val . '/api/price_alert.php?action=cancel&token=' . $token;
$price_text   = $target_price ? number_format($target_price, 2, ',', '.') . ' TL altına düştüğünde' : 'yeni bir broşürde görüldüğünde';

$email_body = "
<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
  <div style='background:#ef4444;padding:24px;text-align:center;border-radius:12px 12px 0 0;'>
    <h1 style='color:white;margin:0;font-size:20px;'>🔔 Fiyat Alarmı Kuruldu</h1>
  </div>
  <div style='background:#fff;padding:28px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;'>
    <p style='color:#475569;'>Merhaba,</p>
    <p style='color:#1e293b;'><strong>" . htmlspecialchars($product_name) . "</strong> ürünü için fiyat alarmınız başarıyla oluşturuldu.</p>
    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:20px 0;'>
      <p style='margin:0;color:#64748b;font-size:14px;'><strong>Alarm Koşulu:</strong> Ürün fiyatı <em>{$price_text}</em> e-posta ile bildirilecektir.</p>
    </div>
    <a href='" . htmlspecialchars($cancel_url) . "' style='display:inline-block;background:#64748b;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:13px;'>Alarmı İptal Et</a>
    <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0;'>
    <p style='color:#94a3b8;font-size:12px;'>Bu e-postayı siz istediniz. Eğer istemediyseniz alarmı iptal edebilirsiniz.</p>
    <p style='color:#94a3b8;font-size:12px;'>© marketisleri.com</p>
  </div>
</div>
";

send_email_notification(
    $email,
    "🔔 Fiyat Alarmı: " . mb_substr($product_name, 0, 60),
    $email_body,
    $pdo
);

json_response(true, [
    'message' => 'Fiyat alarmınız oluşturuldu. Onay e-postası gönderildi.',
    'alert_id' => (int)$pdo->lastInsertId(),
]);
