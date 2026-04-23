<?php
// admin/login.php
define('NS_LOADED', true);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
start_session();
if (!empty($_SESSION['admin_logged_in'])) redirect('/admin/');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Güvenlik hatası.'; }
    elseif (admin_login($_POST['username']??'', $_POST['password']??'')) {
        redirect('/admin/');
    } else { $error = 'Hatalı kullanıcı adı veya şifre.'; }
}
?>
<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Giriş – <?= e(get_setting('site_title','NakreosStream')) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:#050505;font-family:system-ui,sans-serif}</style>
</head><body class="min-h-screen flex items-center justify-center">
<div class="w-full max-w-sm px-4">
  <div class="text-center mb-8">
    <h1 class="text-3xl font-black" style="background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent">🎬 Admin</h1>
    <p class="text-gray-500 text-sm mt-1"><?= e(get_setting('site_title','NakreosStream')) ?></p>
  </div>
  <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
    <?php if ($error): ?><div class="bg-red-500/20 border border-red-500/50 text-red-400 rounded-xl p-3 mb-4 text-sm"><?= e($error) ?></div><?php endif ?>
    <form method="POST" class="space-y-4">
      <?= csrf_field() ?>
      <div><label class="block text-gray-400 text-sm mb-1.5">Kullanıcı Adı</label>
      <input type="text" name="username" required autofocus class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition"></div>
      <div><label class="block text-gray-400 text-sm mb-1.5">Şifre</label>
      <input type="password" name="password" required class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition"></div>
      <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold transition mt-2">Giriş Yap</button>
    </form>
  </div>
</div></body></html>
