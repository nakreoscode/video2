<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
$user = require_login();
$page_title = 'Üyeliği Yükselt';

if ($user['membership'] !== 'free') {
    set_flash('success','Zaten ' . ucfirst($user['membership']) . ' üyesiniz!');
    redirect('/dashboard.php');
}

$plan_premium = get_plan('premium');
$plan_ultimate = get_plan('ultimate');
$premium_price = (float)($plan_premium['price'] ?? 99);
$ultimate_price = (float)($plan_ultimate['price'] ?? 199);
$pm = explode(',', get_setting('payment_methods','bank,crypto'));

$errors = [];

// ─── PayTR Ödeme Başlat ─────────────────────────────────────────────────
function start_paytr(array $user, string $plan, float $amount, object $pdo): void {
    $merchant_id  = get_setting('paytr_merchant_id');
    $merchant_key = get_setting('paytr_merchant_key');
    $merchant_salt= get_setting('paytr_merchant_salt');

    if (!$merchant_id || !$merchant_key || !$merchant_salt) {
        redirect('/checkout.php?error=paytr_config');
    }

    // Ödeme kaydı oluştur
    $order_id = 'NS' . $user['id'] . time();
    $pdo->prepare("INSERT INTO payments(user_id,method,amount,plan,receipt_info,status) VALUES(?,?,?,?,?,'pending')")
        ->execute([$user['id'], 'paytr', $amount, $plan, $order_id]);

    $origin = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];

    $basket = base64_encode(json_encode([[ucfirst($plan).' Üyelik', number_format($amount,2), 1]]));
    $user_ip = get_ip();
    $email   = $user['email'];
    $user_name = $user['full_name'] ?: $user['username'];
    $user_phone = $user['phone'] ?: '05000000000';
    $user_address = 'Türkiye';

    $no_installment = 0;
    $max_installment = 0;
    $currency = 'TL';
    $test_mode = 0;

    $amount_cent = (int)($amount * 100);

    $hash_str = $merchant_id . $user_ip . $order_id . $email . $amount_cent . $basket . $no_installment . $max_installment . $currency . $test_mode . $merchant_salt;
    $paytr_token = base64_encode(hash_hmac('sha256', $hash_str, $merchant_key, true));

    $post_vals = [
        'merchant_id'       => $merchant_id,
        'user_ip'           => $user_ip,
        'merchant_oid'      => $order_id,
        'email'             => $email,
        'payment_amount'    => $amount_cent,
        'paytr_token'       => $paytr_token,
        'user_basket'       => $basket,
        'debug_on'          => 0,
        'no_installment'    => $no_installment,
        'max_installment'   => $max_installment,
        'user_name'         => $user_name,
        'user_address'      => $user_address,
        'user_phone'        => $user_phone,
        'merchant_ok_url'   => $origin . '/payment_ok.php',
        'merchant_fail_url' => $origin . '/payment_fail.php',
        'timeout_limit'     => 30,
        'currency'          => $currency,
        'test_mode'         => $test_mode,
        'lang'              => 'tr',
    ];

    $ch = curl_init('https://www.paytr.com/odeme/api/get-token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_vals,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res  = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);

    if (!empty($data['status']) && $data['status'] === 'success') {
        redirect('https://www.paytr.com/odeme/guvenli/' . $data['token']);
    } else {
        redirect('/checkout.php?error=paytr_token&msg=' . urlencode($data['reason'] ?? 'Bilinmeyen hata'));
    }
}

