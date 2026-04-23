<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$me = get_user();

// Kanal sahibi kimliğini belirle
$slug = trim($_GET['slug'] ?? '');
$uid  = (int)($_GET['user'] ?? 0);

if ($slug) {
    $ch = $pdo->prepare("SELECT c.*,u.username,u.avatar,u.membership,u.bio FROM channels c JOIN users u ON u.id=c.user_id WHERE c.slug=? LIMIT 1");
    $ch->execute([$slug]); $channel = $ch->fetch();
    if (!$channel) { set_flash('error','Kanal bulunamadı.'); redirect('/'); }
    $owner = ['id'=>$channel['user_id'],'username'=>$channel['username'],'avatar'=>$channel['avatar'],'membership'=>$channel['membership']];
} elseif ($uid) {
    $us = $pdo->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");
    $us->execute([$uid]); $owner = $us->fetch();
    if (!$owner) { set_flash('error','Kullanıcı bulunamadı.'); redirect('/'); }
    $ch = $pdo->prepare("SELECT * FROM channels WHERE user_id=? LIMIT 1");
    $ch->execute([$owner['id']]); $channel = $ch->fetch();
} elseif ($me) {
    $owner = $me;
    $ch = $pdo->prepare("SELECT * FROM channels WHERE user_id=? LIMIT 1");
    $ch->execute([$me['id']]); $channel = $ch->fetch();
} else {
    redirect('/login.php');
}

$is_own = $me && $me['id'] === (int)$owner['id'];
$page_title = $channel ? e($channel['name']) : e($owner['username'])."'nin Kanalı";

// Kanal oluşturma
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_channel']) && csrf_verify()) {
    $cname = trim($_POST['channel_name'] ?? '');
    $cdesc = trim($_POST['channel_desc'] ?? '');
    $ccat  = (int)($_POST['channel_cat'] ?? 0);
    if ($cname) {
        $cslug = slugify($cname) . '-' . $me['id'];
        // Banner ve avatar yükleme
        $cavatar = ''; $cbanner = '';
        foreach (['avatar'=>'ca_','banner'=>'cb_'] as $field=>$prefix) {
            if (!empty($_FILES[$field]['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                if (in_array($ext,['jpg','jpeg','png','webp'])) {
                    $dir = __DIR__.'/assets/img/channels/';
                    if (!is_dir($dir)) mkdir($dir,0755,true);
                    $fname = $prefix.$me['id'].'_'.time().'.'.$ext;
                    move_uploaded_file($_FILES[$field]['tmp_name'], $dir.$fname);
                    if($field==='avatar')$cavatar='/assets/img/channels/'.$fname; else $cbanner='/assets/img/channels/'.$fname;
                }
            }
        }
        try {
            $pdo->prepare("INSERT INTO channels(user_id,name,slug,description,avatar,banner,category_id) VALUES(?,?,?,?,?,?,?)")
                ->execute([$me['id'],$cname,$cslug,$cdesc,$cavatar,$cbanner,$ccat?:null]);
            $cid = $pdo->lastInsertId();
            $pdo->prepare("UPDATE users SET channel_id=? WHERE id=?")->execute([$cid,$me['id']]);
            send_notification($me['id'],'system','Kanalın oluşturuldu!','Kanalın başarıyla oluşturuldu. Hemen video yükle!',['icon'=>'🎉']);
            set_flash('success','Kanal oluşturuldu!');
            redirect('/channel.php');
        } catch(Exception $e) {
            set_flash('error','Kanal oluşturulamadı: '.$e->getMessage());
        }
    }
}

