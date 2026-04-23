<?php
require_once __DIR__ . '/../includes/functions.php';
start_session();
$valid = ['tr','en','de','az','es','ru','zh','ar'];
$lang  = in_array($_GET['lang']??'', $valid) ? $_GET['lang'] : 'tr';
setcookie('ns_lang', $lang, time()+31536000, '/');
$_SESSION['lang'] = $lang;
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$user = get_user();
if ($user) { $pdo->prepare("UPDATE users SET lang=? WHERE id=?")->execute([$lang,$user['id']]); }
echo json_encode(['success'=>true,'lang'=>$lang]);
