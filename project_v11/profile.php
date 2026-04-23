<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$me = get_user();

$username = trim($_GET['username'] ?? '');
if ($username && $me && $username !== $me['username']) {
    $st = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
    $st->execute([$username]); $profile_user = $st->fetch();
    if (!$profile_user) { http_response_code(404); die('Kullanıcı bulunamadı.'); }
    $is_own = false;
} elseif ($me) {
    $profile_user = $me; $is_own = true;
} else { redirect('/login.php?return='.urlencode($_SERVER['REQUEST_URI'])); }

$page_title = e($profile_user['full_name'] ?: $profile_user['username']);
$tab = $_GET['tab'] ?? 'videos';
$errors = []; $success = '';

// İzleyen mi?
$is_following = false;
if ($me && !$is_own) {
    $fs = $pdo->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
    $fs->execute([$me['id'],$profile_user['id']]); $is_following = (bool)$fs->fetch();
}

$followers   = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?"); $followers->execute([$profile_user['id']]); $followers=$followers->fetchColumn();
$following   = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id=?"); $following->execute([$profile_user['id']]); $following=$following->fetchColumn();
$uploads     = $pdo->prepare("SELECT * FROM uploaded_videos WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 12"); $uploads->execute([$profile_user['id']]); $uploads=$uploads->fetchAll();
$public_pl   = $pdo->prepare("SELECT * FROM playlists WHERE user_id=? AND visibility='public' ORDER BY created_at DESC LIMIT 6"); $public_pl->execute([$profile_user['id']]); $public_pl=$public_pl->fetchAll();
$total_views = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM uploaded_videos WHERE user_id=?"); $total_views->execute([$profile_user['id']]); $total_views=$total_views->fetchColumn();

// POST işlemleri (sadece kendi profili)
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['profile_action'] ?? '';

    // Profil bilgileri güncelle
    if ($action === 'update_info') {
        $full_name = trim($_POST['full_name'] ?? '');
        $bio       = trim($_POST['bio'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $birth     = trim($_POST['birth_date'] ?? '');
        $twitter   = trim($_POST['twitter'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $youtube_s = trim($_POST['youtube'] ?? '');
        $tiktok    = trim($_POST['tiktok'] ?? '');
        $lang_set  = in_array($_POST['lang']??'',['tr','en','de','az','es','ru','zh','ar']) ? $_POST['lang'] : 'tr';
        $avatar    = $profile_user['avatar'];

        if (!empty($_FILES['avatar']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png','webp','gif'])) {
                $dir = __DIR__.'/assets/img/avatars/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $fname = 'avatar_'.$profile_user['id'].'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'],$dir.$fname)) $avatar=$fname;
            }
        }
        $pdo->prepare("UPDATE users SET full_name=?,bio=?,phone=?,birth_date=?,twitter=?,instagram=?,youtube=?,tiktok=?,lang=?,avatar=? WHERE id=?")
            ->execute([$full_name,$bio,$phone ?: null,!empty($birth)?$birth:null,$twitter,$instagram,$youtube_s,$tiktok,$lang_set,$avatar,$profile_user['id']]);
        set_flash('success','Profil güncellendi.'); redirect('/profile.php');
    }

    // Kullanıcı adı değiştir
    if ($action === 'change_username') {
        $new_uname = trim($_POST['new_username'] ?? '');
        if (strlen($new_uname) < 3) { $errors[] = 'En az 3 karakter.'; }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_uname)) { $errors[] = 'Harf, rakam ve _ kullanılabilir.'; }
        else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=? AND id!=? LIMIT 1");
            $chk->execute([$new_uname,$profile_user['id']]);
            if ($chk->fetch()) { $errors[] = 'Bu kullanıcı adı alınmış.'; }
            else {
                $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$new_uname,$profile_user['id']]);
                $_SESSION['username'] = $new_uname;
                set_flash('success','Kullanıcı adı güncellendi.'); redirect('/profile.php');
            }
        }
    }

    // Şifre değiştir
    if ($action === 'change_password') {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';
        $st   = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $st->execute([$profile_user['id']]); $row=$st->fetch();
        if (!password_verify($old,$row['password'])) { $errors[] = 'Mevcut şifre yanlış.'; }
        elseif (strlen($new) < 6) { $errors[] = 'Yeni şifre en az 6 karakter.'; }
        elseif ($new !== $new2) { $errors[] = 'Yeni şifreler eşleşmiyor.'; }
        else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$profile_user['id']]);
            set_flash('success','Şifre güncellendi.'); redirect('/profile.php');
        }
    }

    // SMS ile giriş toggle
    if ($action === 'toggle_sms') {
        $enabled = isset($_POST['sms_login_enabled']) ? 1 : 0;
        // Phone zorunlu
        if ($enabled && !$profile_user['phone']) { $errors[] = 'SMS girişi için telefon numarası gerekli. Önce profil bilgilerinden telefon ekleyin.'; }
        else {
            $pdo->prepare("UPDATE users SET sms_login_enabled=? WHERE id=?")->execute([$enabled,$profile_user['id']]);
            set_flash('success', $enabled ? 'SMS ile giriş aktifleştirildi.' : 'SMS ile giriş devre dışı bırakıldı.');
            redirect('/profile.php?tab=settings');
        }
    }

    // Yenile
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $st->execute([$profile_user['id']]); $profile_user = $st->fetch();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.ch-banner{height:160px;background:linear-gradient(120deg,#1a1a2e 0%,#16213e 40%,#0f3460 100%);position:relative;overflow:hidden}
.ch-banner::after{content:'';position:absolute;inset:0;background:rgba(0,0,0,.3)}
.ch-header{padding:0 24px}
.ch-av-row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:-40px;margin-bottom:14px;position:relative;z-index:1}
.ch-avatar{width:80px;height:80px;border-radius:50%;border:4px solid var(--bg);object-fit:cover;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--text2);flex-shrink:0;overflow:hidden}
.ch-tabs{display:flex;border-bottom:1px solid var(--border);padding:0 24px;overflow-x:auto;gap:0}
.ch-tabs::-webkit-scrollbar{height:0}
.chtab{padding:12px 20px;font-size:14px;font-weight:500;color:var(--text2);border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:.15s}
.chtab:hover{color:var(--text)}
.chtab.on{color:var(--text);border-bottom-color:var(--text)}
.ch-content{padding:24px}
.settings-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px}
.settings-card h3{font-size:14px;font-weight:600;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}.ch-content{padding:14px}.ch-header{padding:0 14px}}
</style>

