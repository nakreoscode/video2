<?php
define('NS_LOADED',true);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
start_session();
$theme = trim($_GET['theme']??'dark');
$allowed = ['dark','light','netflix','twitch','spotify','cinema','minimal'];
if(!in_array($theme,$allowed)) $theme='dark';
setcookie('ns_theme',$theme,time()+31536000,'/');
$user=get_user();
if($user){
    try{$pdo->prepare("UPDATE users SET theme=? WHERE id=?")->execute([$theme,$user['id']]);}catch(Exception $e){}
}
echo json_encode(['success'=>true,'theme'=>$theme]);
