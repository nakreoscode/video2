<?php
$page_title='Üyelik Paketleri';
require_once __DIR__.'/includes/header.php';

if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    foreach(['free','premium','ultimate'] as $slug){
        if(!isset($_POST['name_'.$slug])) continue;
        $name=trim($_POST['name_'.$slug]);
        $price=(float)($_POST['price_'.$slug]??0);
        $color=trim($_POST['color_'.$slug]??'#666');
        $icon=trim($_POST['icon_'.$slug]??'⚪');
        $features=trim($_POST['features_'.$slug]??'[]');
        $ads_free=isset($_POST['ads_free_'.$slug])?1:0;
        $api_access=isset($_POST['api_access_'.$slug])?1:0;
        $download=isset($_POST['download_'.$slug])?1:0;
        $publish_ads=isset($_POST['publish_ads_'.$slug])?1:0;
        $max_upload=(int)($_POST['max_upload_'.$slug]??0);
        $max_size=(int)($_POST['max_size_'.$slug]??100);
        // JSON doğrula
        $feat_arr=[];
        foreach(explode("\n",$features) as $line){$line=trim($line);if($line)$feat_arr[]=$line;}
        $pdo->prepare("UPDATE membership_plans SET name=?,price=?,color=?,icon=?,features=?,ads_free=?,api_access=?,download_videos=?,publish_ads=?,max_upload_count=?,max_upload_size_mb=? WHERE slug=?")
            ->execute([$name,$price,$color,$icon,json_encode($feat_arr,JSON_UNESCAPED_UNICODE),$ads_free,$api_access,$download,$publish_ads,$max_upload,$max_size,$slug]);
        // settings tablosuna da yaz
        if($slug!=='free') set_setting($slug.'_price',(string)$price);
    }
    set_flash('success','Paketler güncellendi.');redirect('/admin/plans.php');
}

$plans=[];
foreach(['free','premium','ultimate'] as $s){
    $plans[$s]=get_plan($s);
}
$plan_colors=['free'=>'#666','premium'=>'#1a73e8','ultimate'=>'#7c3aed'];
$plan_icons=['free'=>'⚪','premium'=>'💙','ultimate'=>'💜'];
?>
<form method="POST">
<?=csrf_field()?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:20px">
<?php foreach($plans as $slug=>$plan):
  $feats=implode("\n",$plan['features_arr']);
?>
<div class="ns-card" style="padding:20px;border-top:3px solid <?=e($plan['color'])?>">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <span style="font-size:28px"><?=e($plan['icon']??$plan_icons[$slug])?></span>
    <div>
      <p style="font-size:14px;font-weight:600"><?=e($plan['name'])?></p>
      <p style="font-size:12px;color:var(--text2)"><?=$slug==='free'?'Ücretsiz':'Ücretli'?> paket</p>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:10px">
    <div style="display:grid;grid-template-columns:1fr 50px;gap:8px">
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Paket Adı</label>
      <input type="text" name="name_<?=$slug?>" value="<?=e($plan['name'])?>" class="ns-input" style="border-radius:7px;font-size:13px"></div>
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">İkon</label>
      <input type="text" name="icon_<?=$slug?>" value="<?=e($plan['icon']??$plan_icons[$slug])?>" class="ns-input" style="border-radius:7px;font-size:18px;text-align:center;padding:6px"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Fiyat (₺/ay)</label>
      <input type="number" step="0.01" name="price_<?=$slug?>" value="<?=$plan['price']?>" <?=$slug==='free'?'disabled':''?> class="ns-input" style="border-radius:7px;font-size:13px"></div>
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Renk</label>
      <input type="color" name="color_<?=$slug?>" value="<?=e($plan['color'])?>" style="width:100%;height:36px;border-radius:7px;border:1px solid var(--border);cursor:pointer;background:none"></div>
    </div>
    <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Özellikler (her satır bir özellik)</label>
    <textarea name="features_<?=$slug?>" rows="4" class="ns-input" style="border-radius:7px;font-size:12px;resize:vertical"><?=e($feats)?></textarea></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Max Yükleme (0=sınırsız)</label>
      <input type="number" name="max_upload_<?=$slug?>" value="<?=$plan['max_upload_count']??0?>" class="ns-input" style="border-radius:7px;font-size:13px"></div>
      <div><label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Max Boyut (MB)</label>
      <input type="number" name="max_size_<?=$slug?>" value="<?=$plan['max_upload_size_mb']??100?>" class="ns-input" style="border-radius:7px;font-size:13px"></div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach([['ads_free_'.$slug,'Reklamsız'],['api_access_'.$slug,'API Erişimi'],['download_'.$slug,'Video İndir'],['publish_ads_'.$slug,'Reklam Yayınla']] as [$n,$l]):?>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;background:var(--bg3);padding:5px 10px;border-radius:6px">
        <input type="checkbox" name="<?=$n?>" <?=($plan[str_replace([$slug,'_'],['','_'],str_replace('_'.$slug,'',$n))]??0)?'checked':''?> style="accent-color:var(--acc)"><?=$l?>
      </label>
      <?php endforeach?>
    </div>
  </div>
</div>
<?php endforeach?>
</div>
<button type="submit" class="btn" style="border-radius:8px">💾 Paketleri Kaydet</button>
</form>
<?php require_once __DIR__.'/includes/footer.php';?>
