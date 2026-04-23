<?php
$page_title = 'Reklam Yönetimi';
require_once __DIR__ . '/includes/header.php';

// DB'ye eksik kolonları ekle
try {
    $pdo->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS skip_after INT DEFAULT 5");
    $pdo->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS target_membership VARCHAR(20) DEFAULT 'all'");
    $pdo->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) DEFAULT NULL");
} catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $act = $_POST['action'] ?? '';

    // Genel ayarlar
    if ($act === 'settings') {
        set_setting('ad_mode', in_array($_POST['ad_mode']??'',['hilltopads','manual']) ? $_POST['ad_mode'] : 'hilltopads');
        set_setting('hilltop_enabled',         isset($_POST['hilltop_enabled']) ? '1' : '0');
        set_setting('hilltop_vast_url',         trim($_POST['hilltop_vast_url'] ?? ''));
        set_setting('hilltop_preroll_enabled',  isset($_POST['hilltop_preroll_enabled']) ? '1' : '0');
        set_setting('hilltop_skip_after',       (string)max(0,(int)($_POST['hilltop_skip_after']??5)));
        set_setting('hilltop_target',           in_array($_POST['hilltop_target']??'',['all','free','non_premium']) ? $_POST['hilltop_target'] : 'free');
        set_flash('success', 'Reklam ayarları kaydedildi.');
        redirect('/admin/ads.php');
    }

    // Reklam ekle/düzenle
    if ($act === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $type      = in_array($_POST['type']??'',['image','vast','video']) ? $_POST['type'] : 'image';
        $image_url = trim($_POST['image_url'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $vast_code = trim($_POST['vast_code'] ?? '');
        $link      = trim($_POST['link'] ?? '');
        $duration  = max(3,(int)($_POST['duration']??15));
        $skip      = max(0,(int)($_POST['skip_after']??5));
        $target    = in_array($_POST['target']??'',['all','free','non_premium']) ? $_POST['target'] : 'all';
        $active    = isset($_POST['active']) ? 1 : 0;

        if ($name) {
            if ($id) {
                $pdo->prepare("UPDATE ads SET name=?,type=?,image_url=?,vast_code=?,video_url=?,link=?,duration=?,skip_after=?,target_membership=?,active=? WHERE id=?")
                    ->execute([$name,$type,$image_url,$vast_code,$video_url,$link,$duration,$skip,$target,$active,$id]);
            } else {
                $pdo->prepare("INSERT INTO ads(name,type,image_url,vast_code,video_url,link,duration,skip_after,target_membership,active) VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$type,$image_url,$vast_code,$video_url,$link,$duration,$skip,$target,$active]);
            }
            set_flash('success', $id ? 'Reklam güncellendi.' : 'Reklam eklendi.');
        }
        redirect('/admin/ads.php');
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $pdo->prepare("DELETE FROM ads WHERE id=?")->execute([$id]); set_flash('success','Silindi.'); }
        redirect('/admin/ads.php');
    }

    if ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE ads SET active=1-active WHERE id=?")->execute([$id]);
        redirect('/admin/ads.php');
    }
}

$ads    = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC")->fetchAll();
$edit   = !empty($_GET['edit']) ? $pdo->query("SELECT * FROM ads WHERE id=".(int)$_GET['edit']." LIMIT 1")->fetch() : null;

function sv($k,$d='') { return htmlspecialchars(get_setting($k,$d)); }
$ad_mode = get_setting('ad_mode','hilltopads');
?>
<style>
.ad-mode-btn{flex:1;padding:16px;border-radius:12px;border:2px solid var(--border);background:var(--bg2);cursor:pointer;text-align:center;transition:.2s}
.ad-mode-btn.on{border-color:var(--acc);background:rgba(255,0,0,.05)}
.ad-mode-btn:hover{border-color:var(--acc)}
.type-tab{padding:8px 18px;border:none;background:none;color:var(--text2);font-size:14px;cursor:pointer;border-bottom:2px solid transparent;font-family:'Roboto',sans-serif}
.type-tab.on{color:var(--text);border-bottom-color:var(--acc);font-weight:600}
</style>

