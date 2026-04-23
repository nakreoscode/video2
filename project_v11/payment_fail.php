<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$page_title = 'Ödeme Başarısız';
include __DIR__ . '/includes/header.php';
?>
<div style="max-width:500px;margin:60px auto;text-align:center;padding:24px">
  <div style="font-size:72px;margin-bottom:20px">❌</div>
  <h1 style="font-size:24px;font-weight:700;margin-bottom:10px">Ödeme Başarısız</h1>
  <p style="font-size:15px;color:var(--text2);margin-bottom:24px">Ödeme işlemi tamamlanamadı. Lütfen tekrar deneyin.</p>
  <a href="/checkout.php" class="nsbtn" style="border-radius:20px;padding:12px 28px">Tekrar Dene</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