// ─── Shopier Ödeme Başlat ───────────────────────────────────────────────
function start_shopier(array $user, string $plan, float $amount, object $pdo): void {
    $api_key    = get_setting('shopier_api_key');
    $api_secret = get_setting('shopier_api_secret');

    if (!$api_key || !$api_secret) {
        redirect('/checkout.php?error=shopier_config');
    }

    $order_id = 'NS' . $user['id'] . time();
    $pdo->prepare("INSERT INTO payments(user_id,method,amount,plan,receipt_info,status) VALUES(?,?,?,?,?,'pending')")
        ->execute([$user['id'], 'shopier', $amount, $plan, $order_id]);

    $origin = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];

    $data = [
        'API_key'           => $api_key,
        'website_index'     => 1,
        'platform_order_id' => $order_id,
        'product_name'      => ucfirst($plan) . ' Üyelik',
        'product_type'      => 1,
        'buyer_name'        => $user['full_name'] ?: $user['username'],
        'buyer_surname'     => '',
        'buyer_email'       => $user['email'],
        'buyer_phone'       => $user['phone'] ?: '',
        'buyer_account_age' => 0,
        'buyer_id_nr'       => $user['id'],
        'total_order_value' => number_format($amount, 2, '.', ''),
        'currency'          => 0, // TRY
        'callback'          => $origin . '/ajax/shopier_callback.php',
        'callback_base'     => $origin,
    ];

    $hash = base64_encode(hash_hmac('SHA256', $order_id . $data['total_order_value'], $api_secret, true));
    $data['signature'] = $hash;

    $ch = curl_init('https://www.shopier.com/ShowProduct/api_pay4.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $result = json_decode($res, true);

    if (!empty($result['ARG1'])) {
        redirect('https://www.shopier.com/ShowProduct/api_pay4.php?ARG1=' . $result['ARG1'] . '&ARG2=' . $result['ARG2'] . '&ARG3=' . $result['ARG3'] . '&ARG4=' . $result['ARG4']);
    } else {
        redirect('/checkout.php?error=shopier_token');
    }
}

// ─── POST İşlemleri ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $plan   = in_array($_POST['plan']??'', ['premium','ultimate']) ? $_POST['plan'] : 'premium';
    $method = in_array($_POST['method']??'', $pm) ? $_POST['method'] : $pm[0];
    $amount = $plan === 'premium' ? $premium_price : $ultimate_price;

    // PayTR → doğrudan yönlendir
    if ($method === 'paytr') {
        start_paytr($user, $plan, $amount, $pdo);
        exit;
    }

    // Shopier → doğrudan yönlendir
    if ($method === 'shopier') {
        start_shopier($user, $plan, $amount, $pdo);
        exit;
    }

    // Banka / Kripto → dekont
    $receipt = trim($_POST['receipt_info'] ?? '');
    if (!$receipt) { $errors[] = 'Dekont/TXID bilgisi giriniz.'; }
    else {
        $pdo->prepare("INSERT INTO payments(user_id,method,amount,plan,receipt_info,status) VALUES(?,?,?,?,?,'pending')")
            ->execute([$user['id'], $method, $amount, $plan, $receipt]);
        try { mail($user['email'], 'Ödeme Talebiniz Alındı', "Merhaba {$user['username']},\n\n{$plan} paketi için ödeme talebiniz alındı. Admin onayı bekleniyor.\n\nTeşekkürler,\nNakreosStream"); } catch(Exception $e){}
        set_flash('success','Ödeme talebiniz alındı. Admin onayı bekleniyor.');
        redirect('/dashboard.php');
    }
}

// Hata mesajları
$error_msgs = [
    'paytr_config'  => 'PayTR API bilgileri eksik. Admin panelinden girin.',
    'paytr_token'   => 'PayTR bağlantı hatası. Lütfen tekrar deneyin.',
    'shopier_config'=> 'Shopier API bilgileri eksik. Admin panelinden girin.',
    'shopier_token' => 'Shopier bağlantı hatası. Lütfen tekrar deneyin.',
];
if (!empty($_GET['error']) && isset($error_msgs[$_GET['error']])) {
    $errors[] = $error_msgs[$_GET['error']];
    if (!empty($_GET['msg'])) $errors[] = 'Detay: ' . e($_GET['msg']);
}

