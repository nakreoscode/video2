<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms.php';
start_session();
$user = require_login();
load_language($user['lang'] ?? 'tr');
$page_title = 'SMS Doğrulama';
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Güvenlik hatası.'; }
    elseif (isset($_POST['send_code'])) {
        $phone = trim($_POST['phone'] ?? $user['phone'] ?? '');
        if (!$phone) { $error = 'Telefon numarası girin.'; }
        else {
            $sms = SMS::instance();
            $res = $sms->sendOTP($user['id'], $phone, 'phone_verify');
            if ($res['success']) {
                if ($phone !== $user['phone']) {
                    $pdo->prepare("UPDATE users SET phone=? WHERE id=?")->execute([$phone, $user['id']]);
                }
                $success = 'Kod gönderildi.';
            } else { $error = 'SMS gönderilemedi: ' . ($res['error'] ?? ''); }
        }
    } elseif (isset($_POST['verify_code'])) {
        $code = trim($_POST['code'] ?? '');
        if (SMS::verifyOTP($user['id'], $code, 'phone_verify')) {
            $pdo->prepare("UPDATE users SET sms_verify_enabled=1 WHERE id=?")->execute([$user['id']]);
            set_flash('success', 'Telefon numaranız doğrulandı.');
            redirect('/dashboard.php');
        } else { $error = 'Hatalı veya süresi dolmuş kod.'; }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
      <h1 class="text-xl font-bold text-white mb-6">📱 SMS Doğrulama</h1>
      <?php if ($error): ?><div class="bg-red-500/20 border border-red-500/50 text-red-400 rounded-xl p-3 mb-4 text-sm"><?= e($error) ?></div><?php endif ?>
      <?php if ($success): ?><div class="bg-green-500/20 border border-green-500/50 text-green-400 rounded-xl p-3 mb-4 text-sm"><?= e($success) ?></div><?php endif ?>

      <!-- Telefon gönder -->
      <form method="POST" class="mb-6">
        <?= csrf_field() ?>
        <label class="block text-gray-400 text-sm mb-1.5">Telefon Numarası</label>
        <div class="flex gap-2">
          <input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="05xx..."
            class="flex-1 bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-red-500 transition">
          <button type="submit" name="send_code" class="bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-xl text-sm font-bold transition">Kod Gönder</button>
        </div>
      </form>

      <!-- Kod doğrula -->
      <form method="POST">
        <?= csrf_field() ?>
        <label class="block text-gray-400 text-sm mb-1.5">Doğrulama Kodu</label>
        <div class="flex gap-2">
          <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="000000"
            class="flex-1 bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-center text-xl font-mono tracking-widest focus:outline-none focus:border-red-500 transition">
          <button type="submit" name="verify_code" class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-xl text-sm font-bold transition">Doğrula</button>
        </div>
      </form>
      <p class="text-gray-600 text-xs mt-4 text-center">Kod 5 dakika geçerlidir.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
