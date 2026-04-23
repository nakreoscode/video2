<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
check_maintenance();
$user = get_user();

$query    = trim($_GET['q'] ?? '');
$cat_slug = trim($_GET['cat'] ?? '');
$sort     = in_array($_GET['sort']??'', ['newest','popular','oldest']) ? $_GET['sort'] : 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;

// Kategoriler
$categories = $pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order")->fetchAll();

// Aktif kategori
$cur_cat = null;
if ($cat_slug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $cat_slug) { $cur_cat = $cat; break; }
    }
}

// Video sorgusu
$where = ["uv.status='active'"];
$params = [];

if ($query) {
    $where[] = "(uv.title LIKE ? OR uv.description LIKE ? OR uv.tags LIKE ?)";
    $params[] = "%$query%"; $params[] = "%$query%"; $params[] = "%$query%";
}
if ($cur_cat) {
    $where[] = "EXISTS (SELECT 1 FROM video_categories vc WHERE vc.video_id=uv.id AND vc.category_id=?)";
    $params[] = $cur_cat['id'];
}

$order = match($sort) {
    'popular' => 'uv.views DESC',
    'oldest'  => 'uv.created_at ASC',
    default   => 'uv.created_at DESC',
};

$where_sql = implode(' AND ', $where);
$offset = ($page-1)*$per_page;

