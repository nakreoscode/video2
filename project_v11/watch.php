<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/auth.php';
start_session();
$user=get_user();
$platform=trim($_GET['platform']??'');$video_id=trim($_GET['id']??'');
if(!$platform||!$video_id)redirect('/');
$title=trim($_GET['title']??'');$channel=trim($_GET['channel']??'');
$thumbnail=trim($_GET['thumb']??'');$v_type=trim($_GET['type']??'normal');
$is_short=$v_type==='short';$is_image=$v_type==='image';$direct_url='';

// Yerel/Wasabi video
$uploader_name='';$uploader_avatar='';$uploader_id=0;
if(in_array($platform,['local','wasabi','idrive'])){
    $uv=$pdo->prepare("SELECT uv.*,u.username,u.full_name,u.avatar,c.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels c ON c.user_id=uv.user_id WHERE uv.id=? LIMIT 1");
    $uv->execute([$video_id]);$uv=$uv->fetch();
    if($uv){
        $direct_url=$uv['file_path']??'';
        $thumbnail=$uv['thumbnail']?:$thumbnail;
        $title=$uv['title']?:$title;
        $is_short=($uv['type']==='short');
        $uploader_name=$uv['ch_name']?:($uv['full_name']?:$uv['username']??'');
        $uploader_avatar=$uv['avatar']??'';
        $uploader_id=(int)($uv['user_id']??0);
        $channel=$uploader_name;
        $pdo->prepare("UPDATE uploaded_videos SET views=views+1 WHERE id=?")->execute([$video_id]);
    }
}
if($platform==='youtube'){
    $thumbnail=$thumbnail?:"https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg";
    if(!$title){$oe=@json_decode(@file_get_contents("https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json"),true);$title=$oe['title']??'Video';$channel=$oe['author_name']??'';}
}
if(!$title)$title='Video';$page_title=$title;
$origin=(isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$embed_noa=match($platform){'youtube'=>"https://www.youtube.com/embed/{$video_id}?rel=0&enablejsapi=1&origin=".urlencode($origin),'dailymotion'=>"https://www.dailymotion.com/embed/video/{$video_id}",'vimeo'=>"https://player.vimeo.com/video/{$video_id}",'twitch'=>"https://player.twitch.tv/?video={$video_id}&parent=".($_SERVER['HTTP_HOST']??'localhost')."&autoplay=false",default=>''};
$embed_auto=match($platform){'youtube'=>"https://www.youtube.com/embed/{$video_id}?autoplay=1&rel=0&enablejsapi=1&origin=".urlencode($origin),'dailymotion'=>"https://www.dailymotion.com/embed/video/{$video_id}?autoplay=1",'vimeo'=>"https://player.vimeo.com/video/{$video_id}?autoplay=1",'twitch'=>"https://player.twitch.tv/?video={$video_id}&parent=".($_SERVER['HTTP_HOST']??'localhost')."&autoplay=false",default=>''};
if($user){try{$pdo->prepare("INSERT INTO watch_history(user_id,platform,video_id,title,thumbnail,channel) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE watched_at=NOW()")->execute([$user['id'],$platform,$video_id,$title,$thumbnail,$channel]);}catch(Exception $e){}}
$lc=$pdo->prepare("SELECT COUNT(*) FROM likes WHERE platform=? AND video_id=?");$lc->execute([$platform,$video_id]);$like_count=$lc->fetchColumn();
$liked=false;$is_saved=false;
if($user){
    $ls=$pdo->prepare("SELECT id FROM likes WHERE user_id=? AND platform=? AND video_id=?");$ls->execute([$user['id'],$platform,$video_id]);$liked=(bool)$ls->fetch();
    $ss=$pdo->prepare("SELECT id FROM saved_videos WHERE user_id=? AND platform=? AND video_id=?");$ss->execute([$user['id'],$platform,$video_id]);$is_saved=(bool)$ss->fetch();
}
$autoplay_on=isset($_COOKIE['ns_autoplay'])?$_COOKIE['ns_autoplay']==='1':true;
$user_mem=$user['membership']??'free';

// ── REKLAM SİSTEMİ ────────────────────────────────────────────────
$ad_mode = get_setting('ad_mode','hilltopads');
$ad=null;$has_preroll=false;$has_img_preroll=false;$has_vid_preroll=false;$adDur=15;$adSkip=5;

if(!$is_image && !in_array($user_mem,['premium','ultimate'])){
    $hilltop_target = get_setting('hilltop_target','free');
    $show_ad = match($hilltop_target){
        'free'        => $user_mem==='free',
        'non_premium' => !in_array($user_mem,['premium','ultimate']),
        default       => true,
    };
    if($show_ad){
        if($ad_mode==='hilltopads'){
            $hilltop_on  = get_setting('hilltop_enabled','0')==='1';
            $hilltop_pre = get_setting('hilltop_preroll_enabled','1')==='1';
            if($hilltop_on && $hilltop_pre){
                try{$ad=$pdo->query("SELECT * FROM ads WHERE active=1 AND type='vast' AND vast_code IS NOT NULL AND vast_code!='' ORDER BY RAND() LIMIT 1")->fetch();}catch(Exception $e){$ad=null;}
                if(!$ad){
                    $gv=get_setting('hilltop_vast_url');
                    if($gv) $ad=['id'=>0,'type'=>'vast','vast_code'=>$gv,'video_url'=>'','image_url'=>'','duration'=>30,'skip_after'=>(int)get_setting('hilltop_skip_after','5'),'name'=>'HilltopAds','link'=>''];
                }
                if($ad){$has_preroll=true;$adDur=(int)($ad['duration']??30);$adSkip=(int)($ad['skip_after']??5);}
            }
        } else {
            // Manuel mod
            try{$ad=$pdo->query("SELECT * FROM ads WHERE active=1 ORDER BY RAND() LIMIT 1")->fetch();}catch(Exception $e){$ad=null;}
            if($ad){
                $adDur=(int)($ad['duration']??15);$adSkip=(int)($ad['skip_after']??5);
                if($ad['type']==='vast' && !empty($ad['vast_code']))          { $has_preroll=true; }
                elseif($ad['type']==='video' && !empty($ad['video_url']))     { $has_vid_preroll=true; }
                elseif($ad['type']==='image' && !empty($ad['image_url']))     { $has_img_preroll=true; }
                else { $ad=null; }
            }
        }
    }
}

// ── MADDE 11: İndirme izni ─────────────────────────────────────────
$can_download = false;
if(in_array($platform,['local','wasabi','idrive']) && $direct_url){
    try{
        $plan=$pdo->query("SELECT download_videos FROM membership_plans WHERE slug='".($user_mem)."' LIMIT 1")->fetch();
        $can_download = ($plan && $plan['download_videos']);
    }catch(Exception $e){}
    if($user_mem==='ultimate'||$user_mem==='premium') $can_download=true;
}

// Benzer videolar - aynı kategoriden, yoksa popüler
$similar=[];
try{
    // Önce aynı kategoriden
    $cat_ids=$pdo->prepare("SELECT category_id FROM video_categories WHERE video_id=?");
    $cat_ids->execute([$video_id]);$cat_ids=$cat_ids->fetchAll(PDO::FETCH_COLUMN);
    if($cat_ids){
        $placeholders=implode(',',array_fill(0,count($cat_ids),'?'));
        $params=array_merge($cat_ids,[$video_id]);
        $sq=$pdo->prepare("SELECT DISTINCT uv.*,u.username,ch.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels ch ON ch.user_id=uv.user_id INNER JOIN video_categories vc ON vc.video_id=uv.id WHERE vc.category_id IN ($placeholders) AND uv.status='active' AND uv.id!=? ORDER BY uv.views DESC LIMIT 8");
        $sq->execute($params);
        foreach($sq->fetchAll() as $sv){
            $similar[]=['platform'=>'local','id'=>$sv['id'],'title'=>$sv['title'],'thumbnail'=>$sv['thumbnail']??'','channel'=>$sv['ch_name']?:($sv['username']??''),'duration'=>$sv['duration']??0,'type'=>$sv['type']??'normal','views'=>$sv['views']??0];
        }
    }
    // Yetmezse popüler videolar
    if(count($similar)<6){
        $existing_ids=array_column($similar,'id');
        $existing_ids[]=$video_id;
        $placeholders2=implode(',',array_fill(0,count($existing_ids),'?'));
        $sq2=$pdo->prepare("SELECT uv.*,u.username,ch.name as ch_name FROM uploaded_videos uv LEFT JOIN users u ON u.id=uv.user_id LEFT JOIN channels ch ON ch.user_id=uv.user_id WHERE uv.status='active' AND uv.id NOT IN ($placeholders2) ORDER BY uv.views DESC LIMIT ?");
        $sq2->execute(array_merge($existing_ids,[12-count($similar)]));
        foreach($sq2->fetchAll() as $sv){
            $similar[]=['platform'=>'local','id'=>$sv['id'],'title'=>$sv['title'],'thumbnail'=>$sv['thumbnail']??'','channel'=>$sv['ch_name']?:($sv['username']??''),'duration'=>$sv['duration']??0,'type'=>$sv['type']??'normal','views'=>$sv['views']??0];
        }
    }
}catch(Exception $e){}
$similar=array_slice($similar,0,12);

// Yorumlar
$comments=$pdo->prepare("SELECT c.*,u.username,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.platform=? AND c.video_id=? AND c.status='active' AND c.parent_id IS NULL ORDER BY c.created_at DESC LIMIT 50");$comments->execute([$platform,$video_id]);$comments=$comments->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['comment'])&&$user&&csrf_verify()){$ct=trim($_POST['comment_text']??'');if(strlen($ct)>=2&&strlen($ct)<=1000){$pdo->prepare("INSERT INTO comments(user_id,platform,video_id,content) VALUES(?,?,?,?)")->execute([$user['id'],$platform,$video_id,$ct]);redirect("/watch.php?platform={$platform}&id={$video_id}&type={$v_type}#comments");}}
$playlists=[];if($user){$ps=$pdo->prepare("SELECT id,title FROM playlists WHERE user_id=? ORDER BY created_at DESC");$ps->execute([$user['id']]);$playlists=$ps->fetchAll();}
include __DIR__.'/includes/header.php';
?>
<style>
.wl{display:grid;grid-template-columns:1fr 402px;gap:24px;padding:24px;max-width:1800px;margin:0 auto}
.wpl{border-radius:12px;overflow:hidden;position:relative;margin-bottom:14px;background:#000}
.wab{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;background:var(--bg3);border:none;font-size:14px;font-weight:500;color:var(--text);cursor:pointer;font-family:'Roboto',sans-serif;transition:.15s}
.wab:hover{background:var(--border)}.wab.on{background:var(--text);color:var(--bg)}
.wsbi{display:flex;gap:10px;padding:8px;border-radius:10px;cursor:pointer;transition:.15s}
.wsbi:hover{background:var(--bg3)}
.wsbt{width:168px;height:94px;border-radius:8px;background:var(--bg3);overflow:hidden;flex-shrink:0}
.wsbt img{width:100%;height:100%;object-fit:cover}
@media(max-width:1200px){.wl{grid-template-columns:1fr}.wsb{display:none}}
@media(max-width:600px){.wl{padding:10px;gap:10px}}
#pr-container{position:relative}
#pr-ad-layer{position:absolute;inset:0;z-index:10;background:#000;display:flex;flex-direction:column}
#pr-ad-content{flex:1;position:relative}
#pr-bar-row{padding:10px 14px;background:rgba(0,0,0,.92);display:flex;align-items:center;gap:10px;flex-shrink:0}
#pr-progress{height:3px;flex:1;background:rgba(255,255,255,.2);border-radius:99px;overflow:hidden}
#pr-prog-fill{height:100%;width:0%;background:var(--acc,#ff0000);transition:width .5s linear}
@keyframes vsspin{to{transform:rotate(360deg)}}
</style>

<div class="wl">
  <div>
    <div class="wpl" id="player-box">
      <?php
      $pc_style='position:relative;';
      if($is_short)$pc_style.='max-width:360px;margin:0 auto;aspect-ratio:9/16;';
      elseif(!$is_image)$pc_style.='aspect-ratio:16/9;';
      ?>
      <div id="pr-container" style="<?=$pc_style?>">

        <?php if($has_preroll): ?>
        <!-- VAST PREROLL -->
        <div id="pr-ad-layer">
          <div id="pr-ad-content">
            <video id="vast-vid" style="width:100%;height:100%;object-fit:contain;display:none" playsinline></video>
            <div id="vast-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;flex-direction:column;gap:12px">
              <div style="width:36px;height:36px;border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:vsspin 1s linear infinite"></div>
              <p style="font-size:13px">Reklam yükleniyor...</p>
            </div>
          </div>
          <div id="pr-bar-row">
            <span style="font-size:11px;font-weight:700;background:var(--acc,#ff0000);color:#fff;padding:2px 8px;border-radius:3px;flex-shrink:0">REKLAM</span>
            <div id="pr-progress"><div id="pr-prog-fill"></div></div>
            <button onclick="vastToggleMute()" id="vast-mute-btn" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:13px;flex-shrink:0">🔇</button>
            <span style="font-size:12px;color:rgba(255,255,255,.8);white-space:nowrap;flex-shrink:0"><span id="pr-secs"><?=$adSkip?></span>s</span>
            <button id="pr-skip" onclick="skipAd()" disabled style="background:rgba(0,0,0,.7);color:#fff;border:1px solid rgba(255,255,255,.35);padding:6px 14px;border-radius:4px;font-size:13px;font-weight:600;cursor:not-allowed;opacity:.5;font-family:'Roboto',sans-serif;flex-shrink:0">Reklamı Geç ›</button>
          </div>
        </div>

        <?php elseif($has_vid_preroll): ?>
        <!-- MP4 VİDEO PREROLL -->
        <div id="pr-ad-layer">
          <div style="flex:1;position:relative">
            <video id="vast-vid" src="<?=e($ad['video_url']??'')?>" style="width:100%;height:100%;object-fit:contain" playsinline muted></video>
            <?php if(!empty($ad['link'])):?><a href="<?=e($ad['link']??'#')?>" target="_blank" onclick="if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=click')" style="position:absolute;inset:0;display:block"></a><?php endif?>
          </div>
          <div id="pr-bar-row">
            <span style="font-size:11px;font-weight:700;background:var(--acc,#ff0000);color:#fff;padding:2px 8px;border-radius:3px;flex-shrink:0">REKLAM</span>
            <div id="pr-progress"><div id="pr-prog-fill"></div></div>
            <button onclick="vastToggleMute()" id="vast-mute-btn" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:13px;flex-shrink:0">🔇</button>
            <span style="font-size:12px;color:rgba(255,255,255,.8);white-space:nowrap;flex-shrink:0" id="pr-txt"><span id="pr-secs"><?=$adSkip?></span>s</span>
            <button id="pr-skip" onclick="skipAd()" disabled style="background:rgba(0,0,0,.7);color:#fff;border:1px solid rgba(255,255,255,.35);padding:6px 14px;border-radius:4px;font-size:13px;font-weight:600;cursor:not-allowed;opacity:.5;font-family:'Roboto',sans-serif;flex-shrink:0">Reklamı Geç ›</button>
          </div>
        </div>

        <?php elseif($has_img_preroll): ?>
        <!-- GÖRSEL PREROLL -->
        <div id="pr-ad-layer">
          <div style="flex:1;position:relative">
            <a href="<?=e($ad['link']??'#')?>" target="_blank" onclick="if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=click')" style="display:block;width:100%;height:100%">
              <img src="<?=e($ad['image_url']??'')?>" style="width:100%;height:100%;object-fit:cover">
            </a>
          </div>
          <div id="pr-bar-row">
            <span style="font-size:11px;font-weight:700;background:var(--acc,#ff0000);color:#fff;padding:2px 8px;border-radius:3px;flex-shrink:0">REKLAM</span>
            <div id="pr-progress"><div id="pr-prog-fill"></div></div>
            <span style="font-size:12px;color:rgba(255,255,255,.8);white-space:nowrap;flex-shrink:0" id="pr-txt"><span id="pr-secs"><?=$adSkip?></span>s</span>
            <button id="pr-skip" onclick="skipAd()" disabled style="background:rgba(0,0,0,.7);color:#fff;border:1px solid rgba(255,255,255,.35);padding:6px 14px;border-radius:4px;font-size:13px;font-weight:600;cursor:not-allowed;opacity:.5;font-family:'Roboto',sans-serif;flex-shrink:0">Reklamı Geç ›</button>
          </div>
        </div>
        <?php endif?>

        <!-- ANA PLAYER -->
        <?php
        $ad_hidden = ($has_preroll||$has_img_preroll||$has_vid_preroll) ? 'display:none' : '';
        ?>
        <?php if($direct_url):?>
        <!-- Video.js Player -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.6.1/video-js.min.css" rel="stylesheet">
        <video id="ns-player"
          class="video-js vjs-default-skin vjs-big-play-centered"
          controls playsinline preload="auto"
          style="position:absolute;inset:0;width:100%;height:100%;<?=$ad_hidden?>"
          data-setup='{"fluid":false,"responsive":false}'>
          <source src="<?=e($direct_url)?>" type="video/mp4">
          <p class="vjs-no-js">Video oynatmak için JavaScript gereklidir.</p>
        </video>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.6.1/video.min.js"></script>
        <?php elseif($embed_noa):?>
        <iframe id="main-player"
          src="<?=e(($has_preroll||$has_img_preroll||$has_vid_preroll)?'about:blank':$embed_auto)?>"
          data-src="<?=e($embed_auto)?>"
          style="position:absolute;inset:0;width:100%;height:100%;border:none;<?=$ad_hidden?>"
          allowfullscreen allow="autoplay;fullscreen;picture-in-picture"></iframe>
        <?php elseif($is_image):?>
        <div style="text-align:center;padding:20px;background:#000;min-height:300px;display:flex;align-items:center;justify-content:center">
          <img src="<?=e($thumbnail)?>" style="max-width:100%;max-height:80vh;object-fit:contain">
        </div>
        <?php else:?>
        <div style="aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:var(--bg2)"><p style="color:var(--text3)">Oynatıcı yüklenemedi</p></div>
        <?php endif?>
      </div>
    </div>

    <h1 style="font-size:18px;font-weight:600;line-height:1.4;margin-bottom:10px"><?=e($title)?></h1>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:10px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin:8px 0">
      <!-- Kanal/Yükleyen bilgisi -->
      <div style="display:flex;align-items:center;gap:12px">
        <?php if($uploader_id && in_array($platform,['local','wasabi','idrive'])): ?>
        <a href="/channel.php?user=<?=$uploader_id?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text)">
          <?php if($uploader_avatar && $uploader_avatar!=='default.png'): ?>
          <img src="<?=avatar_url($uploader_avatar)?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
          <?php else: ?>
          <div style="width:40px;height:40px;border-radius:50%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0"><?=strtoupper(substr($uploader_name?:' ',0,1))?></div>
          <?php endif?>
          <div><p style="font-size:14px;font-weight:600"><?=e($uploader_name)?></p><p style="font-size:12px;color:var(--text2)">Kanal</p></div>
        </a>
        <?php else: ?>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:40px;height:40px;border-radius:50%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0"><?=strtoupper(substr($channel?:$platform,0,1))?></div>
          <div><p style="font-size:14px;font-weight:600"><?=e($channel?:ucfirst($platform))?></p><p style="font-size:12px;color:var(--text2)"><?=e(ucfirst($platform))?></p></div>
        </div>
        <?php endif?>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button id="like-btn" onclick="dLike()" class="wab <?=$liked?'on':''?>">
          <svg width="18" height="18" fill="<?=$liked?'currentColor':'none'?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
          <span id="lc"><?=$like_count?></span>
        </button>
        <button id="save-btn" onclick="dSave()" class="wab <?=$is_saved?'on':''?>">
          <svg id="save-svg" width="18" height="18" fill="<?=$is_saved?'currentColor':'none'?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
          <span id="save-txt"><?=$is_saved?'Kaydedildi':'Kaydet'?></span>
        </button>
        <?php if($can_download): ?>
        <a href="<?=e($direct_url)?>" download class="wab">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          İndir
        </a>
        <?php endif?>
        <div class="dd" id="dds">
          <button onclick="toggleDD('dds')" class="wab">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            Paylaş
          </button>
          <div class="ddm">
            <button class="ddi" onclick="sOn('whatsapp')">💬 WhatsApp</button>
            <button class="ddi" onclick="sOn('twitter')">🐦 Twitter</button>
            <button class="ddi" onclick="sOn('facebook')">📘 Facebook</button>
            <button class="ddi" onclick="cLink()">📋 Linki Kopyala</button>
            <?php if(!$is_image&&!$is_short):?><div class="dds"></div><button class="ddi" onclick="cEmbed()">💻 Embed Kodu</button><?php endif?>
          </div>
        </div>
        <?php if($user&&$playlists&&!$is_image):?>
        <button onclick="document.getElementById('pl-modal').style.display='flex'" class="wab">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
        <?php endif?>
      </div>
    </div>

    <div id="comments" style="margin-top:20px">
      <p style="font-size:16px;font-weight:600;margin-bottom:14px"><?=count($comments)?> Yorum</p>
      <?php if($user):?>
      <form method="POST" style="display:flex;gap:12px;margin-bottom:20px"><?=csrf_field()?>
        <img src="<?=avatar_url($user['avatar'])?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
        <div style="flex:1">
          <input type="text" name="comment_text" placeholder="Yorum ekle..." maxlength="1000" style="width:100%;background:transparent;border:none;border-bottom:1px solid var(--border);color:var(--text);font-size:14px;padding:8px 0;outline:none;font-family:'Roboto',sans-serif">
          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px"><button type="submit" name="comment" class="nsbtn sm" style="border-radius:20px">Yorum Yap</button></div>
        </div>
      </form>
      <?php else:?><p style="color:var(--text2);margin-bottom:16px"><a href="/login.php" style="color:var(--acc,#ff0000)">Giriş yapın</a></p><?php endif?>
      <?php foreach($comments as $cm):?>
      <div style="display:flex;gap:12px;margin-bottom:16px">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--bg3);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;overflow:hidden">
          <?php if($cm['avatar']&&$cm['avatar']!=='default.png'):?><img src="<?=avatar_url($cm['avatar'])?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><?=strtoupper(substr($cm['username'],0,1))?><?php endif?>
        </div>
        <div>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:3px">
            <span style="font-size:13px;font-weight:500">@<?=e($cm['username'])?></span>
            <span style="font-size:11px;color:var(--text3)"><?=time_ago($cm['created_at'])?></span>
          </div>
          <p style="font-size:14px;line-height:1.5"><?=nl2br(e($cm['content']))?></p>
        </div>
      </div>
      <?php endforeach?>
    </div>
  </div>

  <!-- SAĞ: Benzer videolar -->
  <div class="wsb">
    <?php if(!$is_image&&count($similar)>0):?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <p style="font-size:14px;font-weight:600">Sıradaki</p>
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);cursor:pointer">Oto<input type="checkbox" id="ap-cb-sb" <?=$autoplay_on?'checked':''?> style="accent-color:var(--acc,#ff0000)" onchange="sAP(this.checked)"></label>
    </div>
    <?php endif?>
    <div style="display:flex;flex-direction:column;gap:4px">
      <?php foreach($similar as $sv):?>
      <a href="/watch.php?platform=<?=urlencode($sv['platform'])?>&id=<?=urlencode($sv['id'])?>&type=<?=urlencode($sv['type']??'normal')?>" class="wsbi">
        <div class="wsbt"><?php if($sv['thumbnail']):?><img src="<?=e($sv['thumbnail'])?>" loading="lazy"><?php endif?></div>
        <div style="flex:1;min-width:0">
          <p style="font-size:13px;font-weight:500;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:3px"><?=e($sv['title'])?></p>
          <p style="font-size:12px;color:var(--text2)"><?=e($sv['channel']??'')?></p>
          <?php if(($sv['duration']??0)>0):?><p style="font-size:11px;color:var(--text3)"><?=format_duration($sv['duration'])?></p><?php endif?>
        </div>
      </a>
      <?php endforeach?>
    </div>
  </div>
</div>

<?php if($user&&$playlists):?>
<div id="pl-modal" style="display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,.65);align-items:center;justify-content:center">
  <div class="nscard" style="width:360px;padding:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <p style="font-size:15px;font-weight:600">Listeye Kaydet</p>
      <button onclick="document.getElementById('pl-modal').style.display='none'" style="background:none;border:none;color:var(--text2);cursor:pointer;font-size:20px;line-height:1">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach($playlists as $pl):?>
      <button onclick="aToPL(<?=$pl['id']?>)" style="text-align:left;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:none;color:var(--text);cursor:pointer;font-size:14px;font-family:'Roboto',sans-serif;transition:.15s" onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='none'">📋 <?=e($pl['title'])?></button>
      <?php endforeach?>
    </div>
  </div>
</div>
<?php endif?>

<script>
var WP='<?=e($platform)?>',WID='<?=e($video_id)?>',WT='<?=e(addslashes($title))?>',WTH='<?=e($thumbnail)?>',WC='<?=e(addslashes($channel))?>',BU='<?=$origin?>';
var HAS_PREROLL=<?=($has_preroll||$has_img_preroll||$has_vid_preroll)?'true':'false'?>;
var IS_VID_PREROLL=<?=$has_vid_preroll?'true':'false'?>;
var IS_VAST=<?=$has_preroll?'true':'false'?>;
var VAST_URL=<?=$has_preroll?json_encode($ad['vast_code']??''):'""'?>;
var VAST_SKIP=<?=$adSkip?>;
var VAST_DUR=<?=$adDur?>;
var AD_ID=<?=$ad?($ad['id']??0):0?>;
var EMBED_AUTO=<?=json_encode($embed_auto)?>;

// ── PREROLL ORTAK SKIP ────────────────────────────────────────────
var _prDone=false,_prTimer=null;

function _updateSkipUI(elapsed,dur,skipAt){
    var fill=document.getElementById('pr-prog-fill');
    var secs=document.getElementById('pr-secs');
    var skip=document.getElementById('pr-skip');
    var pct=dur>0?Math.min(100,(elapsed/dur)*100):0;
    if(fill) fill.style.width=pct+'%';
    var rem=Math.max(0,Math.ceil(skipAt-elapsed));
    if(secs) secs.textContent=rem>0?rem+'s':'';
    if(skip){
        if(rem<=0&&skip.disabled){
            skip.disabled=false;
            skip.style.cursor='pointer';
            skip.style.opacity='1';
            skip.style.background='rgba(0,0,0,.85)';
        }
    }
    if(elapsed>=dur) skipAd();
}

function _prTimerStart(){
    // setInterval bazlı timer (görsel veya başarısız video için)
    var el=0;
    _prTimer=setInterval(function(){
        if(_prDone){clearInterval(_prTimer);return;}
        el++;
        _updateSkipUI(el,VAST_DUR,VAST_SKIP);
    },1000);
}

window.skipAd=function(){
    if(_prDone)return;_prDone=true;
    clearInterval(_prTimer);
    var vid=document.getElementById('vast-vid');
    if(vid){vid.pause();}
    var layer=document.getElementById('pr-ad-layer');
    if(layer){layer.style.opacity='0';layer.style.transition='opacity .25s';setTimeout(function(){if(layer.parentNode)layer.parentNode.removeChild(layer);},280);}
    var mp=document.getElementById('main-player');
    var nsv=document.getElementById('ns-player');
    if(mp){mp.style.display='block';mp.src=EMBED_AUTO;}
    if(nsv){
        nsv.style.display='block';
        // video.js zaten init edildi, sadece play et
        if(typeof videojs!=='undefined'){
            var vp=videojs('ns-player');
            if(vp){vp.ready(function(){this.play();});}
        } else {
            nsv.play();
        }
    }
};

// ── VAST PLAYER ───────────────────────────────────────────────────
if(IS_VAST && VAST_URL){
    var _vastMuted=true;
    function loadVast(){
        fetch(VAST_URL,{mode:'cors',cache:'no-store'})
        .then(function(r){return r.text();})
        .then(function(xml){
            var doc=new DOMParser().parseFromString(xml,'text/xml');
            doc.querySelectorAll('Impression').forEach(function(i){var u=i.textContent.trim();if(u)new Image().src=u;});
            var mp4='';
            doc.querySelectorAll('MediaFile').forEach(function(mf){
                var t=mf.getAttribute('type')||'';
                if(!mp4&&(t.includes('mp4')||t.includes('video'))) mp4=mf.textContent.trim();
            });
            var click='';var ct=doc.querySelector('ClickThrough');if(ct)click=ct.textContent.trim();
            if(mp4){playVast(mp4,click);}else{skipAd();}
        }).catch(function(){skipAd();});
    }
    function playVast(url,click){
        var vid=document.getElementById('vast-vid');
        var loading=document.getElementById('vast-loading');
        if(!vid){skipAd();return;}
        if(loading)loading.style.display='none';
        vid.style.display='block';
        vid.muted=true;_vastMuted=true;
        vid.src=url;vid.load();
        vid.play().then(function(){
            setTimeout(function(){vid.muted=false;_vastMuted=false;var mb=document.getElementById('vast-mute-btn');if(mb)mb.textContent='🔊';},600);
        }).catch(function(){_prTimerStart();});
        if(click){vid.style.cursor='pointer';vid.onclick=function(){window.open(click,'_blank');if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=click');};}
        if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=impression');
        vid.addEventListener('loadedmetadata',function(){VAST_DUR=Math.max(1,vid.duration)||VAST_DUR;});
        // timeupdate: skip butonunu video süresine göre güncelle
        vid.addEventListener('timeupdate',function(){
            if(!_prDone) _updateSkipUI(vid.currentTime,VAST_DUR,VAST_SKIP);
        });
        vid.addEventListener('ended',function(){skipAd();});
        vid.addEventListener('error',function(){skipAd();});
    }
    window.vastToggleMute=function(){
        _vastMuted=!_vastMuted;
        var vid=document.getElementById('vast-vid');if(vid)vid.muted=_vastMuted;
        var mb=document.getElementById('vast-mute-btn');if(mb)mb.textContent=_vastMuted?'🔇':'🔊';
    };
    document.addEventListener('DOMContentLoaded',function(){setTimeout(loadVast,200);});

}else if(IS_VID_PREROLL){
    // MP4 Video preroll
    var _vvMuted=true;
    document.addEventListener('DOMContentLoaded',function(){
        var vid=document.getElementById('vast-vid');
        if(!vid){return;}
        if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=impression');
        vid.muted=true;
        vid.play().then(function(){
            setTimeout(function(){vid.muted=false;_vvMuted=false;var mb=document.getElementById('vast-mute-btn');if(mb)mb.textContent='🔊';},600);
        }).catch(function(){_prTimerStart();});
        vid.addEventListener('loadedmetadata',function(){VAST_DUR=Math.max(1,vid.duration)||VAST_DUR;});
        vid.addEventListener('timeupdate',function(){if(!_prDone)_updateSkipUI(vid.currentTime,VAST_DUR,VAST_SKIP);});
        vid.addEventListener('ended',function(){skipAd();});
        vid.addEventListener('error',function(){_prTimerStart();});
    });
    window.vastToggleMute=function(){
        _vvMuted=!_vvMuted;
        var vid=document.getElementById('vast-vid');if(vid)vid.muted=_vvMuted;
        var mb=document.getElementById('vast-mute-btn');if(mb)mb.textContent=_vvMuted?'🔇':'🔊';
    };

}else if(HAS_PREROLL){
    // Görsel preroll - timer başlat
    if(AD_ID>0)fetch('/ajax/track_ad.php?id='+AD_ID+'&type=impression');
    document.addEventListener('DOMContentLoaded',function(){_prTimerStart();});
    window.vastToggleMute=function(){};
}else{
    window.vastToggleMute=function(){};
}

// ── VİDEO İŞLEMLERİ ─────────────────────────────────────────────
function dLike(){if(!NS_USER)return showToast('Giriş yapın');fetch('/ajax/like.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({platform:WP,id:WID,csrf_token:NS_CSRF})}).then(function(r){return r.json()}).then(function(d){var b=document.getElementById('like-btn'),s=b.querySelector('svg');if(d.liked){b.classList.add('on');s.setAttribute('fill','currentColor');}else{b.classList.remove('on');s.setAttribute('fill','none');}document.getElementById('lc').textContent=d.count;showToast(d.message);});}
function dSave(){
  if(!NS_USER)return showToast('Giriş yapın');
  fetch('/ajax/save_video.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({platform:WP,id:WID,title:WT,thumbnail:WTH,channel:WC,csrf_token:NS_CSRF})})
  .then(function(r){return r.json()}).then(function(d){
    var b=document.getElementById('save-btn');
    if(b){if(d.saved){b.classList.add('on');b.style.background='var(--text)';b.style.color='var(--bg)';b.querySelector('svg').setAttribute('fill','currentColor');b.querySelector('span').textContent='Kaydedildi';}else{b.classList.remove('on');b.style.background='';b.style.color='';b.querySelector('svg').setAttribute('fill','none');b.querySelector('span').textContent='Kaydet';}}
    showToast(d.saved?'✅ Kaydedildi':'🗑 Kayıt kaldırıldı');
  });
}
function aToPL(pid){fetch('/ajax/playlist_action.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',playlist_id:pid,platform:WP,video_id:WID,title:WT,csrf_token:NS_CSRF})}).then(function(r){return r.json()}).then(function(d){document.getElementById('pl-modal').style.display='none';showToast(d.message||(d.success?'Eklendi':'Hata'));});}
function sOn(t){var u=encodeURIComponent(location.href),n=encodeURIComponent(WT);var m={whatsapp:'https://wa.me/?text='+n+'%20'+u,twitter:'https://twitter.com/intent/tweet?text='+n+'&url='+u,facebook:'https://www.facebook.com/sharer/sharer.php?u='+u};window.open(m[t],'_blank','width=600,height=400');}
function cLink(){navigator.clipboard.writeText(location.href).then(function(){showToast('Kopyalandı!');});}
function cEmbed(){navigator.clipboard.writeText('<iframe src="'+BU+'/embed.php?platform='+WP+'&id='+WID+'" width="560" height="315" frameborder="0" allowfullscreen></iframe>').then(function(){showToast('Embed kopyalandı!');});}
function sAP(on){document.cookie='ns_autoplay='+(on?'1':'0')+';path=/;max-age=31536000';}
var plyrI=null;
<?php if($direct_url&&!($has_preroll||$has_img_preroll)):?>
if(typeof Plyr!=='undefined')plyrI=new Plyr('#ns-plyr',{controls:['play-large','rewind','play','fast-forward','progress','current-time','duration','mute','volume','settings','fullscreen'],settings:['quality','speed'],speed:{selected:1,options:[0.5,0.75,1,1.25,1.5,2]}});
<?php endif?>
<?php if(!empty($similar)&&$platform==='youtube'):?>
var _yt=document.createElement('script');_yt.src='https://www.youtube.com/iframe_api';document.head.appendChild(_yt);
window.onYouTubeIframeAPIReady=function(){var f=document.getElementById('main-player');if(!f)return;new YT.Player(f,{events:{onStateChange:function(e){if(e.data===0&&<?=$autoplay_on?'true':'false'?>)setTimeout(function(){location.href='/watch.php?platform=<?=urlencode($similar[0]['platform'])?>&id=<?=urlencode($similar[0]['id'])?>&type=<?=urlencode($similar[0]['type']??'normal')?>';},1500);}}});};
<?php endif?>
</script>
<?php include __DIR__.'/includes/footer.php';?>
