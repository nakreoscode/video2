<?php
define('NS_LOADED',true);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
start_session();
header('Content-Type: application/json');
$user=get_user();
if(!$user){echo json_encode(['success'=>false,'message'=>'Giriş yapın']);exit;}
$data=json_decode(file_get_contents('php://input'),true);
if(!isset($data['csrf_token'])||!hash_equals($_SESSION['csrf_token']??'',$data['csrf_token'])){echo json_encode(['success'=>false,'message'=>'Güvenlik hatası']);exit;}
$target_id=(int)($data['user_id']??0);
if(!$target_id||$target_id===$user['id']){echo json_encode(['success'=>false,'message'=>'Geçersiz istek']);exit;}
try {
    $check=$pdo->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
    $check->execute([$user['id'],$target_id]);
    if($check->fetch()){
        $pdo->prepare("DELETE FROM follows WHERE follower_id=? AND following_id=?")->execute([$user['id'],$target_id]);
        echo json_encode(['success'=>true,'following'=>false,'message'=>'Abonelik iptal edildi']);
    } else {
        $pdo->prepare("INSERT INTO follows(follower_id,following_id) VALUES(?,?)")->execute([$user['id'],$target_id]);
        // Takip bildirimi gönder
        $follower_name = $user['full_name']?:$user['username'];
        send_notification($target_id,'follow','Yeni Abone!',"@{$user['username']} kanalına abone oldu. 🎉",['follower_id'=>$user['id']]);
        // Kanal abone sayısını güncelle
        $pdo->prepare("UPDATE channels SET subscriber_count=subscriber_count+1 WHERE user_id=?")->execute([$target_id]);
        echo json_encode(['success'=>true,'following'=>true,'message'=>'Abone olundu']);
    }
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>'Hata: '.$e->getMessage()]);
}
