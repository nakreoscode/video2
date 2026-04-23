<?php
$_is_short=($v['type']==='short');
$_is_image=($v['type']==='image');
// Short videolar YouTube shorts URL ile açılır, normal izleme sayfasına gitmesin
if($_is_short){
    $_target='/watch.php?platform='.urlencode($v['platform']).'&id='.urlencode($v['id']).'&type=short';
}elseif($_is_image){
    $_target=$v['url']??'#';
}else{
    $_target='/watch.php?platform='.urlencode($v['platform']).'&id='.urlencode($v['id']);
}
$_watch='/watch.php?platform='.urlencode($v['platform']).'&id='.urlencode($v['id']).'&type='.urlencode($v['type']??'normal');
// Madde 6: API videosunda sistem, local videoda kullanıcı
$_is_local = true; // Sadece manuel yükleme var
$_display_channel = $_is_local ? ($v['channel']??'') : ($v['channel']??ucfirst($v['platform']));
$_ch_init=strtoupper(substr($_display_channel?:$v['platform']??'?',0,1));
$_dd_id='vdd_'.substr(md5($v['platform'].$v['id']),0,8);
?>
<div class="vc" onclick="window.location.href='<?=e($_target)?>'">
  <div class="vct <?=$_is_short?'st':''?>">
    <?php if($v['thumbnail']):?>
    <img src="<?=e($v['thumbnail'])?>" alt="<?=e($v['title'])?>" loading="lazy" onerror="this.src='/assets/img/no-thumb.svg';this.onerror=null">
    <?php else:?>
    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:32px">🎬</div>
    <?php endif?>
    <div class="vct-hov"><div style="width:48px;height:48px;background:rgba(0,0,0,.75);border-radius:50%;display:flex;align-items:center;justify-content:center"><svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>
    
    <?php if(($v['duration']??0)>0&&!$_is_image):?><span class="vct-dur"><?=format_duration($v['duration'])?></span><?php endif?>
    <?php if($_is_short):?><span class="vct-sh">Short</span>
    <?php elseif($_is_image):?><span class="vct-sh" style="background:#3b82f6">Görsel</span><?php endif?>
  </div>
  <div class="vci" onclick="event.stopPropagation()">
    <?php if(!$_is_short):?><div class="vci-av"><?=$_ch_init?></div><?php endif?>
    <div class="vci-d">
      <a href="<?=e($_target)?>" onclick="event.stopPropagation()"><p class="vci-t"><?=e($v['title'])?></p></a>
      <p class="vci-c"><?=e($_display_channel)?> </p>
      <p class="vci-m"><?php if(($v['views']??0)>0):?><?=format_views($v['views'])?> görüntülenme<?php endif?></p>
    </div>
    <div class="dd" id="<?=$_dd_id?>">
      <button class="vc-mbtn" onclick="event.stopPropagation();toggleDD('<?=$_dd_id?>')">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
      </button>
      <div class="ddm" style="min-width:200px">
        <?php if(!$_is_image):?>
        <button class="ddi" onclick="event.stopPropagation();vcSave('<?=e($v['platform'])?>','<?=e($v['id'])?>','<?=e(addslashes($v['title']))?>','<?=e($v['thumbnail']??'')?>','<?=e(addslashes($v['channel']??''))?>',<?=intval($v['duration']??0)?>,'<?=e($v['type']??'normal')?>)">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>Kaydet
        </button>
        <?php endif?>
        <button class="ddi" onclick="event.stopPropagation();vcShare('<?=e($_target)?>','<?=e(addslashes($v['title']))?>')">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>Paylaş
        </button>
        <?php if(!$_is_image&&!$_is_short):?>
        <button class="ddi" onclick="event.stopPropagation();vcEmbed('<?=e($v['platform'])?>','<?=e($v['id'])?>')">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Embed
        </button>
        <?php endif?>
      </div>
    </div>
  </div>
</div>
<script>
function vcSave(pl,id,ti,th,ch,dur,type){if(!NS_USER)return showToast('Giriş yapın');fetch('/ajax/save_video.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({platform:pl,id:id,title:ti,thumbnail:th,channel:ch,duration:dur,type:type,csrf_token:NS_CSRF})}).then(function(r){return r.json()}).then(function(d){showToast(d.message||(d.success?'Kaydedildi':'Hata'));});}
function vcShare(url,title){var fu=url.startsWith('http')?url:location.origin+url;if(navigator.share)navigator.share({title:title,url:fu});else navigator.clipboard.writeText(fu).then(function(){showToast('Link kopyalandı!');});}
function vcEmbed(pl,id){navigator.clipboard.writeText('<iframe src="'+location.origin+'/embed.php?platform='+pl+'&id='+id+'" width="560" height="315" frameborder="0" allowfullscreen></iframe>').then(function(){showToast('Embed kopyalandı!');});}
</script>
