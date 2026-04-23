<?php
$page_title = 'Admin Yönetimi';
require_once __DIR__ . '/includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $act = $_POST['action'] ?? '';

    if ($act === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';
        $admin = $pdo->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
        $admin->execute([$_SESSION['admin_username']]); $admin = $admin->fetch();
        if (!$admin || !password_verify($old, $admin['password'])) {
            $errors[] = 'Mevcut şifre yanlış.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'Yeni şifre en az 6 karakter olmalı.';
        } elseif ($new !== $new2) {
            $errors[] = 'Şifreler eşleşmiyor.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("UPDATE admins SET password=? WHERE id=?")->execute([$hash, $admin['id']]);
            set_flash('success', 'Şifre güncellendi.'); redirect('/admin/admins.php');
        }
    }

    if ($act === 'add_admin') {
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $fname = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = in_array($_POST['role']??'',['admin','superadmin']) ? $_POST['role'] : 'admin';
        if (!$uname || strlen($pass) < 6) {
            $errors[] = 'Kullanıcı adı ve şifre (min 6 kar.) gerekli.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare("INSERT INTO admins(username,password,full_name,email,role) VALUES(?,?,?,?,?)")->execute([$uname,$hash,$fname,$email,$role]);
                set_flash('success', 'Admin eklendi.'); redirect('/admin/admins.php');
            } catch (Exception $e) {
                $errors[] = 'Bu kullanıcı adı zaten mevcut.';
            }
        }
    }

    if ($act === 'delete_admin') {
        $aid = (int)($_POST['admin_id'] ?? 0);
        $me  = $pdo->prepare("SELECT id FROM admins WHERE username=? LIMIT 1");
        $me->execute([$_SESSION['admin_username']]); $me = $me->fetch();
        if ($aid && $aid !== (int)$me['id']) {
            $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([$aid]);
            set_flash('success', 'Admin silindi.'); redirect('/admin/admins.php');
        }
    }
}

$admins = $pdo->query("SELECT id,username,full_name,email,role,active,last_login,created_at FROM admins ORDER BY created_at")->fetchAll();
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">
  <!-- Şifre Değiştir -->
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">🔒 Şifremi Değiştir</h3>
    <?php if ($errors): ?>
    <div style="background:#2d0a0a;border:1px solid #dc2626;color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:12px"><?= implode('<br>',array_map('e',$errors)) ?></div>
    <?php endif ?>
    <form method="POST" style="display:flex;flex-direction:column;gap:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
      <?php foreach([['old_password','Mevcut Şifre'],['new_password','Yeni Şifre'],['new_password2','Yeni Şifre (Tekrar)']] as [$n,$l]): ?>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px"><?=$l?></label>
      <input type="password" name="<?=$n?>" required class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px"></div>
      <?php endforeach ?>
      <button type="submit" class="btn sm" style="border-radius:7px;align-self:flex-start">Güncelle</button>
    </form>
  </div>

  <!-- Yeni Admin -->
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">👤 Yeni Admin Ekle</h3>
    <form method="POST" style="display:flex;flex-direction:column;gap:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
      <?php foreach([['username','Kullanıcı Adı *'],['password','Şifre * (min 6)'],['full_name','Ad Soyad'],['email','E-posta']] as [$n,$l]): ?>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px"><?=$l?></label>
      <input type="<?= $n==='password'?'password':'text' ?>" name="<?=$n?>" <?= in_array($n,['username','password'])?'required':'' ?> class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px"></div>
      <?php endforeach ?>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Rol</label>
      <select name="role" class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px">
        <option value="admin">Admin</option><option value="superadmin">Süper Admin</option>
      </select></div>
      <button type="submit" class="btn sm" style="border-radius:7px;align-self:flex-start">Ekle</button>
    </form>
  </div>
</div>

<!-- Admin Listesi -->
<div class="ns-card" style="overflow:auto;margin-top:20px;max-width:900px">
  <table class="ns-table">
    <thead><tr><th>Admin</th><th>E-posta</th><th>Rol</th><th>Son Giriş</th><th>İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
    <tr>
      <td><p style="font-size:13px;font-weight:500"><?= e($a['username']) ?></p><p style="font-size:11px;color:var(--text2)"><?= e($a['full_name']??'') ?></p></td>
      <td style="font-size:13px;color:var(--text2)"><?= e($a['email']??'-') ?></td>
      <td><span class="sb <?= $a['role']==='superadmin'?'sb-red':'sb-blue' ?>"><?= ucfirst($a['role']) ?></span></td>
      <td style="font-size:12px;color:var(--text2)"><?= $a['last_login'] ? date('d.m.Y H:i',strtotime($a['last_login'])) : '-' ?></td>
      <td>
        <?php if ($a['username'] !== $_SESSION['admin_username']): ?>
        <form method="POST" onsubmit="return confirm('Sil?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_admin"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
          <button class="btn sm" style="background:#dc2626;border-radius:6px">Sil</button>
        </form>
        <?php else: ?>
        <span style="font-size:12px;color:var(--text3)">Mevcut</span>
        <?php endif ?>
      </td>
    </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

<style>@media(max-width:640px){[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}}</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
