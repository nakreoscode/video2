<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
if (get_user()) redirect('/dashboard.php');
load_language($_SESSION['lang'] ?? 'tr');
$page_title = t('reset_password');
$token = trim($_GET['token'] ?? '');
$error = ''; $done = false;

$reset = $token ? verify_reset_token($token) : null;
if (!$reset && !$done) { $error = t('invalid_token'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    if (!csrf_verify()) { $error = 'Güvenlik hatası.'; }
    else {
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirm'] ?? '';
        if (strlen($pass) < 6) $error = t('short_password');
        elseif ($pass !== $pass2) $error = t('passwords_mismatch');
        else {
            if (reset_password($token, $pass)) { $done = true; }
            else $error = t('invalid_token');
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="/" class="text-3xl font-black gradient-text">🎬 <?= e(get_setting('site_title','NakreosStream')) ?></a>
      <h1 class="text-xl font-bold text-white mt-2"><?= t('reset_password') ?></h1>
    </div>
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
      <?php if ($done): ?>
        <div class="text-center py-4">
          <div class="text-5xl mb-4">✅</div>
          <h2 class="text-white font-bold mb-2">Şifre Güncellendi</h2>
          <p class="text-gray-400 text-sm">Yeni şifrenizle giriş yapabilirsiniz.</p>
          <a href="/login.php" class="inline-block mt-6 bg-red-500 hover:bg-red-600 text-white px-6 py-2.5 rounded-xl font-bold transition text-sm">Giriş Yap</a>
        </div>
      <?php elseif ($error && !$reset): ?>
        <div class="text-center py-4">
          <div class="text-5xl mb-4">❌</div>
          <p class="text-red-400"><?= e($error) ?></p>
          <a href="/forgot-password.php" class="inline-block mt-6 text-red-400 hover:text-red-300 text-sm">Yeni Link İste</a>
        </div>
      <?php else: ?>
        <?php if ($error): ?><div class="bg-red-500/20 border border-red-500/50 text-red-400 rounded-xl p-3 mb-5 text-sm"><?= e($error) ?></div><?php endif ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="space-y-4">
            <div>
              <label class="block text-gray-400 text-sm mb-1.5"><?= t('new_password') ?></label>
              <input type="password" name="password" required minlength="6" autofocus
                class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition">
            </div>
            <div>
              <label class="block text-gray-400 text-sm mb-1.5"><?= t('password_confirm') ?></label>
              <input type="password" name="password_confirm" required minlength="6"
                class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition">
            </div>
          </div>
          <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold transition mt-6">Şifreyi Güncelle</button>
        </form>
      <?php endif ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
