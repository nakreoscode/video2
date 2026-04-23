<?php
$page_title = 'Depolama Ayarları';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {

    if (isset($_POST['test_storage'])) {
        $provider = $_POST['test_storage'];
        $allkeys = ['wasabi_key','wasabi_secret','wasabi_bucket','wasabi_region','wasabi_endpoint',
                    'idrive_key','idrive_secret','idrive_bucket','idrive_region','idrive_endpoint'];
        foreach ($allkeys as $k) {
            if (isset($_POST[$k])) set_setting($k, trim($_POST[$k]));
        }
        set_setting('storage_type', $provider);

        require_once dirname(__DIR__) . '/includes/storage.php';
        $tmp = tempnam(sys_get_temp_dir(), 'nstest');
        file_put_contents($tmp, 'NakreosStream test ' . date('Y-m-d H:i:s'));
        $st  = new Storage();
        $res = $st->upload($tmp, 'test/nstest_' . time() . '.txt', 'text/plain');
        @unlink($tmp);
        if ($res['success']) {
            set_flash('success', '✅ ' . ucfirst($provider) . ' bağlantısı başarılı! URL: ' . $res['url']);
        } else {
            set_flash('error', '❌ Bağlantı başarısız. Bilgileri kontrol edin.');
        }
        redirect('/admin/storage-settings.php');
    }

    $keys = ['storage_type','max_upload_size',
             'wasabi_key','wasabi_secret','wasabi_bucket','wasabi_region','wasabi_endpoint',
             'idrive_key','idrive_secret','idrive_bucket','idrive_region','idrive_endpoint'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) set_setting($k, trim($_POST[$k]));
    }
    set_flash('success', 'Depolama ayarları kaydedildi.');
    redirect('/admin/storage-settings.php');
}

function sg($k, $d = '') { return htmlspecialchars(get_setting($k, $d)); }
$cur = get_setting('storage_type', 'local');

$wasabi_fields = [
    ['wasabi_key',      'Access Key ID',   ''],
    ['wasabi_secret',   'Secret Access Key',''],
    ['wasabi_bucket',   'Bucket Adı',       ''],
    ['wasabi_region',   'Region',           'eu-central-1'],
    ['wasabi_endpoint', 'Endpoint',         'https://s3.eu-central-1.wasabisys.com'],
];
$idrive_fields = [
    ['idrive_key',      'Access Key ID',   ''],
    ['idrive_secret',   'Secret Access Key',''],
    ['idrive_bucket',   'Bucket Adı',       ''],
    ['idrive_region',   'Region',           'us-east-1'],
    ['idrive_endpoint', 'Endpoint',         'https://s3.idrivecloud.io'],
];
?>
<style>
.sc{border-radius:12px;border:2px solid var(--border);padding:20px;margin-bottom:14px;transition:.2s;background:var(--bg2);cursor:pointer}
.sc.on{border-color:var(--acc);background:rgba(255,0,0,.04)}
.sc-head{display:flex;align-items:center;gap:12px;margin-bottom:0}
.sc-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.sc-fields{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding-top:14px;margin-top:14px;border-top:1px solid var(--border)}
.sc-lbl{display:block;font-size:11px;color:var(--text2);margin-bottom:4px}
</style>

