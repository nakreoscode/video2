<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$stats = [
    'users'   => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'pro'     => $pdo->query("SELECT COUNT(*) FROM users WHERE membership='pro'")->fetchColumn(),
    'full'    => $pdo->query("SELECT COUNT(*) FROM users WHERE membership='full'")->fetchColumn(),
    'videos'  => $pdo->query("SELECT COUNT(*) FROM uploaded_videos")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='approved'")->fetchColumn(),
    'comments'=> $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'today'   => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_pays  = $pdo->query("SELECT p.*,u.username FROM payments p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
// Depolama bilgisi
$storage_type = get_setting('storage_type','local');
$video_count = $pdo->query("SELECT COUNT(*) FROM uploaded_videos WHERE status='active'")->fetchColumn();
$pending_vids = $pdo->query("SELECT COUNT(*) FROM uploaded_videos WHERE status='pending'")->fetchColumn();
?>

<?php if ($stats['pending'] > 0): ?>
<div style="background:rgba(255,165,0,.08);border:1px solid rgba(255,165,0,.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <span style="color:#fbbf24;font-size:13px;font-weight:600">⏳ <?=$stats['pending']?> bekleyen ödeme</span>
    <a href="/admin/payments.php?status=pending" class="btn sm">İncele →</a>
</div>
<?php endif ?>

<!-- Stat grid -->
<div class="grid-4" style="margin-bottom:24px">
<?php foreach([
    ['👥','Kullanıcılar',$stats['users'],'#3b82f6'],
    ['⭐','Pro',$stats['pro'],'#f59e0b'],
    ['💜','Full',$stats['full'],'#a78bfa'],
    ['🎬','Video',$stats['videos'],'#10b981'],
    ['⏳','Bekleyen',$stats['pending'],'#ef4444'],
    ['💰','Gelir',$stats['revenue'].' ₺','#22c55e'],
    ['💬','Yorum',$stats['comments'],'#6366f1'],
    ['🆕','Bugün',$stats['today'],'#06b6d4'],
] as [$ic,$lb,$val,$color]): ?>
<div class="ns-card" style="padding:16px;text-align:center">
    <div style="font-size:22px;margin-bottom:6px"><?=$ic?></div>
    <div style="font-size:20px;font-weight:700;color:<?=$color?>"><?=$val?></div>
    <div style="font-size:12px;color:var(--text2);margin-top:3px"><?=$lb?></div>
</div>
<?php endforeach ?>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <!-- Son Kullanıcılar -->
    <div class="ns-card" style="overflow:hidden">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <p style="font-size:13px;font-weight:600">👥 Son Kayıtlar</p>
            <a href="/admin/users.php" style="font-size:12px;color:var(--acc)">Tümü →</a>
        </div>
        <table class="ns-table">
            <tbody>
            <?php foreach ($recent_users as $u): ?>
            <tr>
                <td style="display:flex;align-items:center;gap:10px">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--acc);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                    <div><p style="font-size:13px;font-weight:500"><?= e($u['username']) ?></p><p style="font-size:11px;color:var(--text2)"><?= e($u['email']) ?></p></div>
                </td>
                <td><span class="sb <?= match($u['membership']){'pro'=>'sb-red','full'=>'sb-purple',default=>'sb-gray'} ?>"><?= strtoupper($u['membership']) ?></span></td>
                <td style="font-size:11px;color:var(--text2)"><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Son Ödemeler -->
    <div class="ns-card" style="overflow:hidden">
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <p style="font-size:13px;font-weight:600">💳 Son Ödemeler</p>
            <a href="/admin/payments.php" style="font-size:12px;color:var(--acc)">Tümü →</a>
        </div>
        <table class="ns-table">
            <tbody>
            <?php foreach ($recent_pays as $p): ?>
            <tr>
                <td><p style="font-size:13px;font-weight:500"><?= e($p['username']) ?></p><p style="font-size:11px;color:var(--text2)"><?= ucfirst($p['method']) ?></p></td>
                <td style="font-weight:600;color:var(--acc)"><?= $p['amount'] ?> ₺</td>
                <td><span class="sb <?= match($p['status']){'approved'=>'sb-green','rejected'=>'sb-red',default=>'sb-yellow'} ?>"><?= match($p['status']){'approved'=>'✓','rejected'=>'✗',default=>'⏳'} ?></span></td>
            </tr>
            <?php endforeach ?>
            <?php if (!$recent_pays): ?><tr><td colspan="3" style="text-align:center;color:var(--text3);padding:20px;font-size:13px">Ödeme yok</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Platformlar -->
<div class="ns-card" style="padding:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <p style="font-size:13px;font-weight:600">🌐 Platform Durumu</p>
        <a href="/admin/platforms.php" style="font-size:12px;color:var(--acc)">Ayarlar →</a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($platforms as $p): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 14px;border-radius:8px;background:var(--bg3);font-size:13px">
            <div style="width:6px;height:6px;border-radius:50%;background:<?= $p['active']?'#22c55e':'#ef4444' ?>"></div>
            <?= e($p['name']) ?>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
