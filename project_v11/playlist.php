<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$user = require_login();
$page_title = 'Listelerim';

$action = $_GET['action'] ?? '';
$pl_id  = (int)($_GET['id'] ?? 0);

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $title = trim($_POST['title'] ?? '');
        $vis   = in_array($_POST['visibility']??'',['public','private']) ? $_POST['visibility'] : 'public';
        if ($title) { $pdo->prepare("INSERT INTO playlists(user_id,title,visibility) VALUES(?,?,?)")->execute([$user['id'],$title,$vis]); set_flash('success','Liste oluşturuldu.'); }
        redirect('/playlist.php');
    }
    if ($act === 'delete_list') {
        $id = (int)($_POST['list_id'] ?? 0);
        $pdo->prepare("DELETE FROM playlists WHERE id=? AND user_id=?")->execute([$id,$user['id']]);
        set_flash('success','Silindi.'); redirect('/playlist.php');
    }
    if ($act === 'remove_video') {
        $id = (int)($_POST['pv_id'] ?? 0);
        $pdo->prepare("DELETE FROM playlist_videos WHERE id=? AND playlist_id IN (SELECT id FROM playlists WHERE user_id=?)")->execute([$id,$user['id']]);
        redirect('/playlist.php?id='.$pl_id);
    }
}

// Liste detayı
if ($pl_id) {
    $pl = $pdo->prepare("SELECT * FROM playlists WHERE id=? AND user_id=? LIMIT 1");
    $pl->execute([$pl_id,$user['id']]); $pl = $pl->fetch();
    if (!$pl) { set_flash('error','Liste bulunamadı.'); redirect('/playlist.php'); }
    $videos = $pdo->prepare("SELECT * FROM playlist_videos WHERE playlist_id=? ORDER BY sort_order,added_at DESC");
    $videos->execute([$pl_id]); $videos = $videos->fetchAll();
    $page_title = e($pl['title']);
}

// Tüm listeler
$plists = $pdo->prepare("SELECT p.*,(SELECT COUNT(*) FROM playlist_videos pv WHERE pv.playlist_id=p.id) vc FROM playlists p WHERE p.user_id=? ORDER BY p.created_at DESC");
$plists->execute([$user['id']]); $plists = $plists->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<style>
.pl-layout{display:grid;grid-template-columns:280px 1fr;gap:20px;padding:24px;max-width:1400px}
@media(max-width:900px){.pl-layout{grid-template-columns:1fr}}
.pl-sidebar{background:var(--bg2);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.pl-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);transition:.15s;cursor:pointer}
.pl-item:hover{background:var(--bg3)}
.pl-item.on{background:var(--bg3);border-left:3px solid var(--acc)}
.pl-item:last-child{border-bottom:none}
.vl-item{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;transition:.15s}
.vl-item:hover{background:var(--bg3)}
</style>

