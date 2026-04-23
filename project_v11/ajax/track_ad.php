<?php
require_once __DIR__ . '/../includes/db.php';
$id   = (int)($_GET['id']??0);
$type = $_GET['type']==='click' ? 'clicks' : 'impressions';
if ($id) $pdo->prepare("UPDATE ads SET {$type}={$type}+1 WHERE id=?")->execute([$id]);
echo 'ok';
