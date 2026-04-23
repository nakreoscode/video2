<?php
$page_title='Genel Ayarlar';
require_once __DIR__.'/includes/header.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $keys=['site_title','site_description','site_keywords','site_logo','max_upload_size','active_theme','mail_from','paytr_merchant_id','paytr_merchant_key','paytr_merchant_salt','shopier_api_key','shopier_api_secret','bank_iban','bank_name','bank_owner','crypto_address','crypto_network'];
    foreach($keys as $k){if(isset($_POST[$k]))set_setting($k,trim($_POST[$k]));}
    foreach(['show_categories','show_trending','show_popular_widget','registration_open','maintenance_mode','age_warning_enabled'] as $k){set_setting($k,isset($_POST[$k])?'1':'0');}
    $methods=array_intersect($_POST['payment_methods']??[],['paytr','shopier','bank','crypto']);
    set_setting('payment_methods',implode(',',$methods));
    set_flash('success','Ayarlar kaydedildi.');redirect('/admin/settings.php');
}
function s($k,$d=''){return htmlspecialchars(get_setting($k,$d));}
$pm=explode(',',get_setting('payment_methods','bank,crypto'));
?>
<form method="POST" style="display:flex;flex-direction:column;gap:16px;max-width:860px">
  <?=csrf_field()?>

  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">🌐 Site Bilgileri</h3>
    <div class="grid-2">
      <?php foreach([['site_title','Site Adı'],['site_logo','Logo URL'],['mail_from','Gönderen E-posta'],['max_upload_size','Max Upload (MB)']] as [$k,$l]):?>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$k?>" value="<?=s($k)?>" class="ns-input" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
      <?php endforeach?>
    </div>
  </div>

  <div class="ns-card" style="padding:20px;border-left:3px solid #3b82f6">
    <p style="font-size:13px;color:var(--text2)">💙 Üyelik paket fiyatları ve özellikleri <a href="/admin/plans.php" style="color:var(--acc)">Paketler sayfasından</a> yönetilmektedir.</p>
  </div>

  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">🎨 Varsayılan Tema</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php foreach(['dark'=>['🌙','Koyu YouTube','#0f0f0f','#f1f1f1'],'light'=>['☀️','Açık YouTube','#ffffff','#0f0f0f'],'netflix'=>['🎬','Koyu','#141414','#fff'],'twitch'=>['🎮','Twitch','#0e0e10','#efeff1'],'spotify'=>['🎵','Spotify','#121212','#fff'],'cinema'=>['🎥','Cinema (Plex)','#0a0a0f','#fff'],'minimal'=>['✨','Minimal (Modern)','#f5f5f7','#1a1a2e']] as $k=>[$ic,$lb,$bg,$fg]):?>
      <label style="cursor:pointer">
        <input type="radio" name="active_theme" value="<?=$k?>" style="display:none" <?=s('active_theme','dark')===$k?'checked':''?>>
        <div onclick="document.querySelectorAll('[name=active_theme]').forEach(r=>r.parentElement.querySelector('div').style.borderColor='var(--border)');this.style.borderColor='var(--acc)'"
          style="padding:14px 24px;border-radius:10px;border:2px solid <?=s('active_theme','dark')===$k?'var(--acc)':'var(--border)'?>;background:<?=$bg?>;text-align:center;min-width:110px;transition:.15s">
          <div style="font-size:26px;margin-bottom:4px"><?=$ic?></div>
          <div style="font-size:13px;font-weight:600;color:<?=$fg?>"><?=$lb?></div>
        </div>
      </label>
      <?php endforeach?>
    </div>
  </div>

  <!-- Reklam Modu -->
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">📢 Reklam Gösterim Modu</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <?php $adm=s('ad_mode','hilltopads'); ?>
      <label style="cursor:pointer;flex:1;min-width:180px">
        <input type="radio" name="ad_mode" value="hilltopads" <?=$adm==='hilltopads'?'checked':''?> style="display:none">
        <div onclick="this.closest('label').querySelector('input').checked=true;document.querySelectorAll('[name=ad_mode]').forEach(r=>{r.closest('label').querySelector('div').style.borderColor='var(--border)'});this.style.borderColor='var(--acc)'"
          style="padding:14px;border-radius:10px;border:2px solid <?=$adm==='hilltopads'?'var(--acc)':'var(--border)'?>;transition:.15s;text-align:center">
          <div style="font-size:24px;margin-bottom:4px">📡</div>
          <p style="font-size:13px;font-weight:600">HilltopAds VAST</p>
          <p style="font-size:12px;color:var(--text2)">VAST tag URL ile otomatik</p>
        </div>
      </label>
      <label style="cursor:pointer;flex:1;min-width:180px">
        <input type="radio" name="ad_mode" value="manual" <?=$adm==='manual'?'checked':''?> style="display:none">
        <div onclick="this.closest('label').querySelector('input').checked=true;document.querySelectorAll('[name=ad_mode]').forEach(r=>{r.closest('label').querySelector('div').style.borderColor='var(--border)'});this.style.borderColor='var(--acc)'"
          style="padding:14px;border-radius:10px;border:2px solid <?=$adm==='manual'?'var(--acc)':'var(--border)'?>;transition:.15s;text-align:center">
          <div style="font-size:24px;margin-bottom:4px">🖼️</div>
          <p style="font-size:13px;font-weight:600">Manuel Reklam</p>
          <p style="font-size:12px;color:var(--text2)">Görsel, MP4 veya VAST</p>
        </div>
      </label>
    </div>
    <p style="font-size:12px;color:var(--text3);margin-top:8px">
      Detaylı reklam ayarları için: <a href="/admin/ads.php" style="color:var(--acc)">Admin → Reklamlar</a>
    </p>
  </div>

  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">🏠 Ana Sayfa Bölümleri</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">
      <?php foreach([['show_categories','📂 Kategoriler'],['show_trending','🔥 Trend'],['show_popular_widget','🏆 En Çok İzlenenler Widget'],['registration_open','📝 Kayıt Açık'],['maintenance_mode','🔧 Bakım Modu'],['age_warning_enabled','🔞 +18 Uyarı Popup']] as [$k,$l]):?>
      <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:8px;background:var(--bg3);cursor:pointer;font-size:13px">
        <input type="checkbox" name="<?=$k?>" <?=s($k,'1')==='1'?'checked':''?> style="accent-color:var(--acc)"><?=$l?>
      </label>
      <?php endforeach?>
    </div>
  </div>

  

  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:6px">💳 Ödeme Yöntemleri</h3>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
      <?php foreach(['paytr'=>'💳 PayTR','shopier'=>'🛒 Shopier','bank'=>'🏦 Banka','crypto'=>'₿ Kripto'] as $k=>$lb):?>
      <label style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:8px;border:1.5px solid <?=in_array($k,$pm)?'var(--acc)':'var(--border)'?>;cursor:pointer;font-size:13px;font-weight:500;transition:.15s" onclick="this.style.borderColor=this.querySelector('input').checked?'var(--border)':'var(--acc)'">
        <input type="checkbox" name="payment_methods[]" value="<?=$k?>" <?=in_array($k,$pm)?'checked':''?> style="accent-color:var(--acc)"><?=$lb?>
      </label>
      <?php endforeach?>
    </div>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px;font-weight:500;color:var(--text2);padding:6px 0">💳 PayTR API Ayarları</summary>
    <div class="grid-3" style="margin-top:10px">
      <?php foreach([['paytr_merchant_id','Merchant ID'],['paytr_merchant_key','Merchant Key'],['paytr_merchant_salt','Merchant Salt']] as [$k,$l]):?>
      <div><label style="display:block;font-size:11px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$k?>" value="<?=s($k)?>" class="ns-input" style="font-size:12px;padding:7px 10px;border-radius:7px;font-family:monospace"></div>
      <?php endforeach?>
    </div></details>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px;font-weight:500;color:var(--text2);padding:6px 0">🛒 Shopier API Ayarları</summary>
    <div class="grid-2" style="margin-top:10px">
      <?php foreach([['shopier_api_key','API Key'],['shopier_api_secret','API Secret']] as [$k,$l]):?>
      <div><label style="display:block;font-size:11px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$k?>" value="<?=s($k)?>" class="ns-input" style="font-size:12px;padding:7px 10px;border-radius:7px;font-family:monospace"></div>
      <?php endforeach?>
    </div></details>
    <details><summary style="cursor:pointer;font-size:13px;font-weight:500;color:var(--text2);padding:6px 0">🏦 Banka Havalesi</summary>
    <div class="grid-3" style="margin-top:10px">
      <?php foreach([['bank_iban','IBAN'],['bank_name','Banka'],['bank_owner','Hesap Sahibi']] as [$k,$l]):?>
      <div><label style="display:block;font-size:11px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$k?>" value="<?=s($k)?>" class="ns-input" style="font-size:12px;padding:7px 10px;border-radius:7px"></div>
      <?php endforeach?></div></details>
    <details style="margin-top:8px"><summary style="cursor:pointer;font-size:13px;font-weight:500;color:var(--text2);padding:6px 0">₿ Kripto</summary>
    <div class="grid-2" style="margin-top:10px">
      <?php foreach([['crypto_address','Cüzdan Adresi'],['crypto_network','Ağ']] as [$k,$l]):?>
      <div><label style="display:block;font-size:11px;color:var(--text2);margin-bottom:4px"><?=$l?></label><input type="text" name="<?=$k?>" value="<?=s($k)?>" class="ns-input" style="font-size:12px;padding:7px 10px;border-radius:7px;font-family:monospace"></div>
      <?php endforeach?></div></details>
  </div>

  <div><button type="submit" class="btn" style="border-radius:8px;padding:11px 28px">💾 Tüm Ayarları Kaydet</button></div>
</form>
<?php require_once __DIR__.'/includes/footer.php';?>
