<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/storage.php';
start_session();
$user = require_login();
$page_title = 'Video Yükle';

$max_mb     = (int)get_setting('max_upload_size', '500');
$categories = $pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order")->fetchAll();
$errors     = [];

$edit_video = null;
if (!empty($_GET['edit'])) {
    $ev = $pdo->prepare("SELECT * FROM uploaded_videos WHERE id=? AND user_id=? LIMIT 1");
    $ev->execute([(int)$_GET['edit'], $user['id']]); $edit_video = $ev->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Güvenlik hatası.'; }
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && $edit_video) {
        $pdo->prepare("DELETE FROM uploaded_videos WHERE id=? AND user_id=?")->execute([$edit_video['id'], $user['id']]);
        set_flash('success','Video silindi.'); redirect('/dashboard.php?tab=uploads');
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags        = trim($_POST['tags'] ?? '');
        $type        = in_array($_POST['type'] ?? '', ['normal','short']) ? $_POST['type'] : 'normal';
        $cat_id      = (int)($_POST['category'] ?? 0);

        if (!$title) $errors[] = 'Başlık zorunludur.';

        if (!$errors) {
            if ($edit_video) {
                $pdo->prepare("UPDATE uploaded_videos SET title=?,description=?,tags=?,type=?,updated_at=NOW() WHERE id=? AND user_id=?")
                    ->execute([$title,$description,$tags,$type,$edit_video['id'],$user['id']]);
                if ($cat_id) {
                    $pdo->prepare("DELETE FROM video_categories WHERE video_id=?")->execute([$edit_video['id']]);
                    $pdo->prepare("INSERT INTO video_categories(video_id,category_id) VALUES(?,?)")->execute([$edit_video['id'],$cat_id]);
                }
                set_flash('success','Video güncellendi.'); redirect('/dashboard.php?tab=uploads');
            } else {
                if (empty($_FILES['video']['tmp_name'])) { $errors[] = 'Video dosyası seçin.'; }
                else {
                    $file    = $_FILES['video'];
                    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $size_mb = $file['size'] / 1048576;

                    if (!in_array($ext, ['mp4','webm','mov'])) { $errors[] = 'Desteklenen formatlar: mp4, webm, mov'; }
                    elseif ($size_mb > $max_mb) { $errors[] = "Maksimum dosya boyutu: {$max_mb}MB"; }
                    else {
                        $thumb_url = '';
                        if (!empty($_FILES['thumbnail']['tmp_name'])) {
                            $tdir = __DIR__ . '/assets/img/thumbs/';
                            if (!is_dir($tdir)) mkdir($tdir, 0755, true);
                            $tname = 'thumb_' . time() . '_' . rand(1000,9999) . '.jpg';
                            move_uploaded_file($_FILES['thumbnail']['tmp_name'], $tdir . $tname);
                            $thumb_url = '/assets/img/thumbs/' . $tname;
                        }

                        // Madde 10: Free üye = pending, premium/ultimate = active
                        $upload_status = in_array($user['membership']??'free',['premium','ultimate']) ? 'active' : 'pending';
                        $pdo->prepare("INSERT INTO uploaded_videos(user_id,title,description,tags,type,thumbnail,format,file_size,status) VALUES(?,?,?,?,?,?,?,?,?)")
                            ->execute([$user['id'],$title,$description,$tags,$type,$thumb_url,$ext,(int)$file['size'],$upload_status]);
                        $new_id = (int)$pdo->lastInsertId();

                        if ($cat_id) {
                            $pdo->prepare("INSERT INTO video_categories(video_id,category_id) VALUES(?,?)")->execute([$new_id,$cat_id]);
                        }

                        $remote_key = "videos/{$user['id']}/{$new_id}.{$ext}";
                        $storage_mgr = new Storage();
                        $res = $storage_mgr->upload($file['tmp_name'], $remote_key, 'video/' . $ext);
                        $storage_type = $res['storage'] ?? get_setting('storage_type','local');
                        if ($res['success']) {
                            // Yükleme başarılı - upload_status'a göre durum belirle
                            $final_status = $upload_status === 'active' ? 'active' : 'pending';
                            $pdo->prepare("UPDATE uploaded_videos SET file_path=?,storage=?,status=? WHERE id=?")
                                ->execute([$res['url'], $storage_type, $final_status, $new_id]);
                        } else {
                            // Storage hatası - yerel kayda düş
                            $local_dir = __DIR__ . '/assets/videos/';
                            if (!is_dir($local_dir)) mkdir($local_dir, 0755, true);
                            $local_file = $local_dir . $new_id . '.' . $ext;
                            if (move_uploaded_file($file['tmp_name'], $local_file) || copy($file['tmp_name'], $local_file)) {
                                $final_status = $upload_status === 'active' ? 'active' : 'pending';
                                $pdo->prepare("UPDATE uploaded_videos SET file_path=?,storage='local',status=? WHERE id=?")
                                    ->execute(['/assets/videos/' . $new_id . '.' . $ext, $final_status, $new_id]);
                            }
                        }

                        log_activity('upload_video', "Video yüklendi: {$title}", $user['id']);
                        if($upload_status==='pending'){
                            set_flash('success', 'Video yüklendi! Admin onayı bekleniyor.');
                        } else {
                            set_flash('success', 'Video başarıyla yüklendi!');
                        }
                        redirect('/dashboard.php?tab=uploads');
                    }
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="pg" style="max-width:680px;margin:0 auto">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <h1 style="font-size:22px;font-weight:700;color:var(--text)">
      📤 <?= $edit_video ? 'Video Düzenle' : 'Video Yükle' ?>
    </h1>
    <?php
    $st = get_setting('storage_type','local');
    $st_info = match($st){
      'wasabi' => ['🪣','Wasabi S3','#ff6b00'],
      'idrive' => ['☁️','iDrive e2','#3b82f6'],
      default  => ['💾','Yerel Sunucu','#22c55e']
    };
    ?>
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;padding:5px 12px;border-radius:8px;background:var(--bg3);color:var(--text2)">
      <span><?=$st_info[0]?></span>
      <span><?=$st_info[1]?></span>
      <span style="width:6px;height:6px;border-radius:50%;background:<?=$st_info[2]?>;margin-left:2px"></span>
    </div>
  </div>

  <?php if ($errors): ?>
  <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#f87171;border-radius:12px;padding:14px 16px;margin-bottom:16px;font-size:13px">
    <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach ?>
  </div>
  <?php endif ?>

  <div class="nscard" style="padding:24px">
    <form method="POST" enctype="multipart/form-data" id="upload-form">
      <?= csrf_field() ?>

      <!-- Video Tipi -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:10px">Video Tipi</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <?php foreach (['normal'=>['📺','Normal Video','16:9'],'short'=>['📱','Short','9:16']] as $k=>[$icon,$label,$ratio]): ?>
          <label style="cursor:pointer">
            <input type="radio" name="type" value="<?= $k ?>" style="display:none"
              <?= ($edit_video ? $edit_video['type'] : 'normal')===$k ? 'checked' : '' ?>
              onchange="selectType(this.value)">
            <div class="type-card" data-type="<?= $k ?>" onclick="selectType('<?= $k ?>')" style="cursor:pointer;border:2px solid <?= ($edit_video ? $edit_video['type'] : 'normal')===$k ? 'var(--acc)' : 'var(--border)' ?>;border-radius:12px;padding:16px;text-align:center;transition:.2s;background:<?= ($edit_video ? $edit_video['type'] : 'normal')===$k ? 'rgba(255,0,0,.08)' : 'transparent' ?>">
              <div style="font-size:28px;margin-bottom:6px"><?= $icon ?></div>
              <p style="font-size:14px;font-weight:600;color:var(--text)"><?= $label ?></p>
              <p style="font-size:12px;color:var(--text3);margin-top:2px"><?= $ratio ?></p>
            </div>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Video Dosyası -->
      <?php if (!$edit_video): ?>
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:8px">
          Video Dosyası * <span style="color:var(--text3)">(max <?= $max_mb ?>MB)</span>
        </label>
        <div id="drop-zone" style="border:2px dashed var(--border);border-radius:12px;padding:32px 20px;text-align:center;cursor:pointer;transition:.2s;background:var(--bg3)">
          <div id="dz-default">
            <div style="font-size:36px;margin-bottom:8px">📁</div>
            <p style="font-size:14px;color:var(--text2);margin-bottom:4px">Dosyayı sürükle bırak veya tıkla</p>
            <p style="font-size:12px;color:var(--text3)">MP4, WebM, MOV · max <?= $max_mb ?>MB</p>
          </div>
          <div id="dz-selected" style="display:none">
            <div style="font-size:32px;margin-bottom:8px">✅</div>
            <p id="file-name" style="font-size:14px;font-weight:500;color:#4ade80"></p>
            <p id="file-size" style="font-size:12px;color:var(--text3);margin-top:4px"></p>
          </div>
          <input type="file" name="video" id="video-input" accept=".mp4,.webm,.mov" required style="display:none">
        </div>
        <div id="progress-wrap" style="display:none;margin-top:10px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text2);margin-bottom:4px">
            <span>Yükleniyor...</span><span id="progress-pct">0%</span>
          </div>
          <div style="background:var(--bg3);border-radius:99px;height:4px">
            <div id="progress-bar" style="height:100%;background:var(--acc);border-radius:99px;width:0%;transition:width .3s"></div>
          </div>
        </div>
      </div>
      <?php endif ?>

      <!-- Başlık -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px">Başlık *</label>
        <input type="text" name="title" required maxlength="255"
          value="<?= e($edit_video['title'] ?? '') ?>"
          class="nsinput" style="border-radius:8px"
          placeholder="Video başlığı...">
      </div>

      <!-- Açıklama -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px">Açıklama</label>
        <textarea name="description" rows="3" maxlength="5000"
          class="nsinput" style="border-radius:8px;resize:vertical"
          placeholder="Video hakkında..."><?= e($edit_video['description'] ?? '') ?></textarea>
      </div>

      <!-- Thumbnail -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px">Küçük Resim</label>
        <div style="display:flex;align-items:center;gap:12px">
          <div id="thumb-preview" style="width:120px;height:68px;border-radius:8px;background:var(--bg3);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text3)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <input type="file" name="thumbnail" id="thumb-input" accept="image/*" style="display:none" onchange="thumbPreview(this)">
            <button type="button" onclick="document.getElementById('thumb-input').click()" class="nsbtn ghost sm" style="border-radius:20px">Görsel Seç</button>
            <p style="font-size:11px;color:var(--text3);margin-top:5px">JPG, PNG, WebP · 1280×720 önerilen</p>
          </div>
        </div>
      </div>

      <!-- Kategori -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px">Kategori</label>
        <select name="category" class="nsinput" style="border-radius:8px">
          <option value="">-- Kategori Seç --</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= e($cat['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <!-- Etiketler -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px">Etiketler</label>
        <input type="text" name="tags"
          value="<?= e($edit_video['tags'] ?? '') ?>" maxlength="500"
          class="nsinput" style="border-radius:8px"
          placeholder="müzik, cover, 2024 (virgülle ayır)">
      </div>

      <!-- Butonlar -->
      <div style="display:flex;gap:10px">
        <button type="submit" id="submit-btn" class="nsbtn" style="flex:1;justify-content:center;border-radius:20px;padding:12px">
          <?= $edit_video ? '💾 Güncelle' : '📤 Yükle' ?>
        </button>
        <a href="/dashboard.php?tab=uploads" class="nsbtn ghost" style="border-radius:20px;padding:12px 20px">İptal</a>
        <?php if ($edit_video): ?>
        <button type="submit" name="action" value="delete"
          onclick="return confirm('Videoyu silmek istiyor musunuz?')"
          class="nsbtn" style="border-radius:20px;padding:12px 16px;background:#dc2626">🗑</button>
        <?php endif ?>
      </div>

    </form>
  </div>
</div>

<script>
function selectType(t) {
  document.querySelectorAll('input[name="type"]').forEach(function(r) {
    r.checked = (r.value === t);
  });
  document.querySelectorAll('.type-card').forEach(function(card) {
    var on = card.dataset.type === t;
    card.style.borderColor = on ? 'var(--acc)' : 'var(--border)';
    card.style.background  = on ? 'rgba(255,0,0,.08)' : 'transparent';
  });
}
function updateTypeUI() {
  var checked = document.querySelector('input[name="type"]:checked');
  if (checked) selectType(checked.value);
}

// Drop zone
var dz = document.getElementById('drop-zone');
var vi = document.getElementById('video-input');
if (dz && vi) {
  dz.addEventListener('click', function() { vi.click(); });
  dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.style.borderColor='var(--acc)'; dz.style.background='rgba(255,0,0,.05)'; });
  dz.addEventListener('dragleave', function() { dz.style.borderColor='var(--border)'; dz.style.background='var(--bg3)'; });
  dz.addEventListener('drop', function(e) {
    e.preventDefault(); dz.style.borderColor='var(--border)'; dz.style.background='var(--bg3)';
    var f = e.dataTransfer.files[0];
    if (f && f.type.startsWith('video/')) {
      var dt = new DataTransfer(); dt.items.add(f); vi.files = dt.files; showFile(f);
    }
  });
  vi.addEventListener('change', function(e) { if (e.target.files[0]) showFile(e.target.files[0]); });
}

function showFile(f) {
  document.getElementById('dz-default').style.display = 'none';
  document.getElementById('dz-selected').style.display = 'block';
  document.getElementById('file-name').textContent = f.name;
  document.getElementById('file-size').textContent = (f.size/1024/1024).toFixed(1) + ' MB';
  // 60sn altı ise short seç
  var v = document.createElement('video'); v.src = URL.createObjectURL(f);
  v.onloadedmetadata = function() {
    if (v.duration <= 60) {
      document.querySelector('input[name="type"][value="short"]').checked = true;
      updateTypeUI();
    }
    URL.revokeObjectURL(v.src);
  };
}

// Küçük resim önizleme
function thumbPreview(input) {
  if (!input.files[0]) return;
  var r = new FileReader();
  r.onload = function(e) {
    document.getElementById('thumb-preview').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover">';
  };
  r.readAsDataURL(input.files[0]);
}

// XHR ile yükleme + progress
document.getElementById('upload-form').addEventListener('submit', function(e) {
  if (!vi || !vi.files || !vi.files.length) return;
  e.preventDefault();
  var btn = document.getElementById('submit-btn');
  btn.disabled = true; btn.textContent = '⏳ Yükleniyor...';
  document.getElementById('progress-wrap').style.display = 'block';
  var xhr = new XMLHttpRequest();
  xhr.open('POST', location.href);
  xhr.upload.onprogress = function(ev) {
    var pct = Math.round(ev.loaded / ev.total * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-pct').textContent = pct + '%';
  };
  xhr.onload = function() {
    document.open(); document.write(xhr.responseText); document.close();
  };
  xhr.send(new FormData(document.getElementById('upload-form')));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