// Kanal güncelleme
if ($is_own && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_channel']) && $channel && csrf_verify()) {
    $cname = trim($_POST['channel_name'] ?? '');
    $cdesc = trim($_POST['channel_desc'] ?? '');
    if ($cname) {
        $cavatar = $channel['avatar']; $cbanner = $channel['banner'];
        foreach (['avatar'=>'ca_','banner'=>'cb_'] as $field=>$prefix) {
            if (!empty($_FILES[$field]['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                if (in_array($ext,['jpg','jpeg','png','webp'])) {
                    $dir = __DIR__.'/assets/img/channels/';
                    if (!is_dir($dir)) mkdir($dir,0755,true);
                    $fname = $prefix.$me['id'].'_'.time().'.'.$ext;
                    move_uploaded_file($_FILES[$field]['tmp_name'], $dir.$fname);
                    if($field==='avatar')$cavatar='/assets/img/channels/'.$fname; else $cbanner='/assets/img/channels/'.$fname;
                }
            }
        }
        $pdo->prepare("UPDATE channels SET name=?,description=?,avatar=?,banner=? WHERE id=?")->execute([$cname,$cdesc,$cavatar,$cbanner,$channel['id']]);
        set_flash('success','Kanal güncellendi.'); redirect('/channel.php');
    }
}

// Takip kontrolü
$is_following = false;
if ($me && !$is_own) {
    $fs = $pdo->prepare("SELECT id FROM follows WHERE follower_id=? AND following_id=?");
    $fs->execute([$me['id'], $owner['id']]); $is_following = (bool)$fs->fetch();
}

// İstatistikler
$follower_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id=?");
$follower_count->execute([$owner['id']]); $follower_count=$follower_count->fetchColumn();

$videos = $pdo->prepare("SELECT * FROM uploaded_videos WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 20");
$videos->execute([$owner['id']]); $videos=$videos->fetchAll();

$total_views = $pdo->prepare("SELECT COALESCE(SUM(views),0) FROM uploaded_videos WHERE user_id=?");
$total_views->execute([$owner['id']]); $total_views=(int)$total_views->fetchColumn();

$cats = $pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<style>
.ch-banner{height:200px;background:linear-gradient(135deg,var(--bg3),var(--bg2));position:relative;overflow:hidden}
.ch-banner img{width:100%;height:100%;object-fit:cover}
.ch-banner-edit{position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.7);color:#fff;border:none;padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;backdrop-filter:blur(8px)}
.ch-info{padding:0 24px}
.ch-av-row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:-40px;margin-bottom:14px;position:relative;z-index:1}
.ch-av{width:80px;height:80px;border-radius:50%;border:4px solid var(--bg);object-fit:cover;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--text2);flex-shrink:0;overflow:hidden}
.ch-tabs{display:flex;border-bottom:1px solid var(--border);padding:0 24px;overflow-x:auto}
.ch-tabs::-webkit-scrollbar{height:0}
.chtab{padding:12px 20px;font-size:14px;font-weight:500;color:var(--text2);border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:.15s;text-decoration:none}
.chtab:hover{color:var(--text)}.chtab.on{color:var(--text);border-bottom-color:var(--acc)}
.create-card{max-width:500px;margin:40px auto;padding:32px;background:var(--bg2);border:1px solid var(--border);border-radius:16px;text-align:center}
@media(max-width:600px){.ch-info{padding:0 14px}.ch-tabs{padding:0 14px}}
</style>

<?php if (!$channel && $is_own): ?>
<!-- Kanal Yok — Oluştur -->
<div class="pg">
  <div class="create-card">
    <div style="font-size:56px;margin-bottom:16px">📺</div>
    <h1 style="font-size:20px;font-weight:700;margin-bottom:8px">Kanalını Oluştur</h1>
    <p style="font-size:14px;color:var(--text2);margin-bottom:24px">Video yükleyip paylaşmak için bir kanal oluştur.</p>
    <form method="POST" enctype="multipart/form-data" style="text-align:left">
      <?=csrf_field()?>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kanal Adı *</label>
        <input type="text" name="channel_name" required class="nsinput" style="border-radius:8px" placeholder="Kanalınıza bir isim verin">
      </div>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Açıklama</label>
        <textarea name="channel_desc" rows="3" class="nsinput" style="border-radius:8px;resize:vertical" placeholder="Kanalınızı açıklayın..."></textarea>
      </div>
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kategori</label>
        <select name="channel_cat" class="nsinput" style="border-radius:8px">
          <option value="">Seçin...</option>
          <?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=$c['icon']?> <?=e($c['name'])?></option><?php endforeach?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kanal Avatarı</label>
          <input type="file" name="avatar" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kapak Fotoğrafı</label>
          <input type="file" name="banner" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
        </div>
      </div>
      <button type="submit" name="create_channel" class="nsbtn" style="width:100%;justify-content:center;border-radius:10px;padding:12px">
        🚀 Kanalı Oluştur
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- Kanal Var — Göster -->
<div class="ch-banner">
  <?php if($channel && $channel['banner']): ?>
  <img src="<?=e($channel['banner'])?>" alt="">
  <?php else: ?>
  <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--acc) 0%,var(--bg3) 100%)"></div>
  <?php endif?>
  <?php if($is_own): ?>
  <label class="ch-banner-edit" style="cursor:pointer">
    ✏️ Kapağı Düzenle
    <input type="file" accept="image/*" style="display:none" onchange="updateBanner(this)">
  </label>
  <?php endif?>