<div class="ch-banner"></div>
<div class="ch-header">
  <div class="ch-av-row">
    <div style="display:flex;align-items:flex-end;gap:16px">
      <?php if ($profile_user['avatar']&&$profile_user['avatar']!=='default.png'&&file_exists(__DIR__.'/assets/img/avatars/'.$profile_user['avatar'])): ?>
      <img src="/assets/img/avatars/<?= e($profile_user['avatar']) ?>" class="ch-avatar" alt="">
      <?php else: ?>
      <div class="ch-avatar"><?= strtoupper(substr($profile_user['username'],0,1)) ?></div>
      <?php endif ?>
      <div style="padding-bottom:6px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h1 style="font-size:20px;font-weight:700"><?= e($profile_user['full_name']?:$profile_user['username']) ?></h1>
          <?php $mem=$profile_user['membership']; if ($mem==='premium'): ?>
          <span style="font-size:11px;padding:2px 8px;border-radius:99px;font-weight:700;background:linear-gradient(135deg,#1a73e8,#0d47a1);color:#fff">Premium</span>
          <?php elseif ($mem==='ultimate'): ?>
          <span style="font-size:11px;padding:2px 8px;border-radius:99px;font-weight:700;background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff">Ultimate</span>
          <?php endif ?>
        </div>
        <p style="font-size:13px;color:var(--text2)">@<?= e($profile_user['username']) ?>
          <?php if ($profile_user['birth_date']): ?> · <?= calculate_age($profile_user['birth_date']) ?> yaşında<?php endif ?>
          <?php if ($profile_user['phone']): ?> · <?= e(substr($profile_user['phone'],0,4)).'****' ?><?php endif ?>
        </p>
        <p style="font-size:13px;color:var(--text2);margin-top:2px">
          <strong style="color:var(--text)"><?= $followers ?></strong> abone
          · <strong style="color:var(--text)"><?= $following ?></strong> takip
          · <strong style="color:var(--text)"><?= format_views((int)$total_views) ?></strong> görüntülenme
        </p>
        <?php if ($profile_user['bio']): ?>
        <p style="font-size:13px;color:var(--text2);margin-top:4px;max-width:500px;line-height:1.5"><?= nl2br(e($profile_user['bio'])) ?></p>
        <?php endif ?>
      </div>
    </div>
    <div style="display:flex;gap:6px;align-items:flex-end;padding-bottom:6px;flex-wrap:wrap">
      <?php if (!$is_own && $me): ?>
      <button id="follow-btn" onclick="doFollow(<?=$profile_user['id']?>)"
        class="nsbtn <?= $is_following?'ghost':'' ?> sm" style="border-radius:20px">
        <?= $is_following?'✓ Abone Olundu':'Abone Ol' ?>
      </button>
      <?php endif ?>
      <?php foreach(['twitter'=>['🐦','https://twitter.com/'],'instagram'=>['📷','https://instagram.com/'],'youtube'=>['📺','https://youtube.com/@'],'tiktok'=>['🎵','https://tiktok.com/@']] as $k=>[$icon,$base]): ?>
      <?php if ($profile_user[$k]): ?><a href="<?=$base.e($profile_user[$k])?>" target="_blank" class="nsbtn ghost sm" style="border-radius:20px;padding:7px 10px"><?=$icon?></a><?php endif ?>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- Sekmeler -->
