<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session(); header('Content-Type: application/json');
$user = get_user();
if (!$user) { echo json_encode(['success'=>false]); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['csrf_token']??'', $data['csrf_token']??'')) { echo json_encode(['success'=>false]); exit; }
$id = (int)($data['id']??0);
$pdo->prepare("DELETE FROM uploaded_videos WHERE id=? AND user_id=?")->execute([$id,$user['id']]);
echo json_encode(['success'=>true]);
