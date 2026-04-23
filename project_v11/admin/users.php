<?php
$page_title = 'Kullanıcılar';
require_once __DIR__ . '/includes/header.php';
if ($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()) {
    $uid=(int)($_POST['user_id']??0);$act=$_POST['action']??'';
    if($uid&&$act==='ban'){$pdo->prepare("UPDATE users SET status='banned' WHERE id=?")->execute([$uid]);set_flash('success','Engellendi.');}
    if($uid&&$act==='unban'){$pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uid]);set_flash('success','Engel kaldırıldı.');}
    if($uid&&$act==='premium'){$pdo->prepare("UPDATE users SET membership='premium' WHERE id=?")->execute([$uid]);set_flash('success','Premium verildi.');}
    if($uid&&$act==='ultimate'){$pdo->prepare("UPDATE users SET membership='ultimate' WHERE id=?")->execute([$uid]);set_flash('success','Ultimate verildi.');}
    if($uid&&$act==='free'){$pdo->prepare("UPDATE users SET membership='free' WHERE id=?")->execute([$uid]);set_flash('success','Free yapıldı.');}
    if($uid&&$act==='delete'){$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);set_flash('success','Silindi.');}
    redirect('/admin/users.php');
}
$q=trim($_GET['q']??'');$pg=max(1,(int)($_GET['page']??1));$pp=20;$of=($pg-1)*$pp;
$where=$q?"WHERE username LIKE ? OR email LIKE ?":"";$params=$q?["%$q%","%$q%"]:[];
$total=$pdo->prepare("SELECT COUNT(*) FROM users $where");$total->execute($params);$total=$total->fetchColumn();
$users=$pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $pp OFFSET $of");$users->execute($params);$users=$users->fetchAll();
$pages=ceil($total/$pp);
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <form method="GET" style="display:flex;gap:8px">
    <input type="text" name="q" value="<?=e($q)?>" placeholder="Kullanıcı adı veya e-posta..." class="ns-input" style="width:260px;border-radius:8px;padding:8px 12px">
    <button class="btn sm">Ara</button>
  </form>
  <span style="font-size:13px;color:var(--text2)"><?=$total?> kullanıcı</span>
</div>
<div class="ns-card" style="overflow:auto">
<table class="ns-table">
  <thead><tr><th>Kullanıcı</th><th>E-posta</th><th>Üyelik</th><th>Durum</th><th>Kayıt</th><th>İşlem</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><div style="display:flex;align-items:center;gap:8px"><div style="width:30px;height:30px;border-radius:50%;background:var(--acc);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0"><?=strtoupper(substr($u['username'],0,1))?></div><div><p style="font-size:13px;font-weight:500"><?=e($u['username'])?></p></div></div></td>
    <td style="color:var(--text2);font-size:13px"><?=e($u['email'])?></td>
    <td><span class="sb <?=match($u['membership']){'premium'=>'sb-blue','ultimate'=>'sb-purple',default=>'sb-gray'}?>"><?=strtoupper($u['membership'])?></span></td>
    <td><span class="sb <?=match($u['status']){'active'=>'sb-green','banned'=>'sb-red',default=>'sb-yellow'}?>"><?=match($u['status']){'active'=>'Aktif','banned'=>'Engelli',default=>'Bekliyor'}?></span></td>
    <td style="font-size:12px;color:var(--text2)"><?=date('d.m.Y',strtotime($u['created_at']))?></td>
    <td>
      <form method="POST" style="display:flex;gap:4px;flex-wrap:wrap">
        <?=csrf_field()?><input type="hidden" name="user_id" value="<?=$u['id']?>">
        <?php if($u['membership']==='free'):?><button name="action" value="premium" class="btn sm" style="background:#1a73e8;border-radius:6px">Premium</button>
        <?php elseif($u['membership']==='premium'):?><button name="action" value="ultimate" class="btn sm" style="background:#7c3aed;border-radius:6px">Ultimate</button><?php endif?>
        <?php if($u['membership']!=='free'):?><button name="action" value="free" class="btn sm ghost" style="border-radius:6px">Free</button><?php endif?>
        <?php if($u['status']==='banned'):?><button name="action" value="unban" class="btn sm green" style="border-radius:6px">Aç</button>
        <?php else:?><button name="action" value="ban" class="btn sm" style="background:#f59e0b;border-radius:6px">Engelle</button><?php endif?>
        <button name="action" value="delete" onclick="return confirm('Sil?')" class="btn sm" style="background:#dc2626;border-radius:6px">Sil</button>
      </form>
    </td>
  </tr>
  <?php endforeach ?>
  <?php if(!$users):?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3)">Kullanıcı yok</td></tr><?php endif?>
  </tbody>
</table>
</div>
<?php if($pages>1):?>
<div style="display:flex;gap:6px;margin-top:14px;justify-content:center;flex-wrap:wrap">
  <?php for($i=1;$i<=$pages;$i++):?>
  <a href="?page=<?=$i?><?=$q?"&q=".urlencode($q):''?>" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:13px;<?=$i===$pg?'background:var(--acc);color:#fff':'background:var(--bg3);color:var(--text2)' ?>"><?=$i?></a>
  <?php endfor?>
</div>
<?php endif?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