<div class="ch-tabs">
  <?php
  $tabs = ['videos'=>'Videolar','playlists'=>'Listeler','about'=>'Hakkında'];
  if ($is_own) $tabs['settings'] = '⚙️ Hesap Ayarları';
  foreach ($tabs as $t=>$lb):
  ?>
  <a href="?<?= $username?'username='.urlencode($username).'&':'' ?>tab=<?=$t?>" class="chtab <?= $tab===$t?'on':'' ?>"><?=$lb?></a>
  <?php endforeach ?>
</div>

<div class="ch-content">
<?php if ($errors): ?>
<div style="background:#2d0a0a;border:1px solid #dc2626;color:#f87171;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px"><?= implode('<br>',array_map('e',$errors)) ?></div>
<?php endif ?>

<?php if ($tab === 'videos'): ?>
  <?php if ($uploads): ?>
  <div class="vg">
    <?php foreach ($uploads as $uv):
      $v=['platform'=>'local','id'=>$uv['id'],'title'=>$uv['title'],'thumbnail'=>$uv['thumbnail']??'','channel'=>$profile_user['username'],'duration'=>$uv['duration']??0,'type'=>$uv['type'],'views'=>$uv['views']];
      include __DIR__.'/includes/video_card.php';
    endforeach ?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:60px 20px">
    <div style="font-size:48px;margin-bottom:12px">🎬</div>
    <p style="font-size:16px;font-weight:500;margin-bottom:6px">Henüz video yok</p>
    <?php if ($is_own): ?><a href="/upload.php" class="nsbtn sm" style="border-radius:20px;margin-top:10px">+ Video Yükle</a><?php endif ?>
  </div>
  <?php endif ?>

<?php elseif ($tab === 'playlists'): ?>
  <?php if ($public_pl): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
    <?php foreach ($public_pl as $pl): ?>
    <a href="/playlist.php?id=<?=$pl['id']?>" class="nscard" style="padding:16px;display:flex;align-items:center;gap:12px;transition:.15s" onmouseover="this.style.borderColor='var(--acc)'" onmouseout="this.style.borderColor='var(--border)'">
      <div style="width:44px;height:44px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">📋</div>
      <div><p style="font-size:13px;font-weight:500"><?= e($pl['title']) ?></p><p style="font-size:11px;color:var(--text2)">🌍 Herkese açık</p></div>
    </a>
    <?php endforeach ?>
  </div>
  <?php else: ?><p style="color:var(--text2)">Liste yok.</p><?php endif ?>

