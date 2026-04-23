<?php define("NS_LOADED",true); ?>
<?php
define('NS_LOADED', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session();
header('Content-Type: application/json');
$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Giriş yapmalısınız.']); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
if (empty($data['csrf_token']) || !hash_equals($_SESSION['csrf_token']??'', $data['csrf_token'])) {
    echo json_encode(['success'=>false,'message'=>'Güvenlik hatası.']); exit;
}
$platform  = trim($data['platform'] ?? '');
$video_id  = trim($data['id'] ?? '');
$title     = trim($data['title'] ?? '');
$thumbnail = trim($data['thumbnail'] ?? '');
$channel   = trim($data['channel'] ?? '');
$duration  = (int)($data['duration'] ?? 0);
$type      = in_array($data['type']??'normal',['normal','short','image']) ? $data['type'] : 'normal';

if (!$platform || !$video_id) { echo json_encode(['success'=>false,'message'=>'Geçersiz veri.']); exit; }

// Kayıtlı mı kontrol et
$check = $pdo->prepare("SELECT id FROM saved_videos WHERE user_id=? AND platform=? AND video_id=?");
$check->execute([$user['id'],$platform,$video_id]);

if ($check->fetch()) {
    // Kaldır
    $pdo->prepare("DELETE FROM saved_videos WHERE user_id=? AND platform=? AND video_id=?")->execute([$user['id'],$platform,$video_id]);
    echo json_encode(['success'=>true,'saved'=>false,'message'=>'Kayıt kaldırıldı.']);
} else {
    // Kaydet
    $pdo->prepare("INSERT INTO saved_videos(user_id,platform,video_id,title,thumbnail,channel,duration,type) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([$user['id'],$platform,$video_id,$title,$thumbnail,$channel,$duration,$type]);
    echo json_encode(['success'=>true,'saved'=>true,'message'=>'Video kaydedildi.']);
}