include __DIR__ . '/includes/header.php';
?>
<style>
.co-wrap{max-width:1000px;margin:0 auto;padding:32px 24px}
.plan-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:40px}
.plan-card{border-radius:16px;border:2px solid var(--border);padding:28px 24px;cursor:pointer;transition:.2s;position:relative;overflow:hidden;background:var(--bg2)}
.plan-card:hover{border-color:var(--acc);transform:translateY(-2px)}
.plan-card.featured::before{content:'En Popüler';position:absolute;top:14px;right:-24px;background:var(--acc);color:#fff;font-size:11px;font-weight:700;padding:4px 32px;transform:rotate(45deg)}
.plan-price{font-size:36px;font-weight:700;margin:10px 0 4px}
.feature-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:14px}
.feature-row:last-child{border-bottom:none}
.f-yes{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:700;background:#0d2e1a;color:#4ade80}
.f-no{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;background:var(--bg3);color:var(--text3)}
.pay-form{background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:28px}
@media(max-width:768px){.plan-grid{grid-template-columns:1fr;max-width:380px;margin:0 auto 32px}}
</style>

<div class="co-wrap">
  <div style="text-align:center;margin-bottom:32px">
    <h1 style="font-size:28px;font-weight:700;margin-bottom:8px">⭐ Üyeliği Yükselt</h1>
    <p style="font-size:15px;color:var(--text2)">Daha iyi bir deneyim için planını seç</p>
  </div>

  <?php if ($errors): ?>
  <div style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#f87171;border-radius:10px;padding:14px 18px;font-size:13px;margin-bottom:24px">
    <?= implode('<br>', $errors) ?>
  </div>
  <?php endif ?>

  <!-- Plan Kartları -->
  <div class="plan-grid">
    <!-- Ücretsiz -->
    <div class="plan-card">
      <div style="font-size:24px;margin-bottom:8px">⚪</div>
      <h2 style="font-size:18px;font-weight:700">Ücretsiz</h2>
      <div class="plan-price" style="font-size:28px">₺0</div>
      <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Her zaman ücretsiz</p>
      <?php foreach([['yes','Video izle'],['yes','Arama'],['no','Reklam gösterilir'],['no','API erişimi'],['no','Video indir']] as [$t,$f]):?>
      <div class="feature-row"><span class="<?=$t==='yes'?'f-yes':'f-no'?>"><?=$t==='yes'?'✓':'✗'?></span><?=$f?></div>
      <?php endforeach?>
      <div style="margin-top:16px;padding:10px;background:var(--bg3);border-radius:8px;text-align:center;font-size:13px;color:var(--text2)">Mevcut Planın</div>
    </div>

    <!-- Premium -->
    <div class="plan-card featured" id="plan-premium" onclick="selectPlan('premium')" style="border-color:rgba(26,115,232,.5);background:linear-gradient(135deg,rgba(26,115,232,.06),rgba(13,71,161,.06))">
      <div style="font-size:24px;margin-bottom:8px">💙</div>
      <h2 style="font-size:18px;font-weight:700">Premium</h2>
      <div class="plan-price" style="color:#1a73e8">₺<?=number_format($premium_price,2)?></div>
      <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Aylık · KDV dahil</p>
      <?php foreach([['yes','Reklamsız izle'],['yes','Sınırsız kaydet'],['yes','API erişimi'],['yes','Video yükle'],['no','Video indir'],['no','Reklam yayınla']] as [$t,$f]):?>
      <div class="feature-row"><span class="<?=$t==='yes'?'f-yes':'f-no'?>"><?=$t==='yes'?'✓':'✗'?></span><?=$f?></div>
      <?php endforeach?>
      <button onclick="selectPlan('premium')" class="nsbtn" style="width:100%;justify-content:center;margin-top:16px;border-radius:10px;padding:12px;background:#1a73e8">Premium'a Geç</button>
    </div>

    <!-- Ultimate -->
    <div class="plan-card" id="plan-ultimate" onclick="selectPlan('ultimate')" style="border-color:rgba(124,58,237,.4);background:linear-gradient(135deg,rgba(124,58,237,.06),rgba(219,39,119,.06))">
      <div style="font-size:24px;margin-bottom:8px">💜</div>
      <h2 style="font-size:18px;font-weight:700">Ultimate</h2>
      <div class="plan-price" style="background:linear-gradient(135deg,#7c3aed,#db2777);-webkit-background-clip:text;-webkit-text-fill-color:transparent">₺<?=number_format($ultimate_price,2)?></div>
      <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Aylık · KDV dahil</p>
      <?php foreach([['yes','Tüm Premium özellikler'],['yes','Video indir'],['yes','Reklam yayınla'],['yes','Sınırsız yükleme'],['yes','Özel rozet']] as [$t,$f]):?>
      <div class="feature-row"><span class="f-yes">✓</span><?=$f?></div>
      <?php endforeach?>
      <button onclick="selectPlan('ultimate')" class="nsbtn" style="width:100%;justify-content:center;margin-top:16px;border-radius:10px;padding:12px;background:linear-gradient(135deg,#7c3aed,#db2777)">Ultimate'e Geç</button>
    </div>
  </div>

  <!-- Ödeme Formu -->
  <div class="pay-form" id="pay-form" style="display:none">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:20px">💳 Ödeme Yöntemi Seç</h2>

    <form method="POST" id="payment-form">
      <?= csrf_field() ?>
      <input type="hidden" name="plan" id="plan-input" value="premium">

      <!-- Plan özeti -->
      <div style="background:var(--bg3);border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div>
          <p style="font-size:14px;font-weight:600" id="sum-name">Premium</p>
          <p style="font-size:12px;color:var(--text2)">Aylık abonelik</p>
        </div>
        <div style="font-size:20px;font-weight:700" id="sum-price">₺<?=number_format($premium_price,2)?></div>
      </div>

      <!-- Ödeme yöntemi -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
        <?php
        $pay_labels = [
            'paytr'   => ['💳','PayTR','Kredi/Banka Kartı'],
            'shopier' => ['🛒','Shopier','Güvenli Ödeme'],
            'bank'    => ['🏦','Banka Havalesi','Manuel onay'],
            'crypto'  => ['₿','Kripto (USDT)','TRC20 ağı'],
        ];
        foreach ($pm as $i=>$method): if (!isset($pay_labels[$method])) continue;
        [$icon,$name,$sub] = $pay_labels[$method];
        ?>
        <label style="cursor:pointer;flex:1;min-width:130px">
          <input type="radio" name="method" value="<?=$method?>" <?=$i===0?'checked':''?> onchange="showMethod('<?=$method?>')" style="display:none">
          <div class="pay-method-btn" data-m="<?=$method?>" style="padding:12px 14px;border-radius:10px;border:2px solid <?=$i===0?'var(--acc)':'var(--border)'?>;text-align:center;transition:.2s">
            <div style="font-size:22px;margin-bottom:4px"><?=$icon?></div>
            <div style="font-size:13px;font-weight:600"><?=$name?></div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px"><?=$sub?></div>
          </div>
        </label>
        <?php endforeach ?>
      </div>

      <!-- PayTR bilgi -->
      <?php if (in_array('paytr',$pm)): ?>
      <div id="info-paytr" style="background:rgba(26,115,232,.08);border:1px solid rgba(26,115,232,.2);border-radius:10px;padding:14px;margin-bottom:16px;display:<?=$pm[0]==='paytr'?'block':'none'?>">
        <p style="font-size:13px;font-weight:600;color:#3ea6ff;margin-bottom:6px">💳 PayTR ile Güvenli Ödeme</p>
        <p style="font-size:12px;color:var(--text2)">Tıkladığınızda PayTR güvenli ödeme sayfasına yönlendirileceksiniz. Visa, Mastercard, Troy desteklenir.</p>
      </div>
      <?php endif ?>

      <!-- Shopier bilgi -->
      <?php if (in_array('shopier',$pm)): ?>
      <div id="info-shopier" style="background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.2);border-radius:10px;padding:14px;margin-bottom:16px;display:<?=$pm[0]==='shopier'?'block':'none'?>">
        <p style="font-size:13px;font-weight:600;color:#fb923c;margin-bottom:6px">🛒 Shopier ile Güvenli Ödeme</p>
        <p style="font-size:12px;color:var(--text2)">Tıkladığınızda Shopier güvenli ödeme sayfasına yönlendirileceksiniz.</p>
      </div>
      <?php endif ?>

      <!-- Banka bilgileri -->
      <?php if (in_array('bank',$pm) && get_setting('bank_iban')): ?>
      <div id="info-bank" style="background:var(--bg3);border-radius:10px;padding:14px;margin-bottom:16px;display:<?=$pm[0]==='bank'?'block':'none'?>">
        <p style="font-size:13px;font-weight:600;margin-bottom:10px">🏦 Banka Havalesi</p>
        <?php foreach([['Banka','bank_name'],['Hesap Sahibi','bank_owner'],['IBAN','bank_iban']] as [$l,$k]): if (!get_setting($k)) continue; ?>
        <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:13px">
          <span style="color:var(--text2)"><?=$l?></span>
          <span style="font-weight:500;font-family:monospace"><?=e(get_setting($k))?></span>
        </div>
        <?php endforeach ?>
        <p style="font-size:12px;color:var(--text3);margin-top:8px">Havale açıklamasına kullanıcı adınızı yazın: <strong><?=e($user['username'])?></strong></p>
      </div>
      <?php endif ?>

      <!-- Kripto bilgileri -->
      <?php if (in_array('crypto',$pm) && get_setting('crypto_address')): ?>
      <div id="info-crypto" style="background:var(--bg3);border-radius:10px;padding:14px;margin-bottom:16px;display:<?=$pm[0]==='crypto'?'block':'none'?>">
        <p style="font-size:13px;font-weight:600;margin-bottom:8px">₿ USDT Ödeme</p>
        <p style="font-size:12px;color:var(--text2);margin-bottom:4px">Ağ: <strong><?=e(get_setting('crypto_network','TRC20'))?></strong></p>
        <div style="display:flex;align-items:center;gap:8px;background:var(--bg2);border-radius:8px;padding:10px 12px">
          <code id="wallet-addr" style="font-size:11px;color:var(--acc);flex:1;word-break:break-all"><?=e(get_setting('crypto_address'))?></code>
          <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('wallet-addr').textContent).then(()=>showToast('Kopyalandı!'))" style="background:var(--border);border:none;color:var(--text);padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer">📋</button>
        </div>
      </div>
      <?php endif ?>

      <!-- Dekont (sadece banka/kripto için) -->
      <div id="receipt-section" style="display:<?=(in_array($pm[0],['bank','crypto']))?'block':'none'?>;margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px">Dekont / TXID *</label>
        <input type="text" name="receipt_info" id="receipt-input" class="nsinput" style="border-radius:8px;font-size:13px;padding:10px 14px" placeholder="Referans no veya kripto işlem ID...">
        <p style="font-size:12px;color:var(--text3);margin-top:5px">Ödeme sonrası dekont/TXID girin, admin onayıyla üyelik aktifleşir.</p>
      </div>

      <button type="submit" id="pay-btn" class="nsbtn" style="width:100%;justify-content:center;border-radius:10px;padding:13px;font-size:15px">
        💳 Ödemeye Geç
      </button>
      <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:10px" id="pay-note">PayTR güvenli ödeme sayfasına yönlendirileceksiniz</p>
    </form>
  </div>
