<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session(); header('Content-Type: application/json');
$user = get_user();
if (!$user) { echo json_encode(['success'=>false]); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['csrf_token']??'', $data['csrf_token']??'')) { echo json_encode(['success'=>false]); exit; }
$pdo->prepare("DELETE FROM saved_videos WHERE user_id=? AND platform=? AND video_id=?")->execute([$user['id'],$data['platform']??'',$data['id']??'']);
echo json_encode(['success'=>true]);
