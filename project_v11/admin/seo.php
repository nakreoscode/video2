<?php
$page_title='SEO Ayarları';
require_once __DIR__.'/includes/header.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    foreach(['site_title','site_description','site_keywords','site_logo','site_favicon'] as $k)
        if(isset($_POST[$k]))set_setting($k,trim($_POST[$k]));
    set_flash('success','SEO ayarları kaydedildi.');redirect('/admin/seo.php');
}
function s($k,$d=''){return htmlspecialchars(get_setting($k,$d));}
?>
<div style="max-width:600px">
  <form method="POST" class="ns-card" style="padding:20px;display:flex;flex-direction:column;gap:14px">
    <?=csrf_field()?>
    <h3 style="font-size:14px;font-weight:600">🔍 SEO Ayarları</h3>
    <?php foreach([['site_title','Site Başlığı','NakreosStream'],['site_logo','Logo URL',''],['site_favicon','Favicon URL','']] as [$k,$l,$ph]):?>
    <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px"><?=$l?></label>
    <input type="text" name="<?=$k?>" value="<?=s($k)?>" placeholder="<?=$ph?>" class="ns-input" style="border-radius:8px;font-size:13px;padding:9px 12px"></div>
    <?php endforeach?>
    <?php foreach([['site_description','Meta Açıklama'],['site_keywords','Meta Anahtar Kelimeler']] as [$k,$l]):?>
    <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:5px"><?=$l?></label>
    <textarea name="<?=$k?>" rows="2" class="ns-input" style="border-radius:8px;font-size:13px;padding:9px 12px;resize:none"><?=s($k)?></textarea></div>
    <?php endforeach?>
    <button type="submit" class="btn" style="border-radius:8px;align-self:flex-start">💾 Kaydet</button>
  </form>
</div>
<?php require_once __DIR__.'/includes/footer.php';?>
