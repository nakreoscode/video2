<?php
define('NS_LOADED',true);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
start_session();
header('Content-Type: application/json');
$user=get_user();
if(!$user){echo json_encode(['success'=>false]);exit;}
$data=json_decode(file_get_contents('php://input'),true);
// CSRF kontrolü
try{
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$user['id']]);
    // Duyuruları da okundu yap
    $anns=$pdo->prepare("SELECT id FROM announcements WHERE active=1 AND (target='all' OR target=?)");
    $anns->execute([$user['membership']??'free']);
    foreach($anns->fetchAll() as $a){
        $pdo->prepare("INSERT IGNORE INTO announcement_reads(user_id,announcement_id) VALUES(?,?)")->execute([$user['id'],$a['id']]);
    }
    echo json_encode(['success'=>true]);
}catch(Exception $e){echo json_encode(['success'=>false]);}