<!-- ══ REKLAM MODU SEÇİMİ ══ -->
<div class="ns-card" style="padding:20px;margin-bottom:16px">
  <h3 style="font-size:14px;font-weight:700;margin-bottom:14px">📺 Gösterilecek Reklam Türü</h3>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <div style="display:flex;gap:12px;margin-bottom:16px">
      <!-- HilltopAds -->
      <div class="ad-mode-btn <?= $ad_mode==='hilltopads'?'on':'' ?>" onclick="setMode('hilltopads')">
        <div style="font-size:28px;margin-bottom:6px">📡</div>
        <p style="font-size:14px;font-weight:700">HilltopAds VAST</p>
        <p style="font-size:12px;color:var(--text2);margin-top:4px">VAST tag URL ile otomatik reklam</p>
      </div>
      <!-- Manuel -->
      <div class="ad-mode-btn <?= $ad_mode==='manual'?'on':'' ?>" onclick="setMode('manual')">
        <div style="font-size:28px;margin-bottom:6px">🖼️</div>
        <p style="font-size:14px;font-weight:700">Manuel Reklam</p>
        <p style="font-size:12px;color:var(--text2);margin-top:4px">Görsel, MP4 video veya VAST kodu</p>
      </div>
    </div>
    <input type="hidden" name="ad_mode" id="ad-mode-inp" value="<?= e($ad_mode) ?>">

    <!-- HilltopAds Ayarları -->
    <div id="hilltop-settings" style="<?= $ad_mode==='hilltopads'?'':'display:none' ?>">
      <div style="border:1px solid var(--border);border-radius:10px;padding:16px;display:flex;flex-direction:column;gap:12px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px;border-radius:8px;background:var(--bg3)">
          <input type="checkbox" name="hilltop_enabled" <?= sv('hilltop_enabled')==='1'?'checked':'' ?> style="accent-color:var(--acc);width:18px;height:18px">
          <div>
            <p style="font-size:13px;font-weight:600">HilltopAds'i Etkinleştir</p>
            <p style="font-size:11px;color:var(--text2)">Kapalıyken hiçbir reklam gösterilmez</p>
          </div>
          <span style="margin-left:auto;font-size:11px;padding:2px 10px;border-radius:99px;font-weight:700;<?= sv('hilltop_enabled')==='1'?'background:#0d2e1a;color:#4ade80':'background:#2d0a0a;color:#f87171' ?>">
            <?= sv('hilltop_enabled')==='1'?'Açık':'Kapalı' ?>
          </span>
        </label>
        <div>
          <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px">VAST Tag URL <span style="color:var(--text3)">(HilltopAds → Zones → In-Stream VAST)</span></label>
          <input type="url" name="hilltop_vast_url" value="<?= sv('hilltop_vast_url') ?>" class="ns-input" style="border-radius:8px;font-size:12px;font-family:monospace" placeholder="https://...">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div>
            <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px">Skip Süresi (sn)</label>
            <input type="number" name="hilltop_skip_after" value="<?= sv('hilltop_skip_after','5') ?>" min="0" max="30" class="ns-input" style="border-radius:8px">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px">Hedef Kitle</label>
            <select name="hilltop_target" class="ns-input" style="border-radius:8px">
              <option value="all"        <?= sv('hilltop_target')==='all'       ?'selected':'' ?>>Herkes</option>
              <option value="free"       <?= sv('hilltop_target')==='free'      ?'selected':'' ?>>Sadece Ücretsiz</option>
              <option value="non_premium"<?= sv('hilltop_target')==='non_premium'?'selected':'' ?>>Premium Hariç</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px">Preroll</label>
            <label style="display:flex;align-items:center;gap:8px;padding:9px;border-radius:8px;background:var(--bg3);cursor:pointer">
              <input type="checkbox" name="hilltop_preroll_enabled" <?= sv('hilltop_preroll_enabled','1')==='1'?'checked':'' ?> style="accent-color:var(--acc)">
              <span style="font-size:13px">Aktif</span>
            </label>
          </div>
        </div>
        <p style="font-size:12px;color:var(--text3)">
          Kurulum: HilltopAds → Zones → Add Zone → <strong>In-Stream (VAST)</strong> → Tag URL kopyala → yukarıya yapıştır
        </p>
      </div>
    </div>

    <!-- Manuel Reklam Bilgi -->
    <div id="manual-settings" style="<?= $ad_mode==='manual'?'':'display:none' ?>">
      <div style="padding:12px 14px;border-radius:10px;background:var(--bg3);font-size:13px;color:var(--text2)">
        ℹ️ Manuel modda aşağıdaki <strong>Reklam Listesi</strong>'nden aktif olan reklam gösterilir. Birden fazla aktif reklam varsa rastgele seçilir.
      </div>
    </div>

    <div style="margin-top:14px">
      <button type="submit" class="btn" style="border-radius:8px">💾 Ayarları Kaydet</button>
    </div>
  </form>
