<?php
$page_title='SMS Ayarları';
require_once __DIR__.'/includes/header.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    set_setting('sms_active',isset($_POST['sms_active'])?'1':'0');
    set_setting('sms_login_required',isset($_POST['sms_login_required'])?'1':'0');
    foreach(['sms_username','sms_password','sms_sender'] as $k)
        if(isset($_POST[$k]))set_setting($k,trim($_POST[$k]));
    set_flash('success','SMS ayarları kaydedildi.');redirect('/admin/sms-settings.php');
}
function s($k,$d=''){return htmlspecialchars(get_setting($k,$d));}
?>
<div style="max-width:560px;display:flex;flex-direction:column;gap:16px">
  <form method="POST" class="ns-card" style="padding:20px;display:flex;flex-direction:column;gap:0">
    <?=csrf_field()?>
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">📱 İletimerkezi SMS</h3>
    
    <!-- SMS Aktif -->
    <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);cursor:pointer">
      <div><p style="font-size:14px;font-weight:500">SMS Servisi</p><p style="font-size:12px;color:var(--text2)">SMS gönderimi aktif/pasif yap</p></div>
      <input type="checkbox" name="sms_active" id="sms_active" <?=s('sms_active')==='1'?'checked':''?> onchange="updateTog(this,'tog1')" style="display:none">
      <div id="tog1" onclick="document.getElementById('sms_active').click()" style="width:44px;height:24px;border-radius:99px;background:<?=s('sms_active')==='1'?'var(--acc)':'var(--border)'?>;cursor:pointer;position:relative;transition:.2s">
        <div style="position:absolute;top:2px;<?=s('sms_active')==='1'?'left:22':'left:2'?>px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.2s"></div>
      </div>
    </label>

    <!-- Zorunlu Giriş -->
    <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);cursor:pointer">
      <div><p style="font-size:14px;font-weight:500">SMS Girişi Zorunlu</p><p style="font-size:12px;color:var(--text2)">Tüm kullanıcılar için SMS doğrulama zorunlu</p></div>
      <input type="checkbox" name="sms_login_required" id="sms_req" <?=s('sms_login_required')==='1'?'checked':''?> onchange="updateTog(this,'tog2')" style="display:none">
      <div id="tog2" onclick="document.getElementById('sms_req').click()" style="width:44px;height:24px;border-radius:99px;background:<?=s('sms_login_required')==='1'?'var(--acc)':'var(--border)'?>;cursor:pointer;position:relative;transition:.2s">
        <div style="position:absolute;top:2px;<?=s('sms_login_required')==='1'?'left:22':'left:2'?>px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.2s"></div>
      </div>
    </label>

    <div style="padding-top:14px;display:flex;flex-direction:column;gap:12px">
      <?php foreach([['sms_username','Kullanıcı Adı','text'],['sms_password','Şifre','password'],['sms_sender','Gönderici Adı (max 11 kar.)','text']] as [$k,$l,$t]):?>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px"><?=$l?></label>
      <input type="<?=$t?>" name="<?=$k?>" value="<?=$t==='password'?'':s($k)?>" class="ns-input" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
      <?php endforeach?>
      <p style="font-size:12px;color:var(--text3)">Hesap için: <a href="https://iletimerkezi.com" target="_blank" style="color:var(--acc)">iletimerkezi.com</a></p>
      <button type="submit" class="btn" style="border-radius:8px;align-self:flex-start">💾 Kaydet</button>
    </div>
  </form>

  <!-- Test SMS -->
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">🧪 Test SMS Gönder</h3>
    <div style="display:flex;gap:8px">
      <input type="tel" id="test-ph" placeholder="05xx xxx xx xx" class="ns-input" style="border-radius:8px;font-size:13px;padding:9px 12px">
      <button onclick="testSMS()" class="btn sm" style="border-radius:8px;white-space:nowrap">Gönder</button>
    </div>
    <p id="test-res" style="font-size:12px;margin-top:8px;color:var(--text2)"></p>
  </div>
</div>
<script>
function updateTog(cb,id){
  const t=document.getElementById(id),th=t.querySelector('div');
  t.style.background=cb.checked?'var(--acc)':'var(--border)';
  if(th)th.style.left=cb.checked?'22px':'2px';
}
function testSMS(){
  const ph=document.getElementById('test-ph').value;
  const res=document.getElementById('test-res');
  if(!ph){res.textContent='Telefon girin';return;}
  res.textContent='Gönderiliyor...';
  fetch('/ajax/send_test_sms.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({phone:ph,csrf_token:'<?=csrf_token()?>'})})
  .then(r=>r.json()).then(d=>{res.textContent=d.success?'✅ Gönderildi!':'❌ '+( d.error||'Hata');res.style.color=d.success?'#4ade80':'#f87171'});
}
</script>
<?php require_once __DIR__.'/includes/footer.php';?>
