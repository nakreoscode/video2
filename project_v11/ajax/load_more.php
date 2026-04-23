<?php
define('NS_LOADED',true);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$cat   = trim($_GET['cat'] ?? '');
$sort  = in_array($_GET['sort']??'',['newest','popular','oldest']) ? $_GET['sort'] : 'newest';
$page  = max(2,(int)($_GET['page'] ?? 2));
$per   = 24;

$where = ["uv.status='active'"];
$params = [];

if ($q) {
    $where[] = "(uv.title LIKE ? OR uv.tags LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%";
}
if ($cat) {
    $cat_row = $pdo->prepare("SELECT id FROM categories WHERE slug=? LIMIT 1");
    $cat_row->execute([$cat]); $cat_row = $cat_row->fetch();
    if ($cat_row) {
        $where[] = "EXISTS (SELECT 1 FROM video_categories vc WHERE vc.video_id=uv.id AND vc.category_id=?)";
        $params[] = $cat_row['id'];
    }
}

$order = match($sort) { 'popular'=>'uv.views DESC', 'oldest'=>'uv.created_at ASC', default=>'uv.created_at DESC' };
$where_sql = implode(' AND ', $where);
$offset = ($page-1)*$per;

$stmt = $pdo->prepare("SELECT uv.*,u.username,u.full_name,ch.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels ch ON ch.user_id=uv.user_id WHERE $where_sql ORDER BY $order LIMIT $per OFFSET $offset");
$stmt->execute($params);
$videos = $stmt->fetchAll();

ob_start();
foreach ($videos as $uv) {
    $v = ['platform'=>'local','id'=>$uv['id'],'title'=>$uv['title'],'thumbnail'=>$uv['thumbnail']??'','channel'=>$uv['ch_name']?:($uv['full_name']?:($uv['username']??'')),'duration'=>$uv['duration']??0,'type'=>$uv['type']??'normal','views'=>$uv['views']??0];
    include __DIR__.'/../includes/video_card.php';
}
$html = ob_get_clean();

echo json_encode(['html'=>$html,'has_more'=>count($videos)>=$per,'page'=>$page]);
