<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
start_session();
if (get_user()) redirect('/dashboard.php');
if (get_setting('registration_open','1') !== '1') { set_flash('error','Kayıt şu an kapalı.'); redirect('/login.php'); }
$lang_code = $_COOKIE['ns_lang'] ?? ($_SESSION['lang'] ?? 'tr');
$lang = include __DIR__ . '/languages/' . (file_exists(__DIR__.'/languages/'.$lang_code.'.php')?$lang_code:'tr') . '.php';
$page_title = $lang['register'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Güvenlik hatası.'; }
    else {
        $result = register_user(array_merge($_POST, ['lang' => $lang_code]));
        if ($result['success']) {
            try { $m = new Mailer(); $st=$pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");$st->execute([$result['user_id']]);$u=$st->fetch();$m->welcome($u['email'],$u['username']); } catch(Exception $e) {}
            set_flash('success', $lang['register_success']); redirect('/login.php');
        } else { $errors = $result['errors']; }
    }
}
include __DIR__ . '/includes/header.php';
?>
<style>
.auth-wrap{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center;padding:24px}
.auth-box{width:100%;max-width:440px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:32px}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px}
.form-err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px}
</style>
<div class="auth-wrap">
  <div>
    <div style="text-align:center;margin-bottom:20px">
      <svg width="36" height="25" viewBox="0 0 90 64" fill="var(--accent)"><path d="M88.1 9.9C87 5.9 83.9 2.8 79.9 1.7 73 0 45 0 45 0S17 0 10.1 1.7C6.1 2.8 3 5.9 1.9 9.9 0 16.8 0 32 0 32s0 15.2 1.9 22.1C3 58.1 6.1 61.2 10.1 62.3 17 64 45 64 45 64s28 0 34.9-1.7c4-1.1 7.1-4.2 8.2-8.2C90 47.2 90 32 90 32s0-15.2-1.9-22.1z"/><path d="M36 45.6l23.3-13.6L36 18.4v27.2z" fill="white"/></svg>
    </div>
    <div class="auth-box">
      <h1 style="font-size:20px;font-weight:700;text-align:center;margin-bottom:20px"><?= e($lang['register']) ?></h1>
      <?php if ($errors): ?><div class="form-err"><?= implode('<br>',array_map('e',$errors)) ?></div><?php endif ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label"><?= e($lang['username']) ?> *</label>
            <input type="text" name="username" required value="<?= e($_POST['username']??'') ?>" class="nsinput" style="border-radius:8px" placeholder="kullanici_adi">
          </div>
          <div class="form-group">
            <label class="form-label"><?= e($lang['full_name']) ?></label>
            <input type="text" name="full_name" value="<?= e($_POST['full_name']??'') ?>" class="nsinput" style="border-radius:8px" placeholder="Ad Soyad">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= e($lang['email']) ?> *</label>
          <input type="email" name="email" required value="<?= e($_POST['email']??'') ?>" class="nsinput" style="border-radius:8px" placeholder="ornek@mail.com">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label"><?= e($lang['phone']) ?></label>
            <input type="tel" name="phone" value="<?= e($_POST['phone']??'') ?>" class="nsinput" style="border-radius:8px" placeholder="05xx...">
          </div>
          <div class="form-group">
            <label class="form-label"><?= e($lang['birth_date']) ?></label>
            <input type="date" name="birth_date" value="<?= e($_POST['birth_date']??'') ?>" class="nsinput" style="border-radius:8px">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= e($lang['password']) ?> *</label>
          <input type="password" name="password" required minlength="6" class="nsinput" style="border-radius:8px" placeholder="En az 6 karakter">
        </div>
        <div class="form-group">
          <label class="form-label"><?= e($lang['password_confirm']) ?> *</label>
          <input type="password" name="password_confirm" required minlength="6" class="nsinput" style="border-radius:8px">
        </div>
        <button type="submit" class="nsbtn" style="width:100%;justify-content:center;border-radius:8px;padding:12px;margin-top:4px">
          <?= e($lang['register']) ?>
        </button>
      </form>
    </div>
    <p style="text-align:center;font-size:13px;color:var(--text2);margin-top:16px">
      <?= e($lang['have_account']) ?> <a href="/login.php" style="color:var(--accent);font-weight:500"><?= e($lang['login']) ?></a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
