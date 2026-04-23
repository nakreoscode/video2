<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
start_session();
if (get_user()) redirect('/dashboard.php');
load_language($_SESSION['lang'] ?? 'tr');
$page_title = t('forgot_password');
$sent = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Güvenlik hatası.'; }
    else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('invalid_email');
        } else {
            $token = create_password_reset($email);
            if ($token) {
                try { (new Mailer())->passwordReset($email, $token); } catch(Exception $e) {}
            }
            $sent = true; // Her zaman başarı göster (güvenlik)
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="/" class="text-3xl font-black gradient-text">🎬 <?= e(get_setting('site_title','NakreosStream')) ?></a>
      <h1 class="text-xl font-bold text-white mt-2"><?= t('forgot_password') ?></h1>
    </div>
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
      <?php if ($sent): ?>
        <div class="text-center py-4">
          <div class="text-5xl mb-4">📧</div>
          <h2 class="text-white font-bold mb-2">E-posta Gönderildi</h2>
          <p class="text-gray-400 text-sm">Eğer bu e-posta kayıtlıysa, şifre sıfırlama bağlantısı gönderildi. Lütfen gelen kutunuzu kontrol edin.</p>
          <a href="/login.php" class="inline-block mt-6 bg-red-500 hover:bg-red-600 text-white px-6 py-2.5 rounded-xl font-bold transition text-sm">Giriş Yap</a>
        </div>
      <?php else: ?>
        <?php if ($error): ?><div class="bg-red-500/20 border border-red-500/50 text-red-400 rounded-xl p-3 mb-5 text-sm"><?= e($error) ?></div><?php endif ?>
        <p class="text-gray-400 text-sm mb-5">E-posta adresinizi girin, şifre sıfırlama bağlantısı gönderelim.</p>
        <form method="POST">
          <?= csrf_field() ?>
          <div class="mb-5">
            <label class="block text-gray-400 text-sm mb-1.5"><?= t('email') ?></label>
            <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"
              class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition">
          </div>
          <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold transition">Gönder</button>
        </form>
        <p class="text-center text-gray-500 text-sm mt-5">
          <a href="/login.php" class="text-red-400 hover:text-red-300">← Giriş Yap</a>
        </p>
      <?php endif ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
