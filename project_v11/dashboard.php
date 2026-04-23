<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$user = require_login();
$page_title = 'Studio';
$tab = $_GET['tab'] ?? 'overview';

$stats = [];
foreach ([
    'saved'    => "SELECT COUNT(*) FROM saved_videos WHERE user_id=?",
    'likes'    => "SELECT COUNT(*) FROM likes WHERE user_id=?",
    'playlists'=> "SELECT COUNT(*) FROM playlists WHERE user_id=?",
    'followers'=> "SELECT COUNT(*) FROM follows WHERE following_id=?",
    'following'=> "SELECT COUNT(*) FROM follows WHERE follower_id=?",
    'uploads'  => "SELECT COUNT(*) FROM uploaded_videos WHERE user_id=?",
    'views'    => "SELECT COALESCE(SUM(views),0) FROM uploaded_videos WHERE user_id=?",
] as $k => $q) {
    $s = $pdo->prepare($q); $s->execute([$user['id']]); $stats[$k] = $s->fetchColumn();
}

include __DIR__ . '/includes/header.php';
?>
<style>
.studio{display:grid;grid-template-columns:200px 1fr;min-height:calc(100vh - 56px)}
.snav{background:var(--bg2);border-right:1px solid var(--border);padding:16px 0}
.snav a{display:flex;align-items:center;gap:12px;padding:10px 20px;font-size:13px;color:var(--text2);transition:.1s;border-left:3px solid transparent}
.snav a:hover{background:var(--hover);color:var(--text)}
.snav a.on{background:var(--hover);color:var(--text);border-left-color:var(--acc,#ff0000)}
.smain{padding:28px}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px}
@media(max-width:768px){.studio{grid-template-columns:1fr}.snav{display:flex;overflow-x:auto;border-right:none;border-bottom:1px solid var(--border);padding:0}.snav a{white-space:nowrap;border-left:none;border-bottom:3px solid transparent;padding:12px 16px}.snav a.on{border-bottom-color:var(--acc,#ff0000);border-left:none}.smain{padding:16px}}
</style>

<div class="studio">
  <nav class="snav">
    <?php foreach(['overview'=>['📊','Genel'],'history'=>['🕐','Geçmiş'],'saved'=>['💾','Kaydedilenler'],'playlists'=>['📋','Listelerim'],'uploads'=>['📤','Videolarım'],'api'=>['🔑','API']] as $t=>[$ic,$lb]): ?>
    <a href="?tab=<?=$t?>" class="<?= $tab===$t?'on':'' ?>"><?=$ic?> <?=$lb?></a>
    <?php endforeach ?>
  </nav>

  <div class="smain">
    <?php if ($tab==='overview'): ?>
    <!-- Profil kartı -->
    <div class="nscard" style="padding:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:24px">
      <img src="<?= avatar_url($user['avatar']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--border)">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
          <h2 style="font-size:20px;font-weight:700"><?= e($user['full_name']?:$user['username']) ?></h2>
          <span style="font-size:11px;padding:2px 9px;border-radius:99px;font-weight:700;
            <?= match($user['membership']){'pro'=>'background:rgba(255,0,0,.15);color:var(--acc,#ff0000)','full'=>'background:rgba(124,58,237,.2);color:#a78bfa',default=>'background:var(--bg3);color:var(--text2)'} ?>">
            <?= strtoupper($user['membership']) ?>
          </span>
        </div>
        <p style="font-size:13px;color:var(--text2)">@<?= e($user['username']) ?>
          <?php if ($user['birth_date']): ?> · <?= calculate_age($user['birth_date']) ?> yaşında<?php endif ?>
        </p>
        <div style="display:flex;gap:16px;margin-top:8px;font-size:13px">
          <span><strong><?= $stats['followers'] ?></strong> <span style="color:var(--text2)">abone</span></span>
          <span><strong><?= format_views((int)$stats['views']) ?></strong> <span style="color:var(--text2)">görüntülenme</span></span>
          <span><strong><?= $stats['uploads'] ?></strong> <span style="color:var(--text2)">video</span></span>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-self:flex-start">
        <a href="/profile.php" class="nsbtn ghost sm" style="border-radius:20px">Profili Düzenle</a>
        <a href="/upload.php" class="nsbtn sm" style="border-radius:20px">+ Video Yükle</a>
      </div>
    </div>

    <!-- Stat kartları -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:14px;margin-bottom:24px">
      <?php foreach([
        ['💾',$stats['saved'],'Kaydedilen'],['❤️',$stats['likes'],'Beğenilen'],
        ['📋',$stats['playlists'],'Playlist'],['👥',$stats['followers'],'Takipçi'],
        ['📤',$stats['uploads'],'Video'],['👁️',format_views((int)$stats['views']),'Görüntülenme'],
      ] as [$ic,$val,$lb]): ?>
      <div class="stat-card">
        <div style="font-size:22px;margin-bottom:8px"><?=$ic?></div>
        <div style="font-size:22px;font-weight:700"><?=$val?></div>
        <div style="font-size:12px;color:var(--text2);margin-top:3px"><?=$lb?></div>
      </div>
      <?php endforeach ?>
    </div>

    <?php if ($user['membership']==='free'): ?>
    <div style="background:linear-gradient(135deg,rgba(255,0,0,.1),rgba(255,87,34,.05));border:1px solid rgba(255,0,0,.2);border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <p style="font-size:15px;font-weight:600;margin-bottom:4px">⭐ Pro veya Full'a Geç</p>
        <p style="font-size:13px;color:var(--text2)">Reklamsız izle, sınırsız kaydet, video indir.</p>
      </div>
      <a href="/checkout.php" class="nsbtn" style="border-radius:20px">Yükselt</a>
    </div>
    <?php endif ?>

    <?php elseif ($tab==='history'): ?>
    <?php $history=$pdo->prepare("SELECT * FROM watch_history WHERE user_id=? ORDER BY watched_at DESC LIMIT 24"); $history->execute([$user['id']]); $history=$history->fetchAll(); ?>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">🕐 İzleme Geçmişi</h2>
    <?php if ($history): ?>
    <div class="vg">
      <?php foreach ($history as $h):
        $v=['platform'=>$h['platform'],'id'=>$h['video_id'],'title'=>$h['title']??'','thumbnail'=>$h['thumbnail']??'','channel'=>$h['channel']??'','duration'=>0,'type'=>'normal','views'=>0];
        include __DIR__.'/includes/video_card.php';
      endforeach ?>
    </div>
    <?php else: ?><p style="color:var(--text2)">Geçmiş yok.</p><?php endif ?>

    <?php elseif ($tab==='saved'): ?>
    <?php $saved=$pdo->prepare("SELECT * FROM saved_videos WHERE user_id=? ORDER BY saved_at DESC LIMIT 24"); $saved->execute([$user['id']]); $saved=$saved->fetchAll(); ?>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:16px">💾 Kaydedilen Videolar</h2>
    <?php if ($saved): ?>
    <div class="vg">
      <?php foreach ($saved as $sv):
        $v=['platform'=>$sv['platform'],'id'=>$sv['video_id'],'title'=>$sv['title']??'','thumbnail'=>$sv['thumbnail']??'','channel'=>$sv['channel']??'','duration'=>$sv['duration']??0,'type'=>$sv['type']??'normal','views'=>0];
        include __DIR__.'/includes/video_card.php';
      endforeach ?>
    </div>
    <?php else: ?><p style="color:var(--text2)">Kaydedilen yok. <a href="/" style="color:var(--acc,#ff0000)">Video ara</a></p><?php endif ?>

    <?php elseif ($tab==='playlists'): ?>
    <?php $plists=$pdo->prepare("SELECT p.*,(SELECT COUNT(*) FROM playlist_videos WHERE playlist_id=p.id) vc FROM playlists p WHERE p.user_id=? ORDER BY p.created_at DESC"); $plists->execute([$user['id']]); $plists=$plists->fetchAll(); ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h2 style="font-size:16px;font-weight:600">📋 Oynatma Listeleri</h2>
      <a href="/playlist.php?action=create" class="nsbtn sm" style="border-radius:20px">+ Yeni</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
      <?php foreach ($plists as $pl): ?>
      <a href="/playlist.php?id=<?=$pl['id']?>" class="nscard" style="padding:16px;display:flex;align-items:center;gap:12px;transition:.15s" onmouseover="this.style.borderColor='var(--acc,#ff0000)'" onmouseout="this.style.borderColor='var(--border)'">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,var(--acc,#ff0000),#f97316);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">📋</div>
        <div>
          <p style="font-size:14px;font-weight:500"><?= e($pl['title']) ?></p>
          <p style="font-size:12px;color:var(--text2)"><?=$pl['vc']?> video · <?=$pl['visibility']==='public'?'🌍':'🔒'?></p>
        </div>
      </a>
      <?php endforeach ?>
      <?php if (!$plists): ?><p style="color:var(--text2)">Liste yok.</p><?php endif ?>
    </div>

    <?php elseif ($tab==='uploads'): ?>
    <?php $uploads=$pdo->prepare("SELECT uv.*,u.username,c.name as channel_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels c ON c.user_id=uv.user_id WHERE uv.user_id=? ORDER BY uv.created_at DESC"); $uploads->execute([$user['id']]); $uploads=$uploads->fetchAll(); ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h2 style="font-size:16px;font-weight:600">📤 Videolarım</h2>
      <a href="/upload.php" class="nsbtn sm" style="border-radius:20px">+ Yükle</a>
    </div>
    <div class="nscard" style="overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead style="background:var(--bg3)">
          <tr><?php foreach(['Video','Kanal/Kullanıcı','Tür','Görüntülenme','Durum','Tarih',''] as $h): ?>
          <th style="padding:10px 16px;text-align:left;font-size:11px;color:var(--text2);font-weight:600;text-transform:uppercase;white-space:nowrap"><?=$h?></th>
          <?php endforeach ?></tr>
        </thead>
        <tbody>
          <?php foreach ($uploads as $uv): ?>
          <tr style="border-top:1px solid var(--border)" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='transparent'">
            <td style="padding:10px 16px;max-width:280px">
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:60px;height:34px;border-radius:4px;background:var(--bg3);overflow:hidden;flex-shrink:0">
                  <?php if ($uv['thumbnail']): ?><img src="<?= e($uv['thumbnail']) ?>" style="width:100%;height:100%;object-fit:cover"><?php endif ?>
                </div>
                <p style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($uv['title']) ?></p>
              </div>
            </td>
            <td style="padding:10px 16px;font-size:13px;color:var(--text2)"><?=e($uv['channel_name']?:$uv['username']??'-')?></td>
            <td style="padding:10px 16px;font-size:13px;color:var(--text2)"><?=e($uv['channel_name']?:$uv['username']??'-')?></td>
            <td style="padding:10px 16px;white-space:nowrap"><span style="font-size:11px;background:var(--bg3);padding:2px 8px;border-radius:4px"><?=$uv['type']==='short'?'📱 Short':'🎬 Normal'?></span></td>
            <td style="padding:10px 16px;color:var(--text2)"><?=$uv['views']?></td>
            <td style="padding:10px 16px">
              <span style="font-size:11px;padding:2px 8px;border-radius:99px;font-weight:600;
                <?= match($uv['status']){'active'=>'background:#0d2e1a;color:#4ade80','pending'=>'background:#2d1a00;color:#fbbf24','processing'=>'background:#0d1f3a;color:#60a5fa',default=>'background:#2d0a0a;color:#f87171'} ?>">
                <?= match($uv['status']){'active'=>'Yayında','pending'=>'Bekliyor','processing'=>'İşleniyor',default=>'Reddedildi'} ?>
              </span>
            </td>
            <td style="padding:10px 16px;color:var(--text2);white-space:nowrap;font-size:12px"><?=date('d.m.Y',strtotime($uv['created_at']))?></td>
            <td style="padding:10px 16px">
              <div style="display:flex;gap:6px">
                <a href="/upload.php?edit=<?=$uv['id']?>" style="font-size:12px;color:var(--acc,#ff0000)">Düzenle</a>
                <button onclick="delUpload(<?=$uv['id']?>,this)" style="font-size:12px;color:var(--text3);background:none;border:none;cursor:pointer;font-family:'Roboto',sans-serif">Sil</button>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (!$uploads): ?><tr><td colspan="6" style="padding:40px;text-align:center;color:var(--text3)">Henüz video yok.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($tab==='api'): ?>
    <?php $ak=$pdo->prepare("SELECT * FROM api_keys WHERE user_id=? AND active=1 LIMIT 1"); $ak->execute([$user['id']]); $ak=$ak->fetch(); ?>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:4px">🔑 API Erişimi</h2>
    <p style="font-size:13px;color:var(--text2);margin-bottom:20px">REST API ile video arama ve verilerinize erişin. <a href="/developer.php" style="color:var(--acc,#ff0000)">Dokümantasyon →</a></p>
    <?php if ($ak): ?>
    <div class="nscard" style="padding:16px;margin-bottom:14px">
      <p style="font-size:12px;color:var(--text2);margin-bottom:8px">API Anahtarınız</p>
      <div style="display:flex;align-items:center;gap:10px;background:var(--bg3);border-radius:8px;padding:12px 16px">
        <code id="akv" style="flex:1;font-family:monospace;font-size:13px;color:var(--acc,#ff0000);word-break:break-all"><?= e($ak['api_key']) ?></code>
        <button onclick="navigator.clipboard.writeText(document.getElementById('akv').textContent).then(()=>showToast('Kopyalandı!'))" style="background:var(--border);border:none;color:var(--text);padding:5px 10px;border-radius:5px;font-size:12px;cursor:pointer">📋</button>
      </div>
      <p style="font-size:12px;color:var(--text3);margin-top:8px">Limit: <?=$ak['rate_limit']?> istek/saat · Kullanım: <?=$ak['usage_count']?></p>
    </div>
    <?php else: ?>
    <form method="POST" action="/ajax/create_api_key.php"><?= csrf_field() ?>
      <button type="submit" class="nsbtn sm" style="border-radius:20px">API Anahtarı Oluştur</button>
    </form>
    <?php endif ?>
    <?php endif ?>
  </div>
</div>

<script>
function delUpload(id,btn){
  if(!confirm('Sil?'))return;
  fetch('/ajax/delete_upload.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,csrf_token:NS_CSRF})})
  .then(r=>r.json()).then(d=>{if(d.success){btn.closest('tr').remove();showToast('Silindi');}});
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
