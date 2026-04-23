<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session();
$user = get_user();
if (!$user || $_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php?tab=api'); exit; }
$key = 'nsk_' . bin2hex(random_bytes(20));
$pdo->prepare("INSERT INTO api_keys(user_id,api_key,name,rate_limit) VALUES(?,?,?,?)")->execute([$user['id'],$key,'Default Key',100]);
header('Location: /dashboard.php?tab=api');
