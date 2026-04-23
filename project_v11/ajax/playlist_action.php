<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session(); header('Content-Type: application/json');
$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Giriş yapmalısınız.']); exit; }
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals($_SESSION['csrf_token']??'', $data['csrf_token']??'')) { echo json_encode(['success'=>false]); exit; }
$action      = $data['action'] ?? '';
$playlist_id = (int)($data['playlist_id']??0);
$platform    = trim($data['platform']??'');
$video_id    = trim($data['video_id']??'');
$title       = trim($data['title']??'');

// Playlist sahibi kontrolü
$pl = $pdo->prepare("SELECT id FROM playlists WHERE id=? AND user_id=? LIMIT 1");
$pl->execute([$playlist_id,$user['id']]);
if (!$pl->fetch()) { echo json_encode(['success'=>false,'message'=>'Playlist bulunamadı.']); exit; }

if ($action === 'add') {
    $exists = $pdo->prepare("SELECT id FROM playlist_videos WHERE playlist_id=? AND platform=? AND video_id=?");
    $exists->execute([$playlist_id,$platform,$video_id]);
    if ($exists->fetch()) { echo json_encode(['success'=>false,'message'=>'Video zaten listede.']); exit; }
    $pdo->prepare("INSERT INTO playlist_videos(playlist_id,platform,video_id,title) VALUES(?,?,?,?)")
        ->execute([$playlist_id,$platform,$video_id,$title]);
    echo json_encode(['success'=>true,'message'=>'Videoya eklendi.']);
} elseif ($action === 'remove') {
    $pdo->prepare("DELETE pv FROM playlist_videos pv WHERE pv.playlist_id=? AND pv.platform=? AND pv.video_id=?")->execute([$playlist_id,$platform,$video_id]);
    echo json_encode(['success'=>true,'message'=>'Listeden kaldırıldı.']);
} else {
    echo json_encode(['success'=>false,'message'=>'Geçersiz işlem.']);
}
