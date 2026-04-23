<?php
$page_title = 'Video Yönetimi';
require_once __DIR__ . '/includes/header.php';
if ($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()) {
    $vid=(int)($_POST['video_id']??0);$act=$_POST['action']??'';
    if($vid&&$act==='approve'){$pdo->prepare("UPDATE uploaded_videos SET status='active' WHERE id=?")->execute([$vid]);set_flash('success','Onaylandı.');}
    if($vid&&$act==='reject'){$pdo->prepare("UPDATE uploaded_videos SET status='rejected' WHERE id=?")->execute([$vid]);set_flash('success','Reddedildi.');}
    if($vid&&$act==='delete'){$pdo->prepare("DELETE FROM uploaded_videos WHERE id=?")->execute([$vid]);set_flash('success','Silindi.');}
    redirect('/admin/videos.php');
}
$filter=$_GET['status']??'all';
$where=$filter!=='all'?"WHERE uv.status='".htmlspecialchars($filter)."'":'';
$videos=$pdo->query("SELECT uv.*,u.username FROM uploaded_videos uv JOIN users u ON u.id=uv.user_id $where ORDER BY uv.created_at DESC LIMIT 100")->fetchAll();
?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach(['all'=>'Tümü','pending'=>'Bekleyen','active'=>'Aktif','rejected'=>'Reddedilen'] as $k=>$l):?>
  <a href="?status=<?=$k?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;border:1px solid;<?=$filter===$k?'background:var(--acc);border-color:var(--acc);color:#fff':'background:var(--bg3);border-color:var(--border);color:var(--text2)'?>"><?=$l?></a>
  <?php endforeach?>
</div>
<div class="ns-card" style="overflow:auto">
<table class="ns-table">
  <thead><tr><th>Video</th><th>Kullanıcı</th><th>Tür</th><th>Görüntülenme</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
  <tbody>
  <?php foreach ($videos as $v):?>
  <tr>
    <td style="max-width:280px"><div style="display:flex;align-items:center;gap:8px"><div style="width:60px;height:34px;border-radius:4px;background:var(--bg3);overflow:hidden;flex-shrink:0"><?php if($v['thumbnail']):?><img src="<?=e($v['thumbnail'])?>" style="width:100%;height:100%;object-fit:cover"><?php endif?></div><p style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($v['title'])?></p></div></td>
    <td style="font-size:13px;color:var(--text2)"><?=e($v['username'])?></td>
    <td><span class="sb sb-gray" style="font-size:11px"><?=$v['type']==='short'?'📱 Short':'🎬 Normal'?></span></td>
    <td style="font-size:13px;color:var(--text2)"><?=$v['views']?></td>
    <td><span class="sb <?=match($v['status']){'active'=>'sb-green','pending'=>'sb-yellow','processing'=>'sb-blue',default=>'sb-red'}?>"><?=match($v['status']){'active'=>'Aktif','pending'=>'Bekliyor','processing'=>'İşleniyor',default=>'Reddedildi'}?></span></td>
    <td style="font-size:12px;color:var(--text2);white-space:nowrap"><?=date('d.m.Y',strtotime($v['created_at']))?></td>
    <td><form method="POST" style="display:flex;gap:4px">
      <?=csrf_field()?><input type="hidden" name="video_id" value="<?=$v['id']?>">
      <?php if($v['status']==='pending'):?><button name="action" value="approve" class="btn sm green" style="border-radius:6px">✓</button><button name="action" value="reject" class="btn sm" style="background:#f59e0b;border-radius:6px">✗</button><?php endif?>
      <button name="action" value="delete" onclick="return confirm('Sil?')" class="btn sm" style="background:#dc2626;border-radius:6px">🗑</button>
    </form></td>
  </tr>
  <?php endforeach?>
  <?php if(!$videos):?><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3)">Video yok</td></tr><?php endif?>
  </tbody>
</table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
