<?php
$page_title='Duyuru Yönetimi';
require_once __DIR__.'/includes/header.php';

if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $action=$_POST['action']??'';
    if($action==='create'){
        $title=trim($_POST['title']??'');
        $msg=trim($_POST['message']??'');
        $icon=trim($_POST['icon']??'📢');
        $type=in_array($_POST['type']??'',['info','success','warning','danger'])?$_POST['type']:'info';
        $target=in_array($_POST['target']??'',['all','premium','ultimate','free'])?$_POST['target']:'all';
        $expires=!empty($_POST['expires'])?$_POST['expires']:null;
        $send_notif=isset($_POST['send_notification']);
        if($title&&$msg){
            $pdo->prepare("INSERT INTO announcements(title,message,icon,type,target,active,created_by,expires_at) VALUES(?,?,?,?,?,1,?,?)")
                ->execute([$title,$msg,$icon,$type,$target,$_SESSION['admin_id']??null,$expires]);
            $aid=(int)$pdo->lastInsertId();
            if($send_notif) send_announcement_notifications($aid,$target);
            set_flash('success','Duyuru yayınlandı'.($send_notif?' ve bildirim gönderildi':'').'.');
        }
    }
    if($action==='toggle'){
        $id=(int)($_POST['ann_id']??0);
        $pdo->prepare("UPDATE announcements SET active=1-active WHERE id=?")->execute([$id]);
    }
    if($action==='delete'){
        $id=(int)($_POST['ann_id']??0);
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
        set_flash('success','Duyuru silindi.');
    }
    redirect('/admin/announcements.php');
}

$anns=$pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>
<div style="display:grid;grid-template-columns:1fr 420px;gap:20px;max-width:1400px">

  <!-- Sol: Duyuru listesi -->
  <div>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:14px">Yayınlanan Duyurular</h2>
    <div class="ns-card" style="overflow:auto">
      <table class="ns-table">
        <thead><tr><th>Başlık</th><th>Hedef</th><th>Tip</th><th>Durum</th><th>Tarih</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php foreach($anns as $a):?>
        <tr>
          <td><div style="display:flex;align-items:center;gap:8px"><span style="font-size:18px"><?=e($a['icon'])?></span><div><p style="font-size:13px;font-weight:500"><?=e($a['title'])?></p><p style="font-size:12px;color:var(--text2)"><?=e(substr($a['message'],0,50))?>...</p></div></div></td>
          <td><span class="sb sb-blue"><?=ucfirst($a['target'])?></span></td>
          <td><span class="sb <?=match($a['type']){'success'=>'sb-green','warning'=>'sb-yellow','danger'=>'sb-red',default=>'sb-blue'}?>"><?=ucfirst($a['type'])?></span></td>
          <td><span class="sb <?=$a['active']?'sb-green':'sb-red'?>"><?=$a['active']?'Aktif':'Pasif'?></span></td>
          <td style="font-size:12px;color:var(--text2)"><?=date('d.m.Y H:i',strtotime($a['created_at']))?></td>
          <td>
            <div style="display:flex;gap:4px">
              <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="ann_id" value="<?=$a['id']?>"><?=csrf_field()?>
                <button class="btn sm <?=$a['active']?'':'green'?>" style="border-radius:6px"><?=$a['active']?'Kapat':'Aç'?></button>
              </form>
              <form method="POST" onsubmit="return confirm('Sil?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="ann_id" value="<?=$a['id']?>"><?=csrf_field()?>
                <button class="btn sm" style="background:#dc2626;border-radius:6px">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach?>
        <?php if(!$anns):?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3)">Duyuru yok</td></tr><?php endif?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sağ: Yeni duyuru -->
  <div>
    <h2 style="font-size:16px;font-weight:600;margin-bottom:14px">+ Yeni Duyuru</h2>
    <div class="ns-card" style="padding:20px">
      <form method="POST" style="display:flex;flex-direction:column;gap:12px">
        <?=csrf_field()?><input type="hidden" name="action" value="create">
        <div style="display:grid;grid-template-columns:1fr 60px;gap:8px">
          <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Başlık</label>
          <input type="text" name="title" required class="ns-input" style="border-radius:8px"></div>
          <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">İkon</label>
          <input type="text" name="icon" value="📢" class="ns-input" style="border-radius:8px;text-align:center;font-size:20px"></div>
        </div>
        <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Mesaj</label>
        <textarea name="message" rows="3" required class="ns-input" style="border-radius:8px;resize:vertical"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Tip</label>
          <select name="type" class="ns-input" style="border-radius:8px;font-size:13px">
            <option value="info">ℹ️ Bilgi</option><option value="success">✅ Başarı</option>
            <option value="warning">⚠️ Uyarı</option><option value="danger">🚨 Önemli</option>
          </select></div>
          <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Hedef</label>
          <select name="target" class="ns-input" style="border-radius:8px;font-size:13px">
            <option value="all">👥 Herkes</option><option value="free">⚪ Ücretsiz</option>
            <option value="premium">💙 Premium</option><option value="ultimate">💜 Ultimate</option>
          </select></div>
        </div>
        <div><label style="font-size:12px;color:var(--text2);display:block;margin-bottom:4px">Bitiş Tarihi (Opsiyonel)</label>
        <input type="datetime-local" name="expires" class="ns-input" style="border-radius:8px;font-size:13px"></div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="send_notification" checked style="accent-color:var(--acc)">
          Kullanıcılara bildirim gönder
        </label>
        <button type="submit" class="btn" style="border-radius:8px">📢 Yayınla</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/includes/footer.php';?>