</div>

<div class="ch-info">
  <div class="ch-av-row">
    <div style="display:flex;align-items:flex-end;gap:16px">
      <?php $av=$channel?$channel['avatar']:($owner['avatar']??''); ?>
      <?php if($av&&file_exists(__DIR__.$av)): ?>
      <img src="<?=e($av)?>" class="ch-av" alt="">
      <?php elseif($av&&filter_var($av,FILTER_VALIDATE_URL)): ?>
      <img src="<?=e($av)?>" class="ch-av" alt="">
      <?php else: ?>
      <div class="ch-av"><?=strtoupper(substr($channel?$channel['name']:$owner['username'],0,1))?></div>
      <?php endif?>
      <div style="padding-bottom:8px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h1 style="font-size:22px;font-weight:700"><?=e($channel?$channel['name']:$owner['username'])?></h1>
          <?php $m=$owner['membership']??'free'; if($m==='premium'): ?>
          <span class="mem-badge mem-premium">Premium</span>
          <?php elseif($m==='ultimate'): ?>
          <span class="mem-badge mem-ultimate">Ultimate</span>
          <?php endif?>
          <?php if($channel&&$channel['is_verified']): ?><span style="color:#3ea6ff;font-size:18px" title="Doğrulanmış">✓</span><?php endif?>
        </div>
        <p style="font-size:13px;color:var(--text2);margin-top:2px">
          @<?=e($owner['username'])?> · <strong><?=number_format($follower_count)?></strong> abone · <strong><?=format_views($total_views)?></strong> görüntülenme
        </p>
        <?php if($channel&&$channel['description']): ?>
        <p style="font-size:13px;color:var(--text2);margin-top:4px;max-width:500px"><?=e(substr($channel['description'],0,150))?></p>
        <?php endif?>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:8px;flex-wrap:wrap">
      <?php if($is_own): ?>
      <a href="/upload.php" class="nsbtn sm" style="border-radius:20px">+ Video Yükle</a>
      <button onclick="document.getElementById('ch-edit-modal').style.display='flex'" class="nsbtn ghost sm" style="border-radius:20px">✏️ Düzenle</button>
      <?php elseif($me): ?>
      <button id="follow-btn" onclick="doFollow(<?=$owner['id']?>)" class="nsbtn <?=$is_following?'ghost':''?> sm" style="border-radius:20px">
        <?=$is_following?'✓ Abone Olundu':'Abone Ol'?>
      </button>
      <?php else: ?>
      <a href="/login.php" class="nsbtn sm" style="border-radius:20px">Abone Ol</a>
      <?php endif?>
    </div>
  </div>
</div>

<!-- Sekmeler -->
<div class="ch-tabs">
  <?php $tab=$_GET['tab']??'videos'; ?>
  <a href="?<?=$slug?"slug={$slug}":"user={$owner['id']}"?>&tab=videos" class="chtab <?=$tab==='videos'?'on':''?>">Videolar</a>
  <a href="?<?=$slug?"slug={$slug}":"user={$owner['id']}"?>&tab=about" class="chtab <?=$tab==='about'?'on':''?>">Hakkında</a>
  <?php if($is_own): ?><a href="?tab=settings" class="chtab <?=$tab==='settings'?'on':''?>">⚙️ Ayarlar</a><?php endif?>
</div>

