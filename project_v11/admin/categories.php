<?php
$page_title='Kategoriler';
require_once __DIR__.'/includes/header.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $act=$_POST['action']??'';
    if($act==='add'){$name=trim($_POST['name']??'');$icon=trim($_POST['icon']??'🎬');$desc=trim($_POST['desc']??'');if($name){$slug=slugify($name);try{$pdo->prepare("INSERT INTO categories(name,slug,icon,description) VALUES(?,?,?,?)")->execute([$name,$slug,$icon,$desc]);set_flash('success','Eklendi.');}catch(Exception $e){set_flash('error','Slug çakışması.');}}}
    if($act==='edit'){$id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$icon=trim($_POST['icon']??'');$active=isset($_POST['active'])?1:0;if($id&&$name){$pdo->prepare("UPDATE categories SET name=?,icon=?,active=? WHERE id=?")->execute([$name,$icon,$active,$id]);set_flash('success','Güncellendi.');}}
    if($act==='delete'){$id=(int)($_POST['id']??0);if($id){$pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);set_flash('success','Silindi.');}}
    redirect('/admin/categories.php');
}
$cats=$pdo->query("SELECT c.*,(SELECT COUNT(*) FROM video_categories vc WHERE vc.category_id=c.id) vc FROM categories c ORDER BY sort_order,name")->fetchAll();
?>
<div style="display:grid;grid-template-columns:300px 1fr;gap:16px">
  <div class="ns-card" style="padding:20px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">+ Yeni Kategori</h3>
    <form method="POST" style="display:flex;flex-direction:column;gap:10px">
      <?=csrf_field()?><input type="hidden" name="action" value="add">
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">İsim *</label><input type="text" name="name" required class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px"></div>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">İkon (emoji)</label><input type="text" name="icon" value="🎬" class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px"></div>
      <div><label style="display:block;font-size:12px;color:var(--text2);margin-bottom:4px">Açıklama</label><input type="text" name="desc" class="ns-input" style="border-radius:7px;padding:8px 10px;font-size:13px"></div>
      <button type="submit" class="btn sm" style="border-radius:7px">Ekle</button>
    </form>
  </div>
  <div class="ns-card" style="overflow:auto">
    <table class="ns-table">
      <thead><tr><th>Kategori</th><th>Video</th><th>Durum</th><th>İşlem</th></tr></thead>
      <tbody>
      <?php foreach($cats as $c):?>
      <tr>
        <td><div style="display:flex;align-items:center;gap:8px"><span style="font-size:20px"><?=$c['icon']?></span><span style="font-size:13px;font-weight:500"><?=e($c['name'])?></span></div></td>
        <td style="font-size:13px;color:var(--text2)"><?=$c['vc']?></td>
        <td><span class="sb <?=$c['active']?'sb-green':'sb-gray'?>"><?=$c['active']?'Aktif':'Pasif'?></span></td>
        <td style="display:flex;gap:6px">
          <button onclick="document.getElementById('em-<?=$c['id']?>').style.display='flex'" class="btn sm ghost" style="border-radius:6px">✏️</button>
          <form method="POST" class="inline" onsubmit="return confirm('Sil?')">
            <?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>">
            <button class="btn sm" style="background:#dc2626;border-radius:6px">🗑</button>
          </form>
        </td>
      </tr>
      <tr id="em-<?=$c['id']?>" style="display:none">
        <td colspan="4" style="background:var(--bg3);padding:12px 16px">
          <form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?=csrf_field()?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=$c['id']?>">
            <input type="text" name="name" value="<?=e($c['name'])?>" class="ns-input" style="width:160px;border-radius:6px;padding:7px 10px;font-size:13px">
            <input type="text" name="icon" value="<?=e($c['icon'])?>" class="ns-input" style="width:70px;border-radius:6px;padding:7px 10px;font-size:13px">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer"><input type="checkbox" name="active" <?=$c['active']?'checked':''?> style="accent-color:var(--acc)">Aktif</label>
            <button class="btn sm" style="border-radius:6px">Kaydet</button>
            <button type="button" onclick="document.getElementById('em-<?=$c['id']?>').style.display='none'" class="btn sm ghost" style="border-radius:6px">İptal</button>
          </form>
        </td>
      </tr>
      <?php endforeach?>
      <?php if(!$cats):?><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text3)">Kategori yok</td></tr><?php endif?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__.'/includes/footer.php';?>