<?php elseif ($tab === 'about'): ?>
  <div style="max-width:600px">
    <div class="nscard" style="padding:20px">
      <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">Kanal Bilgileri</h3>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <?php foreach([
          ['Kullanıcı Adı','@'.e($profile_user['username'])],
          $profile_user['full_name']?['Ad Soyad',e($profile_user['full_name'])]:null,
          $profile_user['birth_date']?['Yaş',calculate_age($profile_user['birth_date']).' yaşında']:null,
          ['Üyelik',strtoupper($profile_user['membership'])],
          ['Katılım',date('F Y',strtotime($profile_user['created_at']))],
          ['Toplam Görüntülenme',format_views((int)$total_views)],
          ['Abone',number_format($followers)],
        ] as $row): if (!$row) continue; [$k,$v]=$row; ?>
        <div style="display:flex;gap:16px"><span style="color:var(--text2);width:160px;flex-shrink:0"><?=$k?></span><span><?=$v?></span></div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'settings' && $is_own): ?>
  <div style="max-width:600px">

    <!-- Profil Bilgileri -->
    <div class="settings-card">
      <h3>👤 Profil Bilgileri</h3>
      <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">
        <?= csrf_field() ?><input type="hidden" name="profile_action" value="update_info">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:4px">
          <?php if ($profile_user['avatar']&&$profile_user['avatar']!=='default.png'&&file_exists(__DIR__.'/assets/img/avatars/'.$profile_user['avatar'])): ?>
          <img src="/assets/img/avatars/<?= e($profile_user['avatar']) ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover">
          <?php else: ?>
          <div style="width:60px;height:60px;border-radius:50%;background:var(--acc);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff"><?= strtoupper(substr($profile_user['username'],0,1)) ?></div>
          <?php endif ?>
          <div>
            <p style="font-size:13px;font-weight:500;margin-bottom:4px">Profil Fotoğrafı</p>
            <input type="file" name="avatar" accept="image/*" style="font-size:12px;color:var(--text2)">
          </div>
        </div>
        <div class="form-row">
          <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Ad Soyad</label><input type="text" name="full_name" value="<?= e($profile_user['full_name']??'') ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
          <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Telefon</label><input type="tel" name="phone" value="<?= e($profile_user['phone']??'') ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px" placeholder="05xx..."></div>
        </div>
        <div class="form-row">
          <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Doğum Tarihi</label><input type="date" name="birth_date" value="<?= e($profile_user['birth_date']??'') ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
          <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Dil</label>
          <select name="lang" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px">
            <?php foreach(['tr'=>'🇹🇷 Türkçe','en'=>'🇺🇸 English','de'=>'🇩🇪 Deutsch','az'=>'🇦🇿 Azərbaycan','es'=>'🇪🇸 Español','ru'=>'🇷🇺 Русский','zh'=>'🇨🇳 中文','ar'=>'🇸🇦 العربية'] as $c=>$ln): ?>
            <option value="<?=$c?>" <?= ($profile_user['lang']??'tr')===$c?'selected':'' ?>><?=$ln?></option>
            <?php endforeach ?>
          </select></div>
        </div>
        <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Hakkımda</label><textarea name="bio" rows="3" class="nsinput" style="resize:none;font-size:13px;border-radius:8px;padding:9px 12px"><?= e($profile_user['bio']??'') ?></textarea></div>
        <div class="form-row">
          <?php foreach([['twitter','🐦 Twitter'],['instagram','📷 Instagram'],['youtube','📺 YouTube'],['tiktok','🎵 TikTok']] as [$n,$l]): ?>
          <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$n?>" value="<?= e($profile_user[$n]??'') ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px" placeholder="kullanici_adi"></div>
          <?php endforeach ?>
        </div>
        <button type="submit" class="nsbtn sm" style="border-radius:20px;align-self:flex-start">💾 Kaydet</button>
      </form>
    </div>

    <!-- Kullanıcı Adı Değiştir -->
    <div class="settings-card">
      <h3>🔤 Kullanıcı Adını Değiştir</h3>
      <form method="POST" style="display:flex;gap:10px;align-items:flex-end">
        <?= csrf_field() ?><input type="hidden" name="profile_action" value="change_username">
        <div style="flex:1"><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Yeni Kullanıcı Adı</label>
        <input type="text" name="new_username" required minlength="3" value="<?= e($profile_user['username']) ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
        <button type="submit" class="nsbtn sm" style="border-radius:20px">Değiştir</button>
      </form>
      <p style="font-size:12px;color:var(--text3);margin-top:8px">Harf, rakam ve _ kullanılabilir. En az 3 karakter.</p>
    </div>

    <!-- Şifre Değiştir -->
    <div class="settings-card">
      <h3>🔒 Şifre Değiştir</h3>
      <form method="POST" style="display:flex;flex-direction:column;gap:10px">
        <?= csrf_field() ?><input type="hidden" name="profile_action" value="change_password">
        <?php foreach([['old_password','Mevcut Şifre'],['new_password','Yeni Şifre'],['new_password2','Yeni Şifre (Tekrar)']] as [$n,$l]): ?>
        <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px"><?=$l?></label>
        <input type="password" name="<?=$n?>" required minlength="<?= $n==='old_password'?1:6 ?>" class="nsinput" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
        <?php endforeach ?>
        <button type="submit" class="nsbtn sm" style="border-radius:20px;align-self:flex-start">Şifreyi Güncelle</button>
      </form>
    </div>

    <!-- SMS ile Giriş -->
    <?php if (get_setting('sms_active','0') === '1'): ?>
    <div class="settings-card">
      <h3>📱 SMS ile Giriş</h3>
      <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Aktif edilirse giriş yaparken SMS doğrulama kodu istenir.</p>
      <?php if (!$profile_user['phone']): ?>
      <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);border-radius:8px;padding:12px;font-size:13px;color:#fbbf24">
        ⚠️ SMS girişi için önce profil bilgilerinden telefon numaranızı ekleyin.
      </div>
      <?php else: ?>
      <form method="POST" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <?= csrf_field() ?><input type="hidden" name="profile_action" value="toggle_sms">
        <div>
          <p style="font-size:14px;font-weight:500">SMS Doğrulama</p>
          <p style="font-size:12px;color:var(--text2)"><?= e(substr($profile_user['phone'],0,4)).'****'.substr($profile_user['phone'],-2) ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:13px;color:var(--text2)"><?= $profile_user['sms_login_enabled']?'Aktif':'Pasif' ?></span>
          <label style="position:relative;width:44px;height:24px;cursor:pointer">
            <input type="checkbox" name="sms_login_enabled" id="sms_tog" <?= $profile_user['sms_login_enabled']?'checked':'' ?> style="opacity:0;position:absolute" onchange="this.form.submit()">
            <div id="sms_track" style="width:44px;height:24px;border-radius:99px;background:<?= $profile_user['sms_login_enabled']?'var(--acc)':'var(--border)' ?>;transition:.2s;position:relative">
              <div style="position:absolute;top:2px;<?= $profile_user['sms_login_enabled']?'left:22':'left:2' ?>px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.2s"></div>
            </div>
          </label>
        </div>
      </form>
      <?php endif ?>
    </div>
    <?php endif ?>

    <!-- Üyelik -->
    <div class="settings-card">
      <h3>⭐ Üyelik Durumu</h3>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <p style="font-size:14px;font-weight:500">
            <?= match($profile_user['membership']){'premium'=>'💙 Premium Üye','ultimate'=>'💜 Ultimate Üye',default=>'⚪ Ücretsiz Üye'} ?>
          </p>
          <p style="font-size:12px;color:var(--text2);margin-top:3px">
            <?= match($profile_user['membership']){'premium'=>'Reklamsız izle, sınırsız kaydet, API erişimi','ultimate'=>'Tüm Premium özellikler + video indirme + reklam yayınlama',default=>'Reklamlı izleme, sınırlı kaydetme'} ?>
          </p>
        </div>
        <?php if ($profile_user['membership']==='free'): ?>
        <a href="/checkout.php" class="nsbtn sm" style="background:linear-gradient(135deg,#7c3aed,#db2777);border-radius:20px">Yükselt →</a>
        <?php endif ?>
      </div>
    </div>

  </div>
<?php endif ?>
</div>

<script>
function doFollow(uid){
  fetch('/ajax/follow.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:uid,csrf_token:NS_CSRF})})
  .then(r=>r.json()).then(d=>{
    const b=document.getElementById('follow-btn');
    if(d.following){b.textContent='✓ Abone Olundu';b.classList.add('ghost');}
    else{b.textContent='Abone Ol';b.classList.remove('ghost');}
  });
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
