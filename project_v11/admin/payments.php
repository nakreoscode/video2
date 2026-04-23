<?php
$page_title='Ödemeler';
require_once __DIR__.'/includes/header.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $id=(int)($_POST['payment_id']??0);$act=$_POST['action']??'';
    if($id&&$act==='approve'){
        $pay=$pdo->prepare("SELECT * FROM payments WHERE id=? LIMIT 1");$pay->execute([$id]);$pay=$pay->fetch();
        if($pay){
            $pdo->prepare("UPDATE payments SET status='approved',approved_at=NOW() WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE users SET membership=? WHERE id=?")->execute([$pay['plan'],$pay['user_id']]);
            $usr=$pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");$usr->execute([$pay['user_id']]);$usr=$usr->fetch();
            try{require_once dirname(dirname(__DIR__)).'/includes/mailer.php';$m=new Mailer();$m->paymentApproved($usr['email'],$usr['username'],$pay['plan'],$pay['amount']);}catch(Exception $e){}
            set_flash('success','Onaylandı, üyelik verildi.');
        }
    }
    if($id&&$act==='reject'){$pdo->prepare("UPDATE payments SET status='rejected' WHERE id=?")->execute([$id]);set_flash('success','Reddedildi.');}
    redirect('/admin/payments.php');
}
$filter=$_GET['status']??'all';
$where=$filter!=='all'?"WHERE p.status='".htmlspecialchars($filter)."'":'';
$pays=$pdo->query("SELECT p.*,u.username,u.email FROM payments p JOIN users u ON u.id=p.user_id $where ORDER BY p.created_at DESC LIMIT 100")->fetchAll();
?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach(['all'=>'Tümü','pending'=>'Bekleyen','approved'=>'Onaylı','rejected'=>'Reddedilen'] as $k=>$l):?>
  <a href="?status=<?=$k?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;border:1px solid;<?=$filter===$k?'background:var(--acc);border-color:var(--acc);color:#fff':'background:var(--bg3);border-color:var(--border);color:var(--text2)'?>"><?=$l?></a>
  <?php endforeach?>
</div>
<div class="ns-card" style="overflow:auto">
<table class="ns-table">
  <thead><tr><th>Kullanıcı</th><th>Plan</th><th>Tutar</th><th>Yöntem</th><th>Dekont/TXID</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
  <tbody>
  <?php foreach($pays as $p):?>
  <tr>
    <td><p style="font-size:13px;font-weight:500"><?=e($p['username'])?></p><p style="font-size:11px;color:var(--text2)"><?=e($p['email'])?></p></td>
    <td><span class="sb <?=$p['plan']==='ultimate'?'sb-purple':'sb-blue'?>"><?=strtoupper($p['plan'])?></span></td>
    <td style="font-weight:600;color:var(--acc)"><?=$p['amount']?> ₺</td>
    <td style="font-size:12px;color:var(--text2)"><?=ucfirst($p['method'])?></td>
    <td style="font-size:11px;color:var(--text2);max-width:160px;overflow:hidden;text-overflow:ellipsis"><?=e($p['receipt_info']??'-')?></td>
    <td><span class="sb <?=match($p['status']){'approved'=>'sb-green','rejected'=>'sb-red',default=>'sb-yellow'}?>"><?=match($p['status']){'approved'=>'Onaylı','rejected'=>'Reddedildi',default=>'Bekliyor'}?></span></td>
    <td style="font-size:12px;color:var(--text2);white-space:nowrap"><?=date('d.m.Y H:i',strtotime($p['created_at']))?></td>
    <td><?php if($p['status']==='pending'):?>
      <form method="POST" style="display:flex;gap:4px">
        <?=csrf_field()?><input type="hidden" name="payment_id" value="<?=$p['id']?>">
        <button name="action" value="approve" class="btn sm green" style="border-radius:6px">✓</button>
        <button name="action" value="reject" class="btn sm" style="background:#dc2626;border-radius:6px">✗</button>
      </form>
    <?php else:?><span style="font-size:12px;color:var(--text3)"><?=$p['approved_at']?date('d.m',strtotime($p['approved_at'])):'-'?></span><?php endif?></td>
  </tr>
  <?php endforeach?>
  <?php if(!$pays):?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)">Ödeme yok</td></tr><?php endif?>
  </tbody>
</table>
</div>
<?php require_once __DIR__.'/includes/footer.php';?>