<div class="pl-layout">
  <!-- Sol: Liste listesi -->
  <div>
    <div class="pl-sidebar">
      <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
        <p style="font-size:14px;font-weight:600">📋 Listelerim</p>
        <button onclick="document.getElementById('new-pl-form').style.display=document.getElementById('new-pl-form').style.display==='none'?'block':'none'" class="nsbtn sm" style="border-radius:20px;padding:5px 12px">+</button>
      </div>
      <!-- Yeni liste form -->
      <div id="new-pl-form" style="display:none;padding:12px 14px;border-bottom:1px solid var(--border)">
        <form method="POST" style="display:flex;flex-direction:column;gap:8px">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <input type="text" name="title" required placeholder="Liste adı..." class="nsinput" style="border-radius:7px;padding:8px 10px;font-size:13px">
          <div style="display:flex;gap:6px">
            <select name="visibility" class="nsinput" style="flex:1;border-radius:7px;padding:7px 8px;font-size:12px">
              <option value="public">🌍 Herkese Açık</option>
              <option value="private">🔒 Özel</option>
            </select>
            <button type="submit" class="nsbtn sm" style="border-radius:7px">Oluştur</button>
          </div>
        </form>
      </div>
      <!-- Listeler -->
      <?php foreach ($plists as $p): ?>
      <div class="pl-item <?= $pl_id===$p['id']?'on':'' ?>" onclick="location.href='/playlist.php?id=<?=$p['id']?>'">
        <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--acc),#f97316);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">📋</div>
        <div style="flex:1;min-width:0">
          <p style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['title']) ?></p>
          <p style="font-size:11px;color:var(--text2)"><?= $p['vc'] ?> video · <?= $p['visibility']==='public'?'🌍':'🔒' ?></p>
        </div>
      </div>
      <?php endforeach ?>
      <?php if (!$plists): ?><p style="padding:24px;text-align:center;font-size:13px;color:var(--text3)">Henüz liste yok.</p><?php endif ?>
    </div>
  </div>

  <!-- Sağ: Seçili liste içeriği -->
  <div>
    <?php if ($pl_id && isset($pl)): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <div>
        <h1 style="font-size:20px;font-weight:700"><?= e($pl['title']) ?></h1>
        <p style="font-size:13px;color:var(--text2);margin-top:3px"><?= count($videos) ?> video · <?= $pl['visibility']==='public'?'🌍 Herkese açık':'🔒 Özel' ?></p>
      </div>
      <form method="POST" onsubmit="return confirm('Listeyi sil?')" style="display:flex;gap:6px">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_list"><input type="hidden" name="list_id" value="<?= $pl['id'] ?>">
        <button class="nsbtn ghost sm" style="border-radius:20px">🗑 Listeyi Sil</button>
      </form>
    </div>

    <?php if ($videos): ?>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach ($videos as $i=>$pv): ?>
      <div class="vl-item">
        <span style="font-size:13px;color:var(--text3);width:24px;text-align:center;flex-shrink:0"><?= $i+1 ?></span>
        <div style="width:120px;height:68px;border-radius:8px;background:var(--bg3);overflow:hidden;flex-shrink:0">
          <?php if ($pv['thumbnail']): ?><img src="<?= e($pv['thumbnail']) ?>" style="width:100%;height:100%;object-fit:cover" loading="lazy"><?php endif ?>
        </div>
        <div style="flex:1;min-width:0">
          <a href="/watch.php?platform=<?= urlencode($pv['platform']) ?>&id=<?= urlencode($pv['video_id']) ?>"
            style="font-size:14px;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($pv['title']) ?></a>
          <p style="font-size:12px;color:var(--text2);margin-top:3px"><?= e($pv['channel']??'') ?> · <?= e(ucfirst($pv['platform'])) ?></p>
          <?php if ($pv['duration']): ?><p style="font-size:11px;color:var(--text3)"><?= format_duration($pv['duration']) ?></p><?php endif ?>
        </div>
        <form method="POST">
          <?= csrf_field() ?><input type="hidden" name="action" value="remove_video"><input type="hidden" name="pv_id" value="<?= $pv['id'] ?>">
          <button class="nsbtn ghost sm" style="border-radius:99px;padding:6px 10px;font-size:12px;color:var(--text3)">✕</button>
        </form>
      </div>
      <?php endforeach ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px 20px;background:var(--bg2);border:1px solid var(--border);border-radius:12px">
      <div style="font-size:40px;margin-bottom:12px">📋</div>
      <p style="font-size:15px;font-weight:500;margin-bottom:6px">Bu liste boş</p>
      <p style="font-size:13px;color:var(--text2)">Video izlerken 3 nokta menüsünden "Listeye Ekle"ye tıklayın.</p>
      <a href="/" class="nsbtn sm" style="border-radius:20px;margin-top:14px">Video Ara</a>
    </div>
    <?php endif ?>

    <?php else: ?>
    <div style="text-align:center;padding:80px 20px;background:var(--bg2);border:1px solid var(--border);border-radius:12px">
      <div style="font-size:48px;margin-bottom:12px">📋</div>
      <p style="font-size:16px;font-weight:500;margin-bottom:6px">Liste Seç</p>
      <p style="font-size:13px;color:var(--text2)">Sol taraftan bir liste seçin veya yeni oluşturun.</p>
    </div>
    <?php endif ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
