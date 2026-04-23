<?php
// PayTR IPN Callback
define('NS_LOADED', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$merchant_key  = get_setting('paytr_merchant_key');
$merchant_salt = get_setting('paytr_merchant_salt');

$post = $_POST;
$hash = base64_encode(hash_hmac('sha256', $post['merchant_oid'] . $merchant_salt . $post['status'] . $post['total_amount'], $merchant_key, true));

if ($hash !== $post['hash']) {
    echo 'PAYTR_IPN_IS_INVALID'; exit;
}

$order_id = $post['merchant_oid'] ?? '';
$status   = $post['status'] ?? '';

if ($status === 'success' && $order_id) {
    $pay = $pdo->prepare("SELECT * FROM payments WHERE receipt_info=? LIMIT 1");
    $pay->execute([$order_id]); $pay = $pay->fetch();
    if ($pay && $pay['status'] === 'pending') {
        $pdo->prepare("UPDATE payments SET status='approved', approved_at=NOW() WHERE id=?")->execute([$pay['id']]);
        $pdo->prepare("UPDATE users SET membership=? WHERE id=?")->execute([$pay['plan'], $pay['user_id']]);
        send_notification($pay['user_id'], 'payment', 'Üyeliğiniz Aktifleşti!', ucfirst($pay['plan']).' üyeliğiniz aktifleştirildi. İyi seyirler! 🎉', ['icon'=>'💙']);
    }
}
echo 'OK';
