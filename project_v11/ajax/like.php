<?php
// ajax/like.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session();
header('Content-Type: application/json');
$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Giriş yapmalısınız.']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['csrf_token']??'', $data['csrf_token']??'')) { echo json_encode(['success'=>false]); exit; }
$platform = trim($data['platform']??'');
$video_id = trim($data['id']??'');
if (!$platform || !$video_id) { echo json_encode(['success'=>false]); exit; }
$check = $pdo->prepare("SELECT id FROM likes WHERE user_id=? AND platform=? AND video_id=?");
$check->execute([$user['id'],$platform,$video_id]);
if ($check->fetch()) {
    $pdo->prepare("DELETE FROM likes WHERE user_id=? AND platform=? AND video_id=?")->execute([$user['id'],$platform,$video_id]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO likes(user_id,platform,video_id) VALUES(?,?,?)")->execute([$user['id'],$platform,$video_id]);
    $liked = true;
}
$count = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE platform=? AND video_id=?");
$count->execute([$platform,$video_id]); $count = $count->fetchColumn();
echo json_encode(['success'=>true,'liked'=>$liked,'count'=>$count,'message'=>$liked?'Beğenildi':'Beğeni kaldırıldı']);
