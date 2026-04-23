<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$platform = trim($_GET['platform'] ?? '');
$video_id = trim($_GET['id'] ?? '');
$autoplay = isset($_GET['autoplay']) ? (int)$_GET['autoplay'] : 1;
$controls = isset($_GET['controls']) ? (int)$_GET['controls'] : 1;
$theme    = $_GET['theme'] ?? 'dark';

if (!$platform || !$video_id) { http_response_code(400); die('Geçersiz parametre.'); }

$embed = match($platform) {
    'youtube'     => "https://www.youtube.com/embed/{$video_id}?autoplay={$autoplay}&rel=0&controls={$controls}",
    'dailymotion' => "https://www.dailymotion.com/embed/video/{$video_id}?autoplay={$autoplay}",
    'vimeo'       => "https://player.vimeo.com/video/{$video_id}?autoplay={$autoplay}",
    'twitch'      => "https://player.twitch.tv/?video={$video_id}&parent=".($_SERVER['HTTP_HOST']??'localhost')."&autoplay=false",
    default       => ''
};

// Yerel video
$direct_url = '';
$title = 'Video';
if (in_array($platform, ['local','wasabi'])) {
    $uv = $pdo->prepare("SELECT * FROM uploaded_videos WHERE id=? AND status='active' LIMIT 1");
    $uv->execute([$video_id]); $uv = $uv->fetch();
    if ($uv) { $direct_url = $uv['file_path']??''; $title = $uv['title']??'Video'; }
}

$bg = $theme === 'light' ? '#fff' : '#000';
$fg = $theme === 'light' ? '#000' : '#fff';
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;height:100%;background:<?= $bg ?>}
.player-wrap{width:100%;height:100%}
iframe,video{width:100%;height:100%;border:none;display:block}
.no-embed{display:flex;align-items:center;justify-content:center;height:100%;color:<?= $fg ?>;font-family:sans-serif;font-size:14px;text-align:center;padding:20px}
</style>
<?php if ($direct_url): ?>
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css">
<?php endif ?>
</head>
<body>
<div class="player-wrap">
  <?php if ($direct_url): ?>
  <video id="ep" playsinline controls <?= $autoplay?'autoplay':'' ?>>
    <source src="<?= e($direct_url) ?>" type="video/mp4">
  </video>
  <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
  <script>new Plyr('#ep',{controls:['play-large','play','progress','current-time','mute','volume','fullscreen']});</script>
  <?php elseif ($embed): ?>
  <iframe src="<?= e($embed) ?>" allowfullscreen allow="autoplay;fullscreen;picture-in-picture"></iframe>
  <?php else: ?>
  <div class="no-embed">
    <div>
      <p style="font-size:32px;margin-bottom:10px">❌</p>
      <p>Bu platform için gömülü oynatıcı desteklenmiyor.</p>
    </div>
  </div>
  <?php endif ?>
</div>
</body>
</html>