<div style="padding:24px">
<?php if($tab==='videos'): ?>
  <?php if($videos): ?>
  <div class="vg">
    <?php foreach($videos as $uv):
      $v=['platform'=>'local','id'=>$uv['id'],'title'=>$uv['title'],'thumbnail'=>$uv['thumbnail']??'','channel'=>$channel?$channel['name']:$owner['username'],'duration'=>$uv['duration']??0,'type'=>$uv['type']??'normal','views'=>$uv['views']??0];
      include __DIR__.'/includes/video_card.php';
    endforeach?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:60px 20px">
    <div style="font-size:48px;margin-bottom:12px">🎬</div>
    <p style="font-size:16px;font-weight:500">Henüz video yok</p>
    <?php if($is_own): ?><a href="/upload.php" class="nsbtn sm" style="border-radius:20px;margin-top:12px">+ Video Yükle</a><?php endif?>
  </div>
  <?php endif?>

<?php elseif($tab==='about'): ?>
  <div style="max-width:600px">
    <div class="nscard" style="padding:20px">
      <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">Kanal Bilgileri</h3>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <?php foreach([
          ['Kanal', e($channel?$channel['name']:$owner['username'])],
          ['Kullanıcı', '@'.e($owner['username'])],
          ['Abone', number_format($follower_count)],
          ['Görüntülenme', format_views($total_views)],
          ['Video', count($videos)],
          $channel?['Oluşturuldu', date('F Y',strtotime($channel['created_at']))]:null,
        ] as $row): if(!$row) continue; [$k,$v2]=$row; ?>
        <div style="display:flex;gap:16px"><span style="color:var(--text2);width:140px;flex-shrink:0"><?=$k?></span><span><?=$v2?></span></div>
        <?php endforeach?>
      </div>
    </div>
  </div>

<?php elseif($tab==='settings' && $is_own && $channel): ?>
  <div style="max-width:600px">
    <div class="nscard" style="padding:20px">
      <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">Kanal Ayarları</h3>
      <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">
        <?=csrf_field()?>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kanal Adı</label>
          <input type="text" name="channel_name" value="<?=e($channel['name'])?>" class="nsinput" style="border-radius:8px">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Açıklama</label>
          <textarea name="channel_desc" rows="3" class="nsinput" style="border-radius:8px;resize:vertical"><?=e($channel['description']??'')?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Avatar</label>
            <input type="file" name="avatar" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kapak Fotoğrafı</label>
            <input type="file" name="banner" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
          </div>
        </div>
        <button type="submit" name="update_channel" class="nsbtn sm" style="border-radius:20px;align-self:flex-start">💾 Kaydet</button>
      </form>
    </div>
  </div>
<?php endif?>
</div>

<!-- Kanal Düzenleme Modal -->
<?php if($is_own&&$channel): ?>
<div id="ch-edit-modal" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,.7);align-items:center;justify-content:center">
  <div class="nscard" style="width:420px;padding:24px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px">
      <p style="font-size:15px;font-weight:600">Kanalı Düzenle</p>
      <button onclick="document.getElementById('ch-edit-modal').style.display='none'" style="background:none;border:none;color:var(--text2);cursor:pointer;font-size:20px">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">
      <?=csrf_field()?>
      <div>
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kanal Adı</label>
        <input type="text" name="channel_name" value="<?=e($channel['name'])?>" class="nsinput" style="border-radius:8px">
      </div>
      <div>
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Açıklama</label>
        <textarea name="channel_desc" rows="3" class="nsinput" style="border-radius:8px;resize:vertical"><?=e($channel['description']??'')?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Avatar</label>
          <input type="file" name="avatar" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;margin-bottom:5px">Kapak</label>
          <input type="file" name="banner" accept="image/*" class="nsinput" style="padding:8px;border-radius:8px;font-size:12px">
        </div>
      </div>
      <button type="submit" name="update_channel" class="nsbtn" style="border-radius:20px;justify-content:center">💾 Kaydet</button>
    </form>
  </div>
</div>
<?php endif?>
<?php endif?>

<script>
function doFollow(uid){
  if(!NS_USER)return location.href='/login.php';
  fetch('/ajax/follow.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:uid,csrf_token:NS_CSRF})})
  .then(function(r){return r.json()}).then(function(d){
    var b=document.getElementById('follow-btn');
    if(d.following){b.textContent='✓ Abone Olundu';b.classList.add('ghost');}
    else{b.textContent='Abone Ol';b.classList.remove('ghost');}
    showToast(d.message||'OK');
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