$total = $pdo->prepare("SELECT COUNT(*) FROM uploaded_videos uv WHERE $where_sql");
$total->execute($params); $total = (int)$total->fetchColumn();
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("SELECT uv.*,u.username,u.full_name,u.avatar,ch.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels ch ON ch.user_id=uv.user_id WHERE $where_sql ORDER BY $order LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$videos = $stmt->fetchAll();

// En son yüklenen (sidebar için)
$latest = $pdo->query("SELECT uv.*,u.username FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id WHERE uv.status='active' ORDER BY uv.created_at DESC LIMIT 5")->fetchAll();

// En çok izlenen
$show_popular = get_setting('show_popular_widget','1') === '1';
$popular = [];
if ($show_popular && !$query && !$cur_cat) {
    $popular = $pdo->query("SELECT uv.*,u.username,ch.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels ch ON ch.user_id=uv.user_id WHERE uv.status='active' AND uv.views>0 ORDER BY uv.views DESC LIMIT 6")->fetchAll();
}

$page_title = $query ? "\"$query\" - Arama" : ($cur_cat ? $cur_cat['name'] : 'Ana Sayfa');
include __DIR__ . '/includes/header.php';
?>
<style>
.filter-bar{display:flex;align-items:center;gap:10px;padding:12px 24px;background:var(--nav);border-bottom:1px solid var(--border);flex-wrap:wrap;position:sticky;top:56px;z-index:50}
.sort-btn{padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--text2);font-size:13px;cursor:pointer;transition:.15s;font-family:'Roboto',sans-serif}
.sort-btn:hover,.sort-btn.on{background:var(--text);color:var(--bg);border-color:var(--text)}
.cat-chip{padding:6px 14px;border-radius:99px;border:1px solid var(--border);background:none;color:var(--text2);font-size:13px;cursor:pointer;transition:.15s;white-space:nowrap;text-decoration:none;display:inline-block}
.cat-chip:hover,.cat-chip.on{background:var(--acc);color:#fff;border-color:var(--acc)}
.pagination{display:flex;gap:6px;justify-content:center;padding:24px 0;flex-wrap:wrap}
.pag-btn{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--text2);cursor:pointer;font-size:14px;transition:.15s;display:flex;align-items:center;justify-content:center;text-decoration:none}
.pag-btn:hover,.pag-btn.on{background:var(--acc);color:#fff;border-color:var(--acc)}
</style>

<!-- Kategori & Filtre -->
<div class="filter-bar">
  <div style="display:flex;gap:6px;overflow-x:auto;flex:1;scrollbar-width:none">
    <a href="/" class="cat-chip <?=!$cat_slug&&!$query?'on':''?>">🏠 Tümü</a>
    <?php foreach($categories as $cat): ?>
    <a href="/?cat=<?=e($cat['slug'])?>" class="cat-chip <?=$cat_slug===$cat['slug']?'on':''?>"><?=$cat['icon']?> <?=e($cat['name'])?></a>
    <?php endforeach?>
  </div>
  <div style="display:flex;gap:6px;flex-shrink:0">
    <a href="?<?=http_build_query(array_merge($_GET,['sort'=>'newest','page'=>1]))?>" class="sort-btn <?=$sort==='newest'?'on':''?>">🕐 Yeni</a>
    <a href="?<?=http_build_query(array_merge($_GET,['sort'=>'popular','page'=>1]))?>" class="sort-btn <?=$sort==='popular'?'on':''?>">🔥 Popüler</a>
  </div>
</div>

<div class="pg">
  <?php if($query): ?>
  <p style="font-size:14px;color:var(--text2);margin-bottom:16px">"<strong style="color:var(--text)"><?=e($query)?></strong>" — <?=$total?> sonuç</p>
  <?php elseif($cur_cat): ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
    <span style="font-size:32px"><?=$cur_cat['icon']?></span>
    <h1 style="font-size:22px;font-weight:700"><?=e($cur_cat['name'])?></h1>
    <span style="font-size:13px;color:var(--text2)"><?=$total?> video</span>
  </div>
  <?php endif?>

  <?php if(!empty($popular)): ?>
  <div class="sec-title" style="margin-bottom:12px">🏆 En Çok İzlenenler</div>
  <div class="vg" style="margin-bottom:28px">
    <?php foreach($popular as $uv):
      $v=['platform'=>'local','id'=>$uv['id'],'title'=>$uv['title'],'thumbnail'=>$uv['thumbnail']??'','channel'=>$uv['ch_name']?:($uv['username']??''),'duration'=>$uv['duration']??0,'type'=>$uv['type']??'normal','views'=>$uv['views']??0];
      include __DIR__.'/includes/video_card.php';
    endforeach?>
  </div>
  <?php endif?>

  <?php if(!empty($videos)): ?>
  <?php if(!$query&&!$cur_cat): ?><div class="sec-title" style="margin-bottom:12px">📹 <?=$sort==='popular'?'En Çok İzlenenler':'Son Yüklenen Videolar'?></div><?php endif?>
  <div class="vg">
    <?php foreach($videos as $uv):
      $v=['platform'=>'local','id'=>$uv['id'],'title'=>$uv['title'],'thumbnail'=>$uv['thumbnail']??'','channel'=>$uv['ch_name']?:($uv['full_name']?:($uv['username']??'')),'duration'=>$uv['duration']??0,'type'=>$uv['type']??'normal','views'=>$uv['views']??0];
      include __DIR__.'/includes/video_card.php';
    endforeach?>
  </div>

  <!-- Sayfalama -->
  <?php if($total_pages > 1): ?>
  <div class="pagination">
    <?php if($page>1): ?><a href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>" class="pag-btn">‹</a><?php endif?>
    <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
    <a href="?<?=http_build_query(array_merge($_GET,['page'=>$p]))?>" class="pag-btn <?=$p===$page?'on':''?>"><?=$p?></a>
    <?php endfor?>
    <?php if($page<$total_pages): ?><a href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>" class="pag-btn">›</a><?php endif?>
  </div>
  <?php endif?>

  <?php else: ?>
  <div style="text-align:center;padding:80px 20px">
    <div style="font-size:64px;margin-bottom:16px"><?=$query?'🔍':'🎬'?></div>
    <p style="font-size:18px;font-weight:600;margin-bottom:8px"><?=$query?'Sonuç bulunamadı':'Henüz video yok'?></p>
    <p style="font-size:14px;color:var(--text2)"><?=$query?'Farklı kelimeler deneyin.':'İlk videoyu yükleyin!'?></p>
    <?php if($user): ?><a href="/upload.php" class="nsbtn" style="border-radius:20px;margin-top:16px;display:inline-flex">📤 Video Yükle</a><?php endif?>
  </div>
  <?php endif?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
