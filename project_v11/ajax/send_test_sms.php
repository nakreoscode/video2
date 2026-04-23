<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
start_session(); header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (empty($_SESSION['admin_logged_in'])) { echo json_encode(['success'=>false,'error'=>'Yetkisiz']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf_token']??'', $data['csrf_token']??'')) { echo json_encode(['success'=>false,'error'=>'CSRF']); exit; }
$phone = trim($data['phone'] ?? '');
if (!$phone) { echo json_encode(['success'=>false,'error'=>'Telefon girin']); exit; }
$sms = SMS::instance();
$res = $sms->send($phone, 'NakreosStream test SMS - ' . date('H:i:s'));
echo json_encode($res);
