<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$user = get_user();
$page_title = 'Geliştirici Merkezi';
$base_url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];

$api_key_row = null;
if ($user) {
    $ak = $pdo->prepare("SELECT * FROM api_keys WHERE user_id=? AND active=1 LIMIT 1");
    $ak->execute([$user['id']]); $api_key_row = $ak->fetch();
}
include __DIR__ . '/includes/header.php';
?>
<style>
.dev-layout{display:grid;grid-template-columns:220px 1fr;gap:0;min-height:calc(100vh - 56px)}
.dev-nav{background:var(--bg2);border-right:1px solid var(--border);padding:20px 0}
.dev-nav a{display:block;padding:10px 20px;font-size:13px;color:var(--text2);transition:.1s;border-left:3px solid transparent}
.dev-nav a:hover,.dev-nav a.active{background:var(--hover);color:var(--text);border-left-color:var(--accent)}
.dev-main{padding:32px;max-width:900px}
.code-block{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:16px;font-family:monospace;font-size:13px;color:var(--text);overflow-x:auto;margin:8px 0 16px;position:relative}
.copy-btn{position:absolute;top:8px;right:8px;background:var(--border);border:none;color:var(--text2);padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer}
.copy-btn:hover{background:var(--text3)}
.endpoint{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px}
.method{display:inline-block;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:700;font-family:monospace;margin-right:8px}
.get{background:rgba(34,197,94,.2);color:#4ade80}
.post{background:rgba(59,130,246,.2);color:#60a5fa}
.param-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
.param-table th{padding:8px 12px;text-align:left;font-size:11px;color:var(--text2);font-weight:600;text-transform:uppercase;border-bottom:1px solid var(--border)}
.param-table td{padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text)}
.param-table td code{background:var(--bg3);padding:1px 6px;border-radius:4px;font-size:12px;font-family:monospace}
@media(max-width:768px){.dev-layout{grid-template-columns:1fr}.dev-nav{display:flex;overflow-x:auto;border-right:none;border-bottom:1px solid var(--border);padding:0}.dev-nav a{white-space:nowrap;border-left:none;border-bottom:3px solid transparent}.dev-nav a.active{border-bottom-color:var(--accent)}}
</style>

<div class="dev-layout">
  <nav class="dev-nav">
    <a href="#overview" class="active">📖 Genel Bakış</a>
    <a href="#auth">🔑 Kimlik Doğrulama</a>
    <a href="#endpoints">🔗 API Endpoint'leri</a>
    <a href="#embed">📺 Video Gömme</a>
    <a href="#webhooks">🔔 Webhook'lar</a>
    <a href="#sdk">📦 SDK / Örnekler</a>
  </nav>
  <div class="dev-main">
    <h1 style="font-size:28px;font-weight:700;margin-bottom:8px">🔧 Geliştirici Merkezi</h1>
    <p style="font-size:15px;color:var(--text2);margin-bottom:32px"><?= e($_site_title) ?> API ile güçlü uygulamalar geliştirin.</p>

    <!-- API Key -->
    <?php if ($user): ?>
    <div class="nscard" style="padding:20px;margin-bottom:32px;border-color:var(--accent)">
      <p style="font-size:13px;font-weight:600;margin-bottom:10px">🔑 API Anahtarınız</p>
      <?php if ($api_key_row): ?>
      <div style="display:flex;align-items:center;gap:10px;background:var(--bg3);border-radius:8px;padding:12px 16px">
        <code id="ak" style="flex:1;font-family:monospace;font-size:13px;color:var(--accent);word-break:break-all"><?= e($api_key_row['api_key']) ?></code>
        <button onclick="navigator.clipboard.writeText(document.getElementById('ak').textContent).then(()=>showToast('Kopyalandı!'))" style="background:var(--border);border:none;color:var(--text);padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer">📋 Kopyala</button>
      </div>
      <p style="font-size:12px;color:var(--text3);margin-top:8px">Limit: <?= $api_key_row['rate_limit'] ?> istek/saat</p>
      <?php else: ?>
      <form method="POST" action="/ajax/create_api_key.php">
        <?= csrf_field() ?>
        <button type="submit" class="nsbtn sm">API Anahtarı Oluştur</button>
      </form>
      <?php endif ?>
    </div>
    <?php else: ?>
    <div class="nscard" style="padding:16px;margin-bottom:32px;text-align:center">
      <p style="font-size:14px;color:var(--text2);margin-bottom:12px">API anahtarı almak için giriş yapın.</p>
      <a href="/login.php" class="nsbtn sm">Giriş Yap</a>
    </div>
    <?php endif ?>

    <!-- Genel Bakış -->
    <section id="overview" style="margin-bottom:40px">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:12px">📖 Genel Bakış</h2>
      <p style="font-size:14px;color:var(--text2);margin-bottom:16px;line-height:1.6">
        <?= e($_site_title) ?> REST API, platformdaki videoları aramanıza, kullanıcı verilerine erişmenize ve içerikleri kendi uygulamalarınıza entegre etmenize olanak tanır.
      </p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
        <?php foreach([['🌐','Base URL',$base_url.'/api/v1.php'],['🔒','Auth','API Key (Header veya Query)'],['📦','Format','JSON'],['⚡','Rate Limit','100 istek/saat']] as [$i,$k,$v]): ?>
        <div class="nscard" style="padding:14px">
          <p style="font-size:20px;margin-bottom:6px"><?=$i?></p>
          <p style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase"><?=$k?></p>
          <p style="font-size:13px;color:var(--text);word-break:break-all;margin-top:4px"><?=$v?></p>
        </div>
        <?php endforeach ?>
      </div>
    </section>

    <!-- Auth -->
    <section id="auth" style="margin-bottom:40px">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:12px">🔑 Kimlik Doğrulama</h2>
      <p style="font-size:14px;color:var(--text2);margin-bottom:12px">API anahtarınızı header veya query string ile gönderin:</p>
      <div class="code-block">
        <button class="copy-btn" onclick="copyCode(this)">Kopyala</button>
# Header ile (önerilen)<br>
curl -H "X-API-Key: YOUR_API_KEY" "<?= $base_url ?>/api/v1.php?action=search&q=test"<br><br>
# Query string ile<br>
curl "<?= $base_url ?>/api/v1.php?action=search&q=test&api_key=YOUR_API_KEY"
      </div>
    </section>

    <!-- Endpoints -->
    <section id="endpoints" style="margin-bottom:40px">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:16px">🔗 API Endpoint'leri</h2>

      <?php
      $endpoints = [
        ['GET','search','Video Arama','Tüm aktif platformlarda video, short veya görsel arar.',
          [['q','string','Evet','Arama sorgusu'],['platform','string','Hayır','all, youtube, dailymotion, vimeo vb.'],['type','string','Hayır','all, normal, short, image'],['limit','int','Hayır','Maks. 50, varsayılan 10']],
          '{"success":true,"data":{"query":"test","count":10,"results":[{"platform":"youtube","id":"xxx","title":"...","thumbnail":"...","channel":"...","duration":120,"views":1000,"type":"normal"}]}}'],
        ['GET','trending','Trend Videolar','En popüler videoları getirir.',
          [['platform','string','Hayır','youtube (varsayılan)'],['limit','int','Hayır','Maks. 50']],
          '{"success":true,"data":{"count":10,"results":[...]}}'],
        ['GET','user','Kullanıcı Bilgisi','Mevcut kullanıcının bilgilerini döner.',
          [],
          '{"success":true,"data":{"user":{"id":1,"username":"user","membership":"pro"}}}'],
        ['GET','saved','Kaydedilen Videolar','Kullanıcının kaydettiği videoları listeler.',
          [['limit','int','Hayır','Maks. 100'],['offset','int','Hayır','Sayfalama için']],
          '{"success":true,"data":{"saved":[...]}}'],
        ['GET','categories','Kategoriler','Aktif kategorileri listeler.',[], '{"success":true,"data":{"categories":[...]}}'],
        ['GET','platforms','Platformlar','Aktif platformları listeler.',[], '{"success":true,"data":{"platforms":[...]}}'],
      ];
      foreach ($endpoints as [$method,$action,$title,$desc,$params,$example]):
      ?>
      <div class="endpoint">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <span class="method get"><?= $method ?></span>
          <code style="font-size:14px;font-weight:600">/api/v1.php?action=<?= $action ?></code>
        </div>
        <p style="font-size:14px;font-weight:600;margin-bottom:4px"><?= $title ?></p>
        <p style="font-size:13px;color:var(--text2);margin-bottom:12px"><?= $desc ?></p>
        <?php if ($params): ?>
        <table class="param-table">
          <tr><th>Parametre</th><th>Tip</th><th>Zorunlu</th><th>Açıklama</th></tr>
          <?php foreach ($params as [$name,$type,$req,$desc2]): ?>
          <tr><td><code><?= $name ?></code></td><td style="color:var(--text3)"><?= $type ?></td><td style="color:<?= $req==='Evet'?'#4ade80':'var(--text3)'?>"><?= $req ?></td><td style="color:var(--text2)"><?= $desc2 ?></td></tr>
          <?php endforeach ?>
        </table>
        <?php endif ?>
        <p style="font-size:12px;font-weight:600;color:var(--text3);margin:10px 0 4px">Örnek Yanıt:</p>
        <div class="code-block" style="font-size:11px;padding:10px 14px"><button class="copy-btn" onclick="copyCode(this)">Kopyala</button><?= e($example) ?></div>
      </div>
      <?php endforeach ?>
    </section>

    <!-- Embed -->
    <section id="embed" style="margin-bottom:40px">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:12px">📺 Video Gömme</h2>
      <p style="font-size:14px;color:var(--text2);margin-bottom:16px">Videoları kendi sitenize iframe ile gömebilirsiniz.</p>
      <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Kopyala</button>&lt;iframe<br>
  src="<?= $base_url ?>/embed.php?platform=youtube&amp;id=VIDEO_ID"<br>
  width="560"<br>
  height="315"<br>
  frameborder="0"<br>
  allowfullscreen<br>
  allow="autoplay; fullscreen; picture-in-picture"<br>
&gt;&lt;/iframe&gt;</div>
      <p style="font-size:13px;font-weight:600;margin-bottom:8px;margin-top:16px">Embed Parametreleri:</p>
      <table class="param-table">
        <tr><th>Parametre</th><th>Değerler</th><th>Açıklama</th></tr>
        <tr><td><code>platform</code></td><td style="color:var(--text3)">youtube, dailymotion, vimeo, twitch, local</td><td style="color:var(--text2)">Video platformu</td></tr>
        <tr><td><code>id</code></td><td style="color:var(--text3)">string</td><td style="color:var(--text2)">Video ID</td></tr>
        <tr><td><code>autoplay</code></td><td style="color:var(--text3)">0 veya 1</td><td style="color:var(--text2)">Otomatik oynat (varsayılan: 1)</td></tr>
        <tr><td><code>controls</code></td><td style="color:var(--text3)">0 veya 1</td><td style="color:var(--text2)">Kontroller (varsayılan: 1)</td></tr>
        <tr><td><code>theme</code></td><td style="color:var(--text3)">dark, light</td><td style="color:var(--text2)">Tema (varsayılan: dark)</td></tr>
      </table>
    </section>

    <!-- SDK -->
    <section id="sdk" style="margin-bottom:40px">
      <h2 style="font-size:18px;font-weight:700;margin-bottom:12px">📦 Kod Örnekleri</h2>
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <?php foreach(['JavaScript','PHP','Python'] as $i=>$lang_n): ?>
        <button onclick="showSDK(<?=$i?>)" id="sdk-tab-<?=$i?>" class="nsbtn <?= $i>0?'ghost':'' ?> sm"><?= $lang_n ?></button>
        <?php endforeach ?>
      </div>
      <div id="sdk-0">
<div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Kopyala</button>// JavaScript (fetch)<br>
const API_KEY = 'your_api_key_here';<br>
const BASE = '<?= $base_url ?>/api/v1.php';<br><br>
async function searchVideos(query, platform = 'all') {<br>
&nbsp;&nbsp;const res = await fetch(`${BASE}?action=search&q=${encodeURIComponent(query)}&platform=${platform}`, {<br>
&nbsp;&nbsp;&nbsp;&nbsp;headers: { 'X-API-Key': API_KEY }<br>
&nbsp;&nbsp;});<br>
&nbsp;&nbsp;const data = await res.json();<br>
&nbsp;&nbsp;return data.data.results;<br>
}<br><br>
// Kullanım<br>
searchVideos('php tutorial', 'youtube').then(videos => {<br>
&nbsp;&nbsp;videos.forEach(v => console.log(v.title, v.platform));<br>
});</div>
      </div>
      <div id="sdk-1" style="display:none">
<div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Kopyala</button>&lt;?php<br>
$api_key = 'your_api_key_here';<br>
$base = '<?= $base_url ?>/api/v1.php';<br><br>
function searchVideos($query, $platform = 'all', $api_key) {<br>
&nbsp;&nbsp;$url = $base . '?action=search&q=' . urlencode($query) . '&platform=' . $platform;<br>
&nbsp;&nbsp;$ch = curl_init($url);<br>
&nbsp;&nbsp;curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $api_key]);<br>
&nbsp;&nbsp;curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);<br>
&nbsp;&nbsp;$res = json_decode(curl_exec($ch), true);<br>
&nbsp;&nbsp;curl_close($ch);<br>
&nbsp;&nbsp;return $res['data']['results'] ?? [];<br>
}<br><br>
$videos = searchVideos('php tutorial', 'youtube', $api_key);<br>
foreach ($videos as $v) echo $v['title'] . "\n";</div>
      </div>
      <div id="sdk-2" style="display:none">
