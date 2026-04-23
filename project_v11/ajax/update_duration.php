<?php
define('NS_LOADED',true);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
start_session();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'),true);
$id  = (int)($data['id']??0);
$dur = (int)($data['duration']??0);
if($id>0 && $dur>0){
    try{
        $pdo->prepare("UPDATE uploaded_videos SET duration=? WHERE id=? AND (duration=0 OR duration IS NULL)")->execute([$dur,$id]);
    }catch(Exception $e){}
}
echo json_encode(['ok'=>true]);