</div>

<!-- ══ REKLAM LİSTESİ ══ -->
<div style="display:grid;grid-template-columns:360px 1fr;gap:16px">

  <!-- Form -->
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:14px"><?= $edit?'✏️ Reklamı Düzenle':'+ Yeni Reklam' ?></h3>

    <!-- Tip sekmeleri -->
    <div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:14px">
      <button type="button" class="type-tab <?= ($edit['type']??'image')==='image'?'on':'' ?>" onclick="showType('image')">🖼️ Görsel</button>
      <button type="button" class="type-tab <?= ($edit['type']??'')==='video'?'on':'' ?>" onclick="showType('video')">🎬 MP4 Video</button>
      <button type="button" class="type-tab <?= ($edit['type']??'')==='vast'?'on':'' ?>" onclick="showType('vast')">📡 VAST</button>
    </div>

    <form method="POST" style="display:flex;flex-direction:column;gap:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <?php if($edit):?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif?>
      <input type="hidden" name="type" id="ad-type-inp" value="<?= e($edit['type']??'image') ?>">

      <div>
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Reklam Adı *</label>
        <input type="text" name="name" required value="<?= e($edit['name']??'') ?>" class="ns-input" style="border-radius:8px;font-size:13px" placeholder="Banner #1">
      </div>

      <!-- Görsel -->
      <div id="f-image" style="<?= ($edit['type']??'image')!=='image'?'display:none':'' ?>">
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Görsel URL</label>
        <input type="url" name="image_url" value="<?= e($edit['image_url']??'') ?>" class="ns-input" style="border-radius:8px;font-size:13px" placeholder="https://...">
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px;margin-top:8px">Tıklama URL</label>
        <input type="url" name="link" value="<?= e($edit['link']??'') ?>" class="ns-input" style="border-radius:8px;font-size:13px" placeholder="https://...">
      </div>

      <!-- MP4 Video -->
      <div id="f-video" style="<?= ($edit['type']??'')==='video'?'':'display:none' ?>">
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">MP4 Video URL</label>
        <input type="url" name="video_url" value="<?= e($edit['video_url']??'') ?>" class="ns-input" style="border-radius:8px;font-size:13px" placeholder="https://.../reklam.mp4">
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px;margin-top:8px">Tıklama URL</label>
        <input type="url" name="link" value="<?= e($edit['link']??'') ?>" class="ns-input" style="border-radius:8px;font-size:13px" placeholder="https://...">
      </div>

      <!-- VAST -->
      <div id="f-vast" style="<?= ($edit['type']??'')==='vast'?'':'display:none' ?>">
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">VAST Tag URL</label>
        <textarea name="vast_code" rows="3" class="ns-input" style="border-radius:8px;font-size:11px;font-family:monospace;resize:vertical" placeholder="https://..."><?= e($edit['vast_code']??'') ?></textarea>
      </div>

      <!-- Ortak alanlar -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div>
          <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Süre (sn)</label>
          <input type="number" name="duration" min="3" max="120" value="<?= $edit['duration']??15 ?>" class="ns-input" style="border-radius:8px">
        </div>
        <div>
          <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Skip Sonrası (sn)</label>
          <input type="number" name="skip_after" min="0" max="60" value="<?= $edit['skip_after']??5 ?>" class="ns-input" style="border-radius:8px">
        </div>
      </div>
      <div>
        <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Hedef Kitle</label>
        <select name="target" class="ns-input" style="border-radius:8px">
          <option value="all"         <?= ($edit['target_membership']??'all')==='all'?'selected':'' ?>>Herkes</option>
          <option value="free"        <?= ($edit['target_membership']??'')==='free'?'selected':'' ?>>Sadece Ücretsiz</option>
          <option value="non_premium" <?= ($edit['target_membership']??'')==='non_premium'?'selected':'' ?>>Premium Hariç</option>
        </select>
      </div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="active" <?= ($edit['active']??1)?'checked':'' ?> style="accent-color:var(--acc)">
        <span style="font-size:13px">Aktif (gösterilsin)</span>
      </label>

      <div style="display:flex;gap:8px;margin-top:4px">
        <button type="submit" class="btn sm" style="border-radius:8px">💾 Kaydet</button>
        <?php if($edit):?><a href="/admin/ads.php" class="btn sm ghost" style="border-radius:8px">İptal</a><?php endif?>
      </div>
    </form>
  </div>

  <!-- Liste -->
  <div class="ns-card" style="overflow:auto">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <p style="font-size:14px;font-weight:600">Reklam Listesi</p>
      <span style="font-size:12px;padding:3px 10px;border-radius:99px;font-weight:600;<?= $ad_mode==='hilltopads'?'background:rgba(59,130,246,.15);color:#60a5fa':'background:rgba(34,197,94,.15);color:#4ade80' ?>">
        Aktif Mod: <?= $ad_mode==='hilltopads'?'HilltopAds VAST':'Manuel Reklam' ?>
      </span>
    </div>
    <table class="ns-table">
      <thead><tr><th>Ad</th><th>Tür</th><th>Süre/Skip</th><th>Hedef</th><th>Durum</th><th>İşlem</th></tr></thead>
      <tbody>
        <?php foreach($ads as $ad): ?>
        <tr>
          <td>
            <p style="font-size:13px;font-weight:500"><?= e($ad['name']) ?></p>
            <?php
            $preview = $ad['vast_code'] ?: $ad['video_url'] ?: $ad['image_url'] ?? '';
            if($preview): ?>
            <p style="font-size:10px;color:var(--text3);font-family:monospace;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($preview) ?></p>
            <?php endif?>
          </td>
          <td>
            <span class="sb <?= $ad['type']==='vast'?'sb-blue':($ad['type']==='video'?'sb-green':'sb-gray') ?>">
              <?= match($ad['type']??'image'){'vast'=>'📡 VAST','video'=>'🎬 Video',default=>'🖼️ Görsel'} ?>
            </span>
          </td>
          <td style="font-size:12px;color:var(--text2)"><?= $ad['duration'] ?>s / <?= $ad['skip_after']??5 ?>s</td>
          <td><span class="sb"><?= e($ad['target_membership']??'all') ?></span></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $ad['id'] ?>">
              <button class="sb <?= $ad['active']?'sb-green':'sb-gray' ?>" style="border:none;cursor:pointer;border-radius:4px;padding:2px 8px"><?= $ad['active']?'Aktif':'Pasif' ?></button>
            </form>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="?edit=<?= $ad['id'] ?>" class="btn sm ghost" style="border-radius:6px">✏️</a>
              <form method="POST" onsubmit="return confirm('Sil?')" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $ad['id'] ?>">
                <button class="btn sm" style="background:#dc2626;border-radius:6px">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach?>
        <?php if(!$ads):?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3)">Henüz reklam yok. Sol taraftan ekleyin.</td></tr>
        <?php endif?>
      </tbody>
    </table>
  </div>
</div>

<script>
function setMode(m){
  document.getElementById('ad-mode-inp').value=m;
  document.querySelectorAll('.ad-mode-btn').forEach(function(b){b.classList.remove('on');});
  event.currentTarget.classList.add('on');
  document.getElementById('hilltop-settings').style.display=m==='hilltopads'?'block':'none';
  document.getElementById('manual-settings').style.display=m==='manual'?'block':'none';
}
function showType(t){
  document.getElementById('ad-type-inp').value=t;
  ['image','video','vast'].forEach(function(s){
    document.getElementById('f-'+s).style.display=s===t?'block':'none';
  });
  document.querySelectorAll('.type-tab').forEach(function(b){b.classList.remove('on');});
  event.currentTarget.classList.add('on');
}
<?php if($edit): ?>showType('<?= e($edit['type']??'image') ?>');<?php endif?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
