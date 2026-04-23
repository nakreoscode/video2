<?php
$page_title='Aktivite Logları';
require_once __DIR__.'/includes/header.php';
$logs=$pdo->query("SELECT l.*,u.username FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 200")->fetchAll();
?>
<div class="ns-card" style="overflow:auto">
<table class="ns-table">
  <thead><tr><th>Kullanıcı</th><th>Aksiyon</th><th>Açıklama</th><th>IP</th><th>Tarih</th></tr></thead>
  <tbody>
  <?php foreach($logs as $l):?>
  <tr>
    <td style="font-size:12px;font-family:monospace;color:var(--text2)"><?=e($l['username']??'guest')?></td>
    <td><span class="sb sb-gray" style="font-size:11px"><?=e($l['action'])?></span></td>
    <td style="font-size:12px;color:var(--text2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($l['description']??'')?></td>
    <td style="font-size:11px;color:var(--text3);font-family:monospace"><?=e($l['ip']??'')?></td>
    <td style="font-size:11px;color:var(--text3);white-space:nowrap"><?=date('d.m.Y H:i',strtotime($l['created_at']))?></td>
  </tr>
  <?php endforeach?>
  <?php if(!$logs):?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text3)">Log yok</td></tr><?php endif?>
  </tbody>
</table>
</div>
<?php require_once __DIR__.'/includes/footer.php';?>