</div>

<script>
var premPrice = '<?=number_format($premium_price,2)?>';
var ultPrice  = '<?=number_format($ultimate_price,2)?>';

var payNotes = {
    paytr:   'PayTR güvenli ödeme sayfasına yönlendirileceksiniz',
    shopier: 'Shopier güvenli ödeme sayfasına yönlendirileceksiniz',
    bank:    'Havale sonrası dekontu girin, admin onaylayacak',
    crypto:  'Transfer sonrası TXID girin, admin onaylayacak',
};
var payBtns = {
    paytr:   '💳 PayTR ile Öde',
    shopier: '🛒 Shopier ile Öde',
    bank:    '✓ Ödeme Talebini Gönder',
    crypto:  '✓ Ödeme Talebini Gönder',
};

function selectPlan(plan) {
    document.getElementById('plan-input').value = plan;
    document.getElementById('sum-name').textContent = plan==='premium'?'Premium':'Ultimate';
    document.getElementById('sum-price').textContent = '₺'+(plan==='premium'?premPrice:ultPrice);
    document.getElementById('pay-form').style.display = 'block';
    document.getElementById('pay-form').scrollIntoView({behavior:'smooth',block:'start'});
    ['premium','ultimate'].forEach(function(p){
        var el = document.getElementById('plan-'+p);
        el.style.transform = p===plan?'translateY(-4px)':'translateY(0)';
        el.style.boxShadow = p===plan?'0 8px 32px rgba(0,0,0,.3)':'none';
    });
}