<form method="POST" id="sf">
  <?= csrf_field() ?>
  <input type="hidden" name="storage_type" id="stor-type" value="<?= e($cur) ?>">
  <div style="max-width:700px">

    <!-- Yerel -->
    <div class="sc <?= $cur==='local'?'on':'' ?>" onclick="sel('local')">
      <div class="sc-head">
        <div class="sc-icon" style="background:#22c55e20">💾</div>
        <div style="flex:1">
          <p style="font-size:14px;font-weight:600">Yerel Sunucu</p>
          <p style="font-size:12px;color:var(--text2)">Videolar /assets/videos/ klasörüne kaydedilir</p>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <span style="font-size:11px;padding:2px 8px;border-radius:99px;background:#22c55e20;color:#22c55e;font-weight:600">Ücretsiz</span>
          <?php if($cur==='local'):?><span style="font-size:11px;padding:2px 8px;border-radius:99px;background:var(--acc);color:#fff;font-weight:600">✓ Aktif</span><?php endif?>
        </div>
      </div>
      <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)" onclick="event.stopPropagation()">
        <label class="sc-lbl">Maksimum Dosya Boyutu (MB)</label>
        <input type="number" name="max_upload_size" value="<?= sg('max_upload_size','500') ?>" min="10" max="10000" class="ns-input" style="border-radius:8px;max-width:200px">
      </div>
    </div>

    <!-- Wasabi -->
    <div class="sc <?= $cur==='wasabi'?'on':'' ?>" onclick="sel('wasabi')">
      <div class="sc-head">
        <div class="sc-icon" style="background:#ff6b0020">🪣</div>
        <div style="flex:1">
          <p style="font-size:14px;font-weight:600">Wasabi S3</p>
          <p style="font-size:12px;color:var(--text2)">S3 uyumlu bulut depolama</p>
        </div>
        <?php if($cur==='wasabi'):?><span style="font-size:11px;padding:2px 8px;border-radius:99px;background:var(--acc);color:#fff;font-weight:600">✓ Aktif</span><?php endif?>
      </div>
      <div class="sc-fields" onclick="event.stopPropagation()">
        <?php foreach($wasabi_fields as $row):
          list($k,$l,$ph) = $row; ?>
        <div>
          <label class="sc-lbl"><?= $l ?></label>
          <input type="<?= str_contains($k,'secret')?'password':'text' ?>" name="<?= $k ?>" value="<?= sg($k,$ph) ?>" placeholder="<?= $ph ?>" class="ns-input" style="border-radius:7px;font-size:12px;font-family:monospace">
        </div>
        <?php endforeach?>
        <div style="grid-column:span 2;display:flex;gap:8px;margin-top:4px">
          <button type="button" onclick="test('wasabi')" class="btn sm" style="border-radius:7px;background:#16a34a">🔌 Test Et</button>
          <a href="https://console.wasabisys.com" target="_blank" class="btn sm ghost" style="border-radius:7px" onclick="event.stopPropagation()">Wasabi Panel →</a>
        </div>
      </div>
    </div>

    <!-- iDrive -->
    <div class="sc <?= $cur==='idrive'?'on':'' ?>" onclick="sel('idrive')">
      <div class="sc-head">
        <div class="sc-icon" style="background:#3b82f620">☁️</div>
        <div style="flex:1">
          <p style="font-size:14px;font-weight:600">iDrive e2</p>
          <p style="font-size:12px;color:var(--text2)">S3 uyumlu bulut depolama</p>
        </div>
        <?php if($cur==='idrive'):?><span style="font-size:11px;padding:2px 8px;border-radius:99px;background:var(--acc);color:#fff;font-weight:600">✓ Aktif</span><?php endif?>
      </div>
      <div class="sc-fields" onclick="event.stopPropagation()">
        <?php foreach($idrive_fields as $row):
          list($k,$l,$ph) = $row; ?>
        <div>
          <label class="sc-lbl"><?= $l ?></label>
          <input type="<?= str_contains($k,'secret')?'password':'text' ?>" name="<?= $k ?>" value="<?= sg($k,$ph) ?>" placeholder="<?= $ph ?>" class="ns-input" style="border-radius:7px;font-size:12px;font-family:monospace">
        </div>
        <?php endforeach?>
        <div style="grid-column:span 2">
          <p style="font-size:12px;color:var(--text3);margin-bottom:8px">💡 iDrive panelinden Buckets → Endpoint adresini kopyalayın</p>
          <div style="display:flex;gap:8px">
            <button type="button" onclick="test('idrive')" class="btn sm" style="border-radius:7px;background:#16a34a">🔌 Test Et</button>
            <a href="https://app.idrivecloud.io" target="_blank" class="btn sm ghost" style="border-radius:7px" onclick="event.stopPropagation()">iDrive Panel →</a>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
      <button type="submit" class="btn" style="border-radius:8px">💾 Kaydet</button>
      <span style="font-size:13px;color:var(--text2)">Aktif:
        <strong><?= match($cur){'wasabi'=>'Wasabi S3','idrive'=>'iDrive e2',default=>'Yerel Sunucu'} ?></strong>
      </span>
    </div>
  </div>
</form>

<script>
function sel(t){
  document.getElementById('stor-type').value=t;
  document.querySelectorAll('.sc').forEach(function(c){c.classList.remove('on');});
  event.currentTarget.classList.add('on');
}
function test(provider){
  var fd=new FormData(document.getElementById('sf'));
  fd.set('test_storage',provider);
  var f=document.createElement('form');
  f.method='POST';f.action='/admin/storage-settings.php';
  fd.forEach(function(v,k){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;f.appendChild(i);});
  document.body.appendChild(f);f.submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
