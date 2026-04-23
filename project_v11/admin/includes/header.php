<?php
define('NS_LOADED', true);
if (!defined('IN_ADMIN')) define('IN_ADMIN', true);
require_once dirname(dirname(__DIR__)) . '/includes/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
start_session();
require_admin();
$st = get_setting('site_title','NakreosStream');
$cur = basename($_SERVER['PHP_SELF']);
$pf = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
$fl = get_flash();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($page_title)?e($page_title).' – ':'' ?>Admin · <?= e($st) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f0f0f;--bg2:#181818;--bg3:#212121;--border:#303030;--acc:#ff0000;--acc-h:#cc0000;--text:#f1f1f1;--text2:#aaa;--text3:#717171;--hover:rgba(255,255,255,.08)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Roboto',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:14px}
a{color:inherit;text-decoration:none}button{cursor:pointer;font-family:'Roboto',sans-serif}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}

/* Sidebar */
.asb{width:220px;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;bottom:0;left:0;overflow-y:auto;z-index:50;transition:transform .2s}
.asb-h{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.asb-logo{font-size:15px;font-weight:700}
.asec{padding:8px 0}
.asec-lbl{font-size:11px;font-weight:600;color:var(--text3);padding:10px 16px 4px;text-transform:uppercase;letter-spacing:.5px}
.ani{display:flex;align-items:center;gap:10px;padding:9px 16px;font-size:13px;color:var(--text2);transition:background .1s;border-left:3px solid transparent;white-space:nowrap}
.ani:hover{background:var(--hover);color:var(--text)}
.ani.on{background:var(--hover);color:var(--text);border-left-color:var(--acc)}
.ani .badge{background:var(--acc);color:#fff;font-size:10px;font-weight:700;border-radius:99px;padding:1px 6px;margin-left:auto}

/* Main */
.amain{margin-left:220px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.atop{background:var(--bg2);border-bottom:1px solid var(--border);padding:0 24px;height:52px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;flex-shrink:0}
.atop h1{font-size:15px;font-weight:600}
.acon{padding:24px;flex:1}

/* Buttons */
.btn{background:var(--acc);color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:500;transition:.15s;display:inline-flex;align-items:center;gap:6px;font-family:'Roboto',sans-serif}
.btn:hover{background:var(--acc-h)}
.btn.ghost{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn.ghost:hover{background:var(--hover)}
.btn.green{background:#16a34a}.btn.green:hover{background:#15803d}
.btn.sm{padding:6px 12px;font-size:12px}

/* Inputs */
.ns-input{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-size:13px;width:100%;outline:none;transition:border-color .2s;font-family:'Roboto',sans-serif}
.ns-input:focus{border-color:var(--acc)}
.ns-input::placeholder{color:var(--text3)}
select.ns-input option{background:var(--bg3)}

/* Card */
.ns-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px}

/* Table */
.ns-table{width:100%;border-collapse:collapse;font-size:13px}
.ns-table th{padding:10px 16px;text-align:left;font-size:11px;color:var(--text2);font-weight:600;text-transform:uppercase;background:var(--bg3);white-space:nowrap}
.ns-table td{padding:10px 16px;border-top:1px solid var(--border);vertical-align:middle}
.ns-table tr:hover td{background:var(--bg3)}

/* Status badges */
.sb{font-size:11px;font-weight:600;padding:3px 9px;border-radius:99px;white-space:nowrap}
.sb-green{background:#0d2e1a;color:#4ade80}
.sb-red{background:#2d0a0a;color:#f87171}
.sb-yellow{background:#2d1a00;color:#fbbf24}
.sb-blue{background:#0d1f3a;color:#60a5fa}
.sb-gray{background:var(--bg3);color:var(--text2)}
.sb-purple{background:rgba(124,58,237,.2);color:#a78bfa}

/* Responsive */
@media(max-width:900px){.asb{transform:translateX(-100%)}.amain{margin-left:0}}

/* Grid utilities */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
@media(max-width:768px){.grid-2,.grid-3,.grid-4{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="asb">
  <div class="asb-h">
    <svg width="24" height="17" viewBox="0 0 90 64" fill="var(--acc)"><path d="M88.1 9.9C87 5.9 83.9 2.8 79.9 1.7 73 0 45 0 45 0S17 0 10.1 1.7C6.1 2.8 3 5.9 1.9 9.9 0 16.8 0 32 0 32s0 15.2 1.9 22.1C3 58.1 6.1 61.2 10.1 62.3 17 64 45 64 45 64s28 0 34.9-1.7c4-1.1 7.1-4.2 8.2-8.2C90 47.2 90 32 90 32s0-15.2-1.9-22.1z"/><path d="M36 45.6l23.3-13.6L36 18.4v27.2z" fill="white"/></svg>
    <span class="asb-logo"><?= e($st) ?> Admin</span>
  </div>

  <div class="asec">
    <div class="asec-lbl">Genel</div>
    <a href="/admin/" class="ani <?= $cur==='index.php'?'on':'' ?>">📊 Dashboard</a>
    <a href="/admin/users.php" class="ani <?= $cur==='users.php'?'on':'' ?>">👥 Kullanıcılar</a>
    <a href="/admin/payments.php" class="ani <?= $cur==='payments.php'?'on':'' ?>">
      💳 Ödemeler <?php if($pf>0):?><span class="badge"><?=$pf?></span><?php endif?>
    </a>
    <a href="/admin/videos.php" class="ani <?= $cur==='videos.php'?'on':'' ?>">🎬 Videolar</a>
  </div>

  <div class="asec">
    <div class="asec-lbl">İçerik</div>
    <a href="/admin/categories.php" class="ani <?= $cur==='categories.php'?'on':'' ?>">📂 Kategoriler</a>
    <a href="/admin/ads.php" class="ani <?= $cur==='ads.php'?'on':'' ?>">📢 Reklamlar</a>
  </div>

  <div class="asec">
    <div class="asec-lbl">Ayarlar</div>
    <a href="/admin/settings.php" class="ani <?= $cur==='settings.php'?'on':'' ?>">⚙️ Genel</a>
    <a href="/admin/seo.php" class="ani <?= $cur==='seo.php'?'on':'' ?>">🔍 SEO</a>
    <a href="/admin/sms-settings.php" class="ani <?= $cur==='sms-settings.php'?'on':'' ?>">📱 SMS</a>
    <a href="/admin/storage-settings.php" class="ani <?= $cur==='storage-settings.php'?'on':'' ?>">☁️ Depolama</a>
    <a href="/admin/themes.php" class="ani <?=$cur==='themes.php'?'on':'' ?>">🎨 Temalar</a>
    <a href="/admin/announcements.php" class="ani <?=$cur==='announcements.php'?'on':'' ?>">📢 Duyurular</a>
    <a href="/admin/plans.php" class="ani <?=$cur==='plans.php'?'on':'' ?>">⭐ Paketler</a>
    <a href="/admin/admins.php" class="ani <?= $cur==='admins.php'?'on':'' ?>">👤 Admin Yönetimi</a>
    <a href="/admin/logs.php" class="ani <?= $cur==='logs.php'?'on':'' ?>">📋 Loglar</a>
  </div>

  <div class="asec" style="margin-top:auto;border-top:1px solid var(--border)">
    <a href="/" target="_blank" class="ani">🌐 Siteye Git</a>
    <a href="/admin/logout.php" class="ani" style="color:#f87171">🚪 Çıkış</a>
  </div>
</aside>

<!-- Main -->
<div class="amain">
  <div class="atop">
    <h1><?= isset($page_title)?e($page_title):'Dashboard' ?></h1>
    <span style="font-size:12px;color:var(--text2)">👤 <?= e($_SESSION['admin_username']??'Admin') ?></span>
  </div>
  <?php if ($fl): ?>
  <div style="margin:12px 24px 0;padding:11px 16px;border-radius:8px;font-size:13px;border:1px solid;
    <?= match($fl['type']){'success'=>'background:#0d2e1a;border-color:#16a34a;color:#4ade80','error'=>'background:#2d0a0a;border-color:#ef4444;color:#f87171',default=>'background:var(--bg2);border-color:var(--border);color:var(--text)'} ?>">
    <?= e($fl['message']) ?>
  </div>
  <?php endif ?>
  <div class="acon">
