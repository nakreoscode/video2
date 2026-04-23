<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms.php';
start_session();
if (get_user()) redirect('/dashboard.php');
if (get_setting('sms_active','0') !== '1') {
    set_flash('error','SMS servisi aktif değil. Lütfen şifrenizle giriş yapın.');
    redirect('/login.php');
}
$page_title = 'SMS ile Giriş';
$error = '';
$step  = (int)($_SESSION['sms_login_step'] ?? 1);
$phone_display = $_SESSION['sms_login_phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Güvenlik hatası.'; }
    elseif ($step === 1) {
        $phone = trim($_POST['phone'] ?? '');
        $st = $pdo->prepare("SELECT * FROM users WHERE phone=? AND status='active' LIMIT 1");
        $st->execute([$phone]); $found = $st->fetch();
        if (!$found) { $error = 'Bu telefon numarasına kayıtlı aktif hesap bulunamadı.'; }
        else {
            $result = SMS::instance()->sendOTP($found['id'], $phone, 'login');
            if ($result['success']) {
                $_SESSION['sms_login_uid']   = $found['id'];
                $_SESSION['sms_login_step']  = 2;
                $_SESSION['sms_login_phone'] = substr($phone, 0, 4) . '****' . substr($phone, -2);
                $step = 2; $phone_display = $_SESSION['sms_login_phone'];
            } else { $error = 'SMS gönderilemedi: ' . ($result['error'] ?? 'Bilinmeyen hata'); }
        }
    } elseif ($step === 2) {
        $code = trim($_POST['code'] ?? '');
        $uid  = (int)($_SESSION['sms_login_uid'] ?? 0);
        if (!$uid) { $error = 'Oturum süresi doldu.'; $step=1; }
        elseif (SMS::verifyOTP($uid, $code, 'login')) {
            $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");
            $st->execute([$uid]); $user = $st->fetch();
            if ($user) {
                unset($_SESSION['sms_login_step'],$_SESSION['sms_login_uid'],$_SESSION['sms_login_phone']);
                login_user($user);
                set_flash('success','SMS ile giriş başarılı.');
                redirect($_GET['return'] ?? '/dashboard.php');
            } else { $error = 'Hesap bulunamadı.'; }
        } else { $error = 'Hatalı veya süresi dolmuş kod. Lütfen tekrar deneyin.'; }
    }
}
include __DIR__ . '/includes/header.php';
?>
<style>
.auth-wrap{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center;padding:24px}
.auth-box{width:100%;max-width:400px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:32px}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px}
.form-err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px}
</style>
<div class="auth-wrap">
  <div>
    <div style="text-align:center;margin-bottom:20px">
      <svg width="36" height="25" viewBox="0 0 90 64" fill="var(--acc)"><path d="M88.1 9.9C87 5.9 83.9 2.8 79.9 1.7 73 0 45 0 45 0S17 0 10.1 1.7C6.1 2.8 3 5.9 1.9 9.9 0 16.8 0 32 0 32s0 15.2 1.9 22.1C3 58.1 6.1 61.2 10.1 62.3 17 64 45 64 45 64s28 0 34.9-1.7c4-1.1 7.1-4.2 8.2-8.2C90 47.2 90 32 90 32s0-15.2-1.9-22.1z"/><path d="M36 45.6l23.3-13.6L36 18.4v27.2z" fill="white"/></svg>
    </div>
    <div class="auth-box">
      <h1 style="font-size:20px;font-weight:700;text-align:center;margin-bottom:4px">📱 SMS ile Giriş</h1>
      <p style="font-size:13px;color:var(--text2);text-align:center;margin-bottom:20px">
        <?= $step===1 ? 'Telefon numaranızı girin' : 'SMS kodu: '.$phone_display ?>
      </p>
      <?php if ($error): ?><div class="form-err"><?= e($error) ?></div><?php endif ?>
      <form method="POST">
        <?= csrf_field() ?>
        <?php if ($step===1): ?>
        <div class="form-group">
          <label class="form-label">Telefon Numarası</label>
          <input type="tel" name="phone" required autofocus class="nsinput" style="border-radius:8px" placeholder="05xx xxx xx xx">
        </div>
        <button type="submit" class="nsbtn" style="width:100%;justify-content:center;border-radius:8px;padding:12px">📨 Kod Gönder</button>
        <?php else: ?>
        <div class="form-group">
          <label class="form-label">6 Haneli SMS Kodu</label>
          <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autofocus
            class="nsinput" style="text-align:center;font-size:28px;letter-spacing:12px;font-family:monospace;border-radius:8px;padding:14px" placeholder="000000">
        </div>
        <button type="submit" class="nsbtn" style="width:100%;justify-content:center;border-radius:8px;padding:12px">Giriş Yap</button>
        <button type="button" onclick="<?= 'session_destroy' ?>;window.location.href='/login-sms.php'"
          onclick="location.href='/login-sms.php'"
          class="nsbtn ghost" style="width:100%;justify-content:center;border-radius:8px;padding:11px;margin-top:8px">← Geri</button>
        <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:10px">Kod 5 dakika geçerlidir</p>
        <?php endif ?>
      </form>
    </div>
    <p style="text-align:center;font-size:13px;color:var(--text2);margin-top:14px">
      <a href="/login.php" style="color:var(--acc)">← Şifre ile giriş yap</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