<div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Kopyala</button">import requests<br><br>
API_KEY = 'your_api_key_here'<br>
BASE = '<?= $base_url ?>/api/v1.php'<br>
HEADERS = {'X-API-Key': API_KEY}<br><br>
def search_videos(query, platform='all', limit=10):<br>
&nbsp;&nbsp;&nbsp;&nbsp;params = {'action': 'search', 'q': query, 'platform': platform, 'limit': limit}<br>
&nbsp;&nbsp;&nbsp;&nbsp;res = requests.get(BASE, params=params, headers=HEADERS)<br>
&nbsp;&nbsp;&nbsp;&nbsp;return res.json()['data']['results']<br><br>
videos = search_videos('python tutorial', 'youtube')<br>
for v in videos:<br>
&nbsp;&nbsp;&nbsp;&nbsp;print(v['title'], '-', v['platform'])</div>
      </div>
    </section>
  </div>
</div>

<script>
function showSDK(i){
  [0,1,2].forEach(j=>{
    document.getElementById('sdk-'+j).style.display=j===i?'':'none';
    const btn=document.getElementById('sdk-tab-'+j);
    btn.classList.toggle('ghost',j!==i);
  });
}
function copyCode(btn){
  const code=btn.nextSibling?.textContent||btn.parentElement.textContent.replace('Kopyala','');
  navigator.clipboard.writeText(code.trim()).then(()=>{btn.textContent='✓ Kopyalandı';setTimeout(()=>btn.textContent='Kopyala',2000)});
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