function showMethod(m) {
    ['paytr','shopier','bank','crypto'].forEach(function(k){
        var el = document.getElementById('info-'+k);
        if (el) el.style.display = k===m?'block':'none';
    });
    document.querySelectorAll('.pay-method-btn').forEach(function(b){
        b.style.borderColor = b.dataset.m===m?'var(--acc)':'var(--border)';
    });
    // Dekont alanını göster/gizle
    var rs = document.getElementById('receipt-section');
    var ri = document.getElementById('receipt-input');
    if (m==='bank'||m==='crypto') {
        rs.style.display='block';
        ri.setAttribute('required','required');
    } else {
        rs.style.display='none';
        ri.removeAttribute('required');
    }
    // Buton ve not güncelle
    document.getElementById('pay-btn').textContent = payBtns[m]||'Öde';
    document.getElementById('pay-note').textContent = payNotes[m]||'';
}

// Form submit - PayTR/Shopier için loading göster
document.getElementById('payment-form').addEventListener('submit', function(){
    var method = document.querySelector('input[name="method"]:checked')?.value;
    if (method==='paytr'||method==='shopier') {
        var btn = document.getElementById('pay-btn');
        btn.textContent = '⏳ Yönlendiriliyor...';
        btn.disabled = true;
    }
});

<?php if (!empty($_POST['plan'])): ?>
selectPlan('<?=e($_POST['plan'])?>');
<?php endif ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
