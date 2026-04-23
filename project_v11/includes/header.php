<?php
if(!defined('NS_LOADED')){require_once __DIR__.'/db.php';require_once __DIR__.'/functions.php';require_once __DIR__.'/auth.php';start_session();}
$_user      = get_user();
$_lang_code = $_COOKIE['ns_lang'] ?? $_user['lang'] ?? 'tr';
$_theme     = get_active_theme();
$_site_title= get_setting('site_title','NakreosStream');
$_flash     = get_flash();
$_q         = e($_GET['q'] ?? '');
$_platform  = e($_GET['platform'] ?? 'all');
$_type      = e($_GET['type'] ?? 'all');
$_notif_count=0;
if($_user){
    try{
        $ns=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $ns->execute([$_user['id']]);$_notif_count=(int)$ns->fetchColumn();
        // Duyurular sayısı da ekle
        $as=$pdo->prepare("SELECT COUNT(*) FROM announcements a WHERE a.active=1 AND (a.expires_at IS NULL OR a.expires_at>NOW()) AND (a.target='all' OR a.target=?) AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id=?)");
        $as->execute([$_user['membership']??'free',$_user['id']]);$_notif_count+=(int)$as->fetchColumn();
    }catch(Exception $e){}
}
$_theme_vars = get_theme_vars($_theme);
$_lfile=__DIR__.'/../languages/'.$_lang_code.'.php';
$lang=file_exists($_lfile)?include $_lfile:include __DIR__.'/../languages/tr.php';
$_langs=['tr'=>['🇹🇷','Türkçe'],'en'=>['🇺🇸','English'],'de'=>['🇩🇪','Deutsch'],'az'=>['🇦🇿','Azərbaycan'],'es'=>['🇪🇸','Español'],'ru'=>['🇷🇺','Русский'],'zh'=>['🇨🇳','中文'],'ar'=>['🇸🇦','العربية']];
// Temaları DB'den oku, yoksa varsayılan kullan
$_themes = [];
try {
    $th_st = $pdo->query("SELECT slug,name,icon,active FROM themes WHERE active=1 ORDER BY sort_order");
    foreach ($th_st->fetchAll() as $th) {
        $_themes[$th['slug']] = [$th['icon'], $th['name']];
    }
} catch(Exception $e) {}
if (empty($_themes)) {
    $_themes = ['dark'=>['🌙','Koyu'],'light'=>['☀️','Açık'],'netflix'=>['🎬','Netflix'],'twitch'=>['🎮','Twitch'],'spotify'=>['🎵','Spotify'],'cinema'=>['🎥','Cinema'],'minimal'=>['✨','Minimal']];
}
$_dir=$_lang_code==='ar'?'rtl':'ltr';
$_cats=$pdo->query("SELECT * FROM categories WHERE active=1 ORDER BY sort_order LIMIT 8")->fetchAll();
$_plats = []; // Platformlar kaldırıldı
$_curpage=basename($_SERVER['PHP_SELF'],'.php');
$_mem=$_user['membership']??'free';
?>
<!DOCTYPE html>
<html lang="<?=$_lang_code?>" dir="<?=$_dir?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=isset($page_title)?e($page_title).' - '.$_site_title:$_site_title?></title>
<meta name="description" content="<?=e(get_setting('site_description'))?>">
<link rel="manifest" href="/manifest.json">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
<?= $_theme_vars ?>
<?php
$theme_css_map = ['cinema'=>'/assets/themes/cinema.css','minimal'=>'/assets/themes/minimal.css'];
if(isset($theme_css_map[$_theme])):?>
<link rel="stylesheet" href="<?=$theme_css_map[$_theme]?>?v=7">
<?php endif?>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Roboto',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;font-size:14px}
a{color:inherit;text-decoration:none}button{cursor:pointer;font-family:'Roboto',sans-serif}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
/* TOPBAR */
#tb{position:fixed;top:0;left:0;right:0;height:56px;background:var(--nav);z-index:100;display:flex;align-items:center;padding:0 16px;gap:8px;border-bottom:1px solid var(--border)}
.tb-logo{display:flex;align-items:center;gap:8px;width:220px;flex-shrink:0;text-decoration:none;color:var(--text)}
.tb-logo svg{flex-shrink:0}
.tb-logo-txt{font-size:18px;font-weight:700;letter-spacing:-.5px}
/* Arama */
.tb-sw{flex:1;max-width:700px;margin:0 auto;display:flex;align-items:center;gap:6px}
.tb-sf{flex:1;display:flex}
.tb-sf input{flex:1;height:40px;background:var(--inp);border:1px solid var(--border);border-right:none;border-radius:40px 0 0 40px;padding:0 16px;font-size:15px;color:var(--text);outline:none;transition:border-color .2s}
.tb-sf input:focus{border-color:var(--acc);background:var(--bg)}
.tb-sf input::placeholder{color:var(--text3)}
.tb-sb{height:40px;width:56px;background:var(--bg3);border:1px solid var(--border);border-radius:0 40px 40px 0;color:var(--text2);display:flex;align-items:center;justify-content:center;transition:.2s}
.tb-sb:hover{background:var(--border);color:var(--text)}
.vb{width:40px;height:40px;border-radius:50%;background:var(--bg3);border:1px solid var(--border);color:var(--text2);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.2s}
.vb:hover{background:var(--border);color:var(--text)}
/* Sağ */
.tb-r{margin-left:auto;display:flex;align-items:center;gap:4px;flex-shrink:0}
.ib{width:40px;height:40px;border-radius:50%;background:none;border:none;color:var(--text2);display:flex;align-items:center;justify-content:center;position:relative;transition:.2s;flex-shrink:0}
.ib:hover{background:var(--hov);color:var(--text)}
.nb{position:absolute;top:2px;right:2px;background:var(--acc);color:#fff;font-size:10px;font-weight:700;border-radius:99px;padding:1px 4px;min-width:16px;text-align:center;line-height:1.4}
.sign-btn{display:flex;align-items:center;gap:6px;background:transparent;border:1px solid var(--border);color:#3ea6ff;padding:7px 14px;border-radius:20px;font-size:14px;font-weight:500;transition:.2s;white-space:nowrap}
.sign-btn:hover{background:rgba(62,166,255,.1)}
.ua-btn{display:flex;align-items:center;gap:8px;background:none;border:none;color:var(--text);padding:4px 8px;border-radius:4px;transition:.2s}
.ua-btn:hover{background:var(--hov)}
.uav{width:32px;height:32px;border-radius:50%;object-fit:cover}
.mem-badge{font-size:10px;padding:1px 7px;border-radius:99px;font-weight:700;color:#fff}
.mem-premium{background:linear-gradient(135deg,#1a73e8,#0d47a1)}
.mem-ultimate{background:linear-gradient(135deg,#7c3aed,#db2777)}
/* DROPDOWN */
.dd{position:relative}
.ddm{position:absolute;top:calc(100%+8px);right:0;background:var(--bg2);border:1px solid var(--border);border-radius:12px;min-width:180px;padding:6px;box-shadow:0 8px 40px var(--shadow);z-index:300;display:none;animation:ddin .15s ease}
.dd.open .ddm{display:block}
@keyframes ddin{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.ddi{display:flex;align-items:center;gap:12px;padding:9px 14px;border-radius:8px;font-size:13px;color:var(--text);border:none;background:none;width:100%;text-align:left;transition:.1s;font-family:'Roboto',sans-serif;cursor:pointer}
.ddi:hover{background:var(--hov)}
.dds{height:1px;background:var(--border);margin:4px 0}
/* SIDEBAR */
#sb{position:fixed;top:56px;left:0;bottom:0;width:240px;background:var(--nav);overflow-y:auto;overflow-x:hidden;z-index:90;padding:8px 0;border-right:1px solid var(--border);transition:width .2s}
#sb.col{width:72px}
#sb::-webkit-scrollbar{width:0}
.ssec{margin-bottom:4px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.ssec:last-child{border-bottom:none}
.slbl{font-size:12px;font-weight:600;color:var(--text3);padding:12px 24px 6px;white-space:nowrap;text-transform:uppercase;letter-spacing:.5px}
#sb.col .slbl{display:none}
.ni{display:flex;align-items:center;gap:20px;padding:10px 24px;color:var(--text2);font-size:13px;border:none;background:none;width:100%;text-align:left;transition:.1s;white-space:nowrap;font-family:'Roboto',sans-serif;cursor:pointer}
.ni:hover{background:var(--hov);color:var(--text)}.ni.on{background:var(--hov);color:var(--text);font-weight:500}
.ni svg{flex-shrink:0;width:20px;height:20px}
#sb.col .ni{padding:12px;justify-content:center;gap:0;margin:2px 8px;border-radius:10px;width:auto}
#sb.col .ni span{display:none}
.scat{display:flex;align-items:center;gap:14px;padding:8px 24px;font-size:13px;color:var(--text2);transition:.1s;white-space:nowrap;text-decoration:none}
.scat:hover{background:var(--hov);color:var(--text)}
#sb.col .scat{display:none}
.plat-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
/* MAIN */
#main{margin-top:56px;margin-left:240px;transition:margin-left .2s;min-height:calc(100vh - 56px)}
#main.exp{margin-left:72px}
/* CHIPS */
.chips{display:flex;gap:8px;overflow-x:auto;padding:12px 24px;background:var(--nav);position:sticky;top:56px;z-index:50;border-bottom:1px solid var(--border)}
.chips::-webkit-scrollbar{height:0}
.chip{padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;white-space:nowrap;border:none;background:var(--bg3);color:var(--text);transition:.15s;flex-shrink:0;cursor:pointer}
.chip:hover{background:var(--border)}.chip.on{background:var(--text);color:var(--bg)}
/* VIDEO GRID */
.vg{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.sg{display:grid;gap:10px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}.short-section-scroll{display:flex;gap:10px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;-ms-overflow-style:none}.short-section-scroll::-webkit-scrollbar{display:none}.short-section-scroll .vc{flex-shrink:0;width:calc((100vw - 240px - 48px) / 5 - 10px)}@media(max-width:1400px){.short-section-scroll .vc{width:calc((100vw - 240px - 48px) / 4 - 10px)}}@media(max-width:1024px){.short-section-scroll .vc{width:calc((100vw - 48px) / 4 - 10px)}}@media(max-width:768px){.short-section-scroll .vc{width:calc((100vw - 32px) / 3 - 10px)}}@media(max-width:480px){.short-section-scroll .vc{width:calc((100vw - 24px) / 2 - 10px)}}.shorts-nav-btn{position:absolute;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:50%;background:var(--bg2);border:1px solid var(--border);color:var(--text);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px var(--shadow);z-index:2}.short-section .sg{display:flex;gap:10px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none;-ms-overflow-style:none}.short-section .sg::-webkit-scrollbar{display:none}.short-section .sg .vc{flex-shrink:0;width:calc((100vw - 240px - 48px) / 5 - 10px)}@media(max-width:1400px){.short-section .sg .vc{width:calc((100vw - 240px - 48px) / 4 - 10px)}}@media(max-width:1024px){.short-section .sg .vc{width:calc((100vw - 48px) / 4 - 10px)}}@media(max-width:768px){.short-section .sg .vc{width:calc((100vw - 32px) / 3 - 10px)}}@media(max-width:480px){.short-section .sg .vc{width:calc((100vw - 24px) / 2 - 10px)}}
/* VIDEO CARD */
.vc{background:transparent;cursor:pointer;display:flex;flex-direction:column;transition:transform .15s}
.vc:hover{transform:translateY(-2px)}
.vct{position:relative;overflow:hidden;background:var(--bg3);border-radius:12px;aspect-ratio:16/9}
.vct.st{aspect-ratio:9/16;border-radius:10px}
.vct img{width:100%;height:100%;object-fit:cover;transition:transform .3s;display:block}
.vc:hover .vct img{transform:scale(1.03)}
.vct-dur{position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,.85);color:#fff;font-size:12px;font-weight:500;padding:2px 6px;border-radius:4px}
.vct-pl{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.8);color:#fff;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;text-transform:capitalize;backdrop-filter:blur(4px)}
.vct-sh{position:absolute;top:6px;right:6px;background:var(--acc);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px}
.vct-hov{position:absolute;inset:0;background:rgba(0,0,0,.2);opacity:0;transition:.2s;display:flex;align-items:center;justify-content:center}
.vc:hover .vct-hov{opacity:1}
.vci{padding:10px 4px 4px;display:flex;gap:10px}
.vci-av{width:36px;height:36px;border-radius:50%;background:var(--bg3);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--text2);overflow:hidden}
.vci-av img{width:100%;height:100%;object-fit:cover}
.vci-d{flex:1;min-width:0}
.vci-t{font-size:14px;font-weight:500;line-height:1.4;color:var(--text);margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.vci-c{font-size:13px;color:var(--text2);margin-bottom:2px}
.vci-m{font-size:12px;color:var(--text2)}
.vc-mbtn{background:none;border:none;color:var(--text3);width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;opacity:0;transition:.15s;flex-shrink:0;align-self:flex-start;margin-top:4px}
.vc:hover .vc-mbtn{opacity:1}.vc-mbtn:hover{background:var(--hov);color:var(--text)}
/* MISC */
.nscard{background:var(--bg2);border:1px solid var(--border);border-radius:12px}
.nsinput{background:var(--inp);border:1px solid var(--border);border-radius:8px;padding:10px 14px;color:var(--text);font-size:14px;width:100%;outline:none;transition:border-color .2s;font-family:'Roboto',sans-serif}
.nsinput:focus{border-color:var(--acc)}.nsinput::placeholder{color:var(--text3)}
select.nsinput option{background:var(--bg2)}
.nsbtn{background:var(--acc);color:#fff;border:none;border-radius:20px;padding:10px 18px;font-size:14px;font-weight:500;display:inline-flex;align-items:center;gap:6px;transition:.15s;font-family:'Roboto',sans-serif;white-space:nowrap;cursor:pointer}
.nsbtn:hover{background:var(--acc2)}.nsbtn.ghost{background:transparent;border:1px solid var(--border);color:var(--text)}.nsbtn.ghost:hover{background:var(--hov)}.nsbtn.sm{padding:7px 14px;font-size:13px}
.sec-title{font-size:16px;font-weight:600;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.pg{padding:24px;max-width:1800px}
.ns-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(12px);background:var(--bg2);border:1px solid var(--border);color:var(--text);padding:12px 20px;border-radius:8px;font-size:13px;box-shadow:0 4px 20px var(--shadow);z-index:9999;opacity:0;transition:.25s;pointer-events:none}
.ns-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.short-section{margin-bottom:32px}
.short-section-title{font-size:16px;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;padding:0 4px}
/* PLATFORM BADGES */
.plat-youtube{background:#ff0000}.plat-twitch{background:#9147ff}.plat-vimeo{background:#1ab7ea}.plat-dailymotion{background:#0066dc}.plat-spotify{background:#1db954}.plat-local{background:#22c55e}
/* MOBİL */
.mob-ov{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:89;display:none}
.mob-ov.show{display:block}
@media(max-width:1024px){#sb{transform:translateX(-100%);transition:transform .25s,width .2s;width:240px!important}#sb.mob-open{transform:translateX(0)}#main{margin-left:0!important}#desktop-toggle{display:none}.mob-btn{display:flex!important}}
@media(max-width:768px){.vg{grid-template-columns:repeat(2,1fr);gap:10px}.sg{grid-template-columns:repeat(2,1fr);gap:8px}.pg{padding:12px 14px}.chips{padding:8px 12px}.tb-logo{width:auto}}
@media(max-width:480px){.tb-sf input{font-size:13px}.tb-logo-txt{display:none}}
[dir="rtl"] #sb{left:auto;right:0;border-right:none;border-left:1px solid var(--border)}
[dir="rtl"] #main{margin-left:0;margin-right:240px}[dir="rtl"] #main.exp{margin-right:72px}
</style>
</head>
<body>
<header id="tb">
  <button class="ib mob-btn" onclick="openMob()" style="display:none;flex-shrink:0">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <button class="ib" id="desktop-toggle" onclick="toggleDeskSB()" style="flex-shrink:0">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>

  <a href="/" class="tb-logo">
    <?php if(get_setting('site_logo')): ?>
    <img src="<?=e(get_setting('site_logo'))?>" style="height:28px" alt="">
    <?php else: ?>
    <svg width="28" height="20" viewBox="0 0 90 64" fill="var(--acc)"><path d="M88.1 9.9C87 5.9 83.9 2.8 79.9 1.7 73 0 45 0 45 0S17 0 10.1 1.7C6.1 2.8 3 5.9 1.9 9.9 0 16.8 0 32 0 32s0 15.2 1.9 22.1C3 58.1 6.1 61.2 10.1 62.3 17 64 45 64 45 64s28 0 34.9-1.7c4-1.1 7.1-4.2 8.2-8.2C90 47.2 90 32 90 32s0-15.2-1.9-22.1z"/><path d="M36 45.6l23.3-13.6L36 18.4v27.2z" fill="<?=$_theme==='light'?'#fff':'white'?>"/></svg>
    <?php endif ?>
    <span class="tb-logo-txt"><?=e($_site_title)?>
      <?php if($_mem==='premium'): ?><span class="mem-badge mem-premium" style="font-size:9px;vertical-align:middle;margin-left:3px">P</span>
      <?php elseif($_mem==='ultimate'): ?><span class="mem-badge mem-ultimate" style="font-size:9px;vertical-align:middle;margin-left:3px">U</span><?php endif?>
    </span>
  </a>

  <!-- ARAMA + SES -->
  <div class="tb-sw">
    <form method="GET" action="/" id="sf" class="tb-sf">
      <input type="text" name="q" id="sq" value="<?=$_q?>" placeholder="Ara...">
      <button type="submit" class="tb-sb">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </button>
    </form>
    <button class="vb" onclick="startVoice()" title="Sesli Ara">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2H3v2a9 9 0 0 0 8 8.94V23h2v-2.06A9 9 0 0 0 21 12v-2z"/></svg>
    </button>
  </div>

  <div class="tb-r">
    <?php if($_user): ?>
    <a href="/upload.php" class="ib" title="Video Yükle">
      <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
    </a>
    <!-- Bildirimler -->
    <div class="dd" id="dd-notif">
      <button class="ib" onclick="toggleDD('dd-notif');markNotifsRead()">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <?php if($_notif_count>0): ?><span class="nb"><?=$_notif_count?></span><?php endif?>
      </button>
      <div class="ddm" style="width:320px;max-height:420px;overflow-y:auto">
        <div style="padding:12px 14px 8px;font-size:14px;font-weight:600;border-bottom:1px solid var(--border);margin-bottom:4px">🔔 Bildirimler</div>
        <?php
        try {
            // Duyurular
            $anns=$pdo->prepare("SELECT a.* FROM announcements a WHERE a.active=1 AND (a.expires_at IS NULL OR a.expires_at>NOW()) AND (a.target='all' OR a.target=?) AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id=?) ORDER BY a.created_at DESC LIMIT 5");
            $anns->execute([$_user['membership']??'free',$_user['id']]);$anns=$anns->fetchAll();
            foreach($anns as $ann):?>
            <div class="ddi" style="flex-direction:column;align-items:flex-start;gap:2px;background:rgba(var(--acc-rgb,255,0,0),.05)">
              <div style="display:flex;align-items:center;gap:6px;width:100%">
                <span style="font-size:16px"><?=e($ann['icon']??'📢')?></span>
                <p style="font-size:13px;font-weight:600;flex:1"><?=e($ann['title'])?></p>
              </div>
              <p style="font-size:12px;color:var(--text2)"><?=e(substr($ann['message'],0,80))?></p>
              <p style="font-size:11px;color:var(--text3)"><?=time_ago($ann['created_at'])?></p>
            </div>
            <?php endforeach;
            // Normal bildirimler
            $notifs=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8");
            $notifs->execute([$_user['id']]);$notifs=$notifs->fetchAll();
            foreach($notifs as $n):?>
            <a href="<?=e($n['link']??'/')?>" class="ddi" style="flex-direction:column;align-items:flex-start;gap:2px;<?=!$n['is_read']?'background:rgba(255,255,255,.03)':''?>">
              <div style="display:flex;align-items:center;gap:6px;width:100%">
                <span><?=e($n['icon']??'🔔')?></span>
                <p style="font-size:13px;font-weight:500"><?=e($n['title']??'Bildirim')?></p>
              </div>
              <p style="font-size:12px;color:var(--text2)"><?=e(substr($n['message']??'',0,60))?></p>
              <p style="font-size:11px;color:var(--text3)"><?=time_ago($n['created_at'])?></p>
            </a>
            <?php endforeach;
            if(!$anns&&!$notifs):?><p style="text-align:center;padding:24px;font-size:13px;color:var(--text3)">Bildirim yok</p><?php endif;
        }catch(Exception $e){echo '<p style="padding:16px;font-size:13px;color:var(--text3)">Yüklenemedi</p>';}
        ?>
      </div>
    </div>
    <!-- Kullanıcı -->
    <div class="dd" id="dd-user">
      <button class="ua-btn" onclick="toggleDD('dd-user')">
        <img src="<?=avatar_url($_user['avatar']??'')?>" class="uav" alt="">
        <?php if($_mem==='premium'): ?><span class="mem-badge mem-premium">Premium</span>
        <?php elseif($_mem==='ultimate'): ?><span class="mem-badge mem-ultimate">Ultimate</span><?php endif?>
      </button>
      <div class="ddm" style="min-width:240px">
        <div style="padding:10px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);margin-bottom:4px">
          <img src="<?=avatar_url($_user['avatar']??'')?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
          <div><p style="font-size:14px;font-weight:500"><?=e($_user['full_name']?:$_user['username'])?></p><p style="font-size:12px;color:var(--text2)">@<?=e($_user['username'])?></p></div>
        </div>
        <a href="/profile.php" class="ddi"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Hesabım</a>
        <a href="/channel.php" class="ddi"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4"/></svg>Kanalım</a>
        <a href="/dashboard.php" class="ddi"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Studio</a>
        <div class="dds"></div>
        <?php if($_mem==='free'): ?><a href="/checkout.php" class="ddi" style="color:var(--acc)">⭐ Premium / Ultimate</a><div class="dds"></div><?php endif?>
        <a href="/logout.php" class="ddi" style="color:#ff4444">🚪 Çıkış Yap</a>
      </div>
    </div>
    <?php else: ?>
    <a href="/login.php" class="sign-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
      Giriş Yap
    </a>
    <?php endif?>

    <!-- 3 NOKTA: Tema + Dil -->
    <div class="dd" id="dd-more">
      <button class="ib" onclick="toggleDD('dd-more')" title="Ayarlar">
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
      <div class="ddm" style="min-width:220px">
        <div style="padding:6px 14px;font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Tema</div>
        <?php foreach($_themes as $tc=>[$ti,$tn]):?>
        <button class="ddi" onclick="setTheme('<?=$tc?>')" style="<?=$_theme===$tc?'color:var(--acc);font-weight:500':''?>">
          <span style="font-size:16px"><?=$ti?></span><?=$tn?>
          <?php if($_theme===$tc):?><svg style="margin-left:auto" width="13" height="13" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php endif?>
        </button>
        <?php endforeach?>
        <div class="dds"></div>
        <div style="padding:6px 14px;font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Dil</div>
        <?php foreach($_langs as $code=>[$flag,$name]):?>
        <button class="ddi" onclick="setLang('<?=$code?>')" style="<?=$_lang_code===$code?'color:var(--acc);font-weight:500':''?>">
          <span style="font-size:18px"><?=$flag?></span><?=$name?>
          <?php if($_lang_code===$code):?><svg style="margin-left:auto" width="13" height="13" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php endif?>
        </button>
        <?php endforeach?>
      </div>
    </div>
  </div>
</header>

<div class="mob-ov" id="mob-ov" onclick="closeMob()"></div>

<aside id="sb">
  <div class="ssec">
    <a href="/" class="ni <?=$_curpage==='index'?'on':''?>">
      <svg fill="<?=$_curpage==='index'?'currentColor':'none'?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Ana Sayfa</span>
    </a>
    <a href="/category.php" class="ni <?=$_curpage==='category'?'on':''?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
      <span>Videolar</span>
    </a>
  </div>
  <?php if($_user):?>
  <div class="ssec">
    <p class="slbl">Sen</p>
    <a href="/channel.php" class="ni <?=$_curpage==='channel'?'on':''?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4"/></svg>
      <span>Kanalım</span>
    </a>
    <a href="/dashboard.php" class="ni <?=$_curpage==='dashboard'?'on':''?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      <span>Studio</span>
    </a>
    <a href="/dashboard.php?tab=history" class="ni">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>Geçmiş</span>
    </a>
    <a href="/playlist.php" class="ni <?=$_curpage==='playlist'?'on':''?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10M4 18h10"/></svg>
      <span>Listelerim</span>
    </a>
    <a href="/dashboard.php?tab=saved" class="ni">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
      <span>Kaydedilenler</span>
    </a>
  </div>
  <?php endif?>
  <?php if($_cats):?>
  <div class="ssec">
    <p class="slbl">Kategoriler</p>
    <?php foreach($_cats as $cat):?>
    <a href="/category.php?slug=<?=e($cat['slug'])?>" class="scat">
      <span style="font-size:18px;flex-shrink:0"><?=$cat['icon']?></span>
      <span><?=e($cat['name'])?></span>
    </a>
    <?php endforeach?>
  </div>
  <?php endif?>
  <div class="ssec" style="border-bottom:none">
    <a href="/admin/" class="ni">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      <span>Yönetim</span>
    </a>
  </div>
</aside>

<?php if($_flash):?>
<div id="fl" style="position:fixed;top:66px;right:16px;z-index:500;max-width:360px">
  <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;border:1px solid;box-shadow:0 4px 20px var(--shadow);font-size:13px;font-weight:500;
    <?=match($_flash['type']){'success'=>'background:#0d2e1a;border-color:#16a34a;color:#4ade80','error'=>'background:#2d0a0a;border-color:#dc2626;color:#f87171','warning'=>'background:#2d1a00;border-color:#d97706;color:#fbbf24',default=>'background:var(--bg2);border-color:var(--border);color:var(--text)'}?>">
    <span><?=match($_flash['type']){'success'=>'✓','error'=>'✗','warning'=>'⚠',default=>'ℹ'}?></span>
    <span style="flex:1"><?=e($_flash['message'])?></span>
    <button onclick="this.closest('#fl').remove()" style="background:none;border:none;cursor:pointer;opacity:.6;color:inherit;font-size:16px">✕</button>
  </div>
</div>
<script>setTimeout(()=>{const f=document.getElementById('fl');if(f){f.style.opacity='0';f.style.transition='.3s';setTimeout(()=>f?.remove(),300)}},4000)</script>
<?php endif?>

<div id="ns-toast" class="ns-toast"></div>
<div id="main">
<script>
const NS_CSRF='<?=csrf_token()?>';
const NS_USER=<?=$_user?json_encode(['id'=>$_user['id'],'membership'=>$_user['membership']??'free']):'null'?>;
const NS_LANG='<?=$_lang_code?>';
const NS_THEME='<?=$_theme?>';

function showToast(m,d=2500){var t=document.getElementById('ns-toast');t.textContent=m;t.classList.add('show');setTimeout(function(){t.classList.remove('show');},d);}
function toggleDD(id){document.querySelectorAll('.dd.open').forEach(function(d){if(d.id!==id)d.classList.remove('open');});document.getElementById(id)?.classList.toggle('open');}
document.addEventListener('click',function(e){if(!e.target.closest('.dd'))document.querySelectorAll('.dd.open').forEach(function(d){d.classList.remove('open');});});
var _sc=document.cookie.includes('sb_collapsed=1');
function applySB(){var s=document.getElementById('sb'),m=document.getElementById('main');if(s)s.classList.toggle('col',_sc);if(m)m.classList.toggle('exp',_sc);}
applySB();
function toggleDeskSB(){_sc=!_sc;document.cookie='sb_collapsed='+(_sc?'1':'0')+';path=/;max-age=31536000';applySB();}
function openMob(){document.getElementById('sb')?.classList.add('mob-open');document.getElementById('mob-ov')?.classList.add('show');}
function closeMob(){document.getElementById('sb')?.classList.remove('mob-open');document.getElementById('mob-ov')?.classList.remove('show');}
function setTheme(t){document.cookie='ns_theme='+t+';path=/;max-age=31536000';fetch('/ajax/change_theme.php?theme='+t).then(function(){location.reload();});}
function setLang(code){document.cookie='ns_lang='+code+';path=/;max-age=31536000';fetch('/ajax/change_lang.php?lang='+code).then(function(){location.reload();});}
function startVoice(){if(!('webkitSpeechRecognition'in window||'SpeechRecognition'in window))return showToast('Desteklenmiyor');var SR=window.SpeechRecognition||window.webkitSpeechRecognition;var r=new SR();r.lang=NS_LANG==='tr'?'tr-TR':'en-US';r.onresult=function(e){document.getElementById('sq').value=e.results[0][0].transcript;document.getElementById('sf').submit();};r.start();showToast('🎤 Dinleniyor...');}
function markNotifsRead(){fetch('/ajax/mark_notifs_read.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:NS_CSRF})});document.querySelectorAll('.nb').forEach(function(b){b.style.display='none';});}
if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js').catch(function(){});
</script>
