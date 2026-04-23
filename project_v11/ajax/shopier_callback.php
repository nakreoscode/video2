<?php
// Shopier IPN Callback
define('NS_LOADED', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$api_key    = get_setting('shopier_api_key');
$api_secret = get_setting('shopier_api_secret');

$order_id  = $_POST['platform_order_id'] ?? '';
$status    = $_POST['status'] ?? '';
$signature = $_POST['signature'] ?? '';

// İmza doğrula
$expected = base64_encode(hash_hmac('SHA256', $order_id . ($_POST['installment'] ?? '1'), $api_secret, true));
if (!hash_equals($expected, $signature)) {
    http_response_code(400); echo 'Invalid signature'; exit;
}

if ($status === '1' && $order_id) {
    // Ödemeyi bul
    $pay = $pdo->prepare("SELECT * FROM payments WHERE receipt_info=? LIMIT 1");
    $pay->execute([$order_id]); $pay = $pay->fetch();
    if ($pay && $pay['status'] === 'pending') {
        $pdo->prepare("UPDATE payments SET status='approved', approved_at=NOW() WHERE id=?")->execute([$pay['id']]);
        $pdo->prepare("UPDATE users SET membership=? WHERE id=?")->execute([$pay['plan'], $pay['user_id']]);
        send_notification($pay['user_id'], 'payment', 'Üyeliğiniz Aktifleşti!', ucfirst($pay['plan']).' üyeliğiniz aktifleştirildi. İyi seyirler! 🎉', ['icon'=>'💜']);
    }
}
echo 'OK';
