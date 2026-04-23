<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sms.php';
start_session();
if(get_user())redirect('/dashboard.php');
$lang_code=$_COOKIE['ns_lang']??($_SESSION['lang']??'tr');
$lf=__DIR__.'/languages/'.$lang_code.'.php';
$lang=file_exists($lf)?include $lf:include __DIR__.'/languages/tr.php';
$page_title=$lang['login'];
$error='';$sms_step=false;$phone_display='';

$sms_active=get_setting('sms_active','0')==='1';
$sms_required=get_setting('sms_login_required','0')==='1';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify()){$error='Güvenlik hatası.';}
    elseif(isset($_POST['sms_code'])&&!empty($_SESSION['sms_login_uid'])){
        $code=trim($_POST['sms_code']??'');
        $uid=(int)$_SESSION['sms_login_uid'];
        if(SMS::verifyOTP($uid,$code,'login')){
            $st=$pdo->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");
            $st->execute([$uid]);$user=$st->fetch();
            unset($_SESSION['sms_login_uid'],$_SESSION['sms_login_phone']);
            login_user($user);set_flash('success',$lang['login_success']);
            redirect($_GET['return']??'/dashboard.php');
        }else{$error='Hatalı veya süresi dolmuş kod.';$sms_step=true;$phone_display=$_SESSION['sms_login_phone']??'';}
    }else{
        $ident=trim($_POST['email']??'');$password=$_POST['password']??'';
        $result=attempt_login($ident,$password);
        if($result['success']){
            $user=$result['user'];
            // SMS gerekiyor? (admin zorunlu VEYA kullanıcı kendi aktif etti + telefonu var)
            $need_sms=$sms_active&&(
                $sms_required||
                (!empty($user['sms_login_enabled'])&&$user['phone'])
            );
            if($need_sms&&$user['phone']){
                SMS::instance()->sendOTP($user['id'],$user['phone'],'login');
                $_SESSION['sms_login_uid']=$user['id'];
                $_SESSION['sms_login_phone']=substr($user['phone'],0,4).'****'.substr($user['phone'],-2);
                $sms_step=true;$phone_display=$_SESSION['sms_login_phone'];
            }else{
                login_user($user);set_flash('success',$lang['login_success']);
                redirect($_GET['return']??'/dashboard.php');
            }
        }else{$error=$result['error'];}
    }
}
include __DIR__.'/includes/header.php';
?>
<style>
.auth-wrap{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center;padding:24px}
.auth-box{width:100%;max-width:400px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:32px}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px}
.form-err{background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#f87171;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px}
</style>
<div class="auth-wrap"><div>
  <div style="text-align:center;margin-bottom:24px">
    <svg width="40" height="28" viewBox="0 0 90 64" fill="var(--acc)"><path d="M88.1 9.9C87 5.9 83.9 2.8 79.9 1.7 73 0 45 0 45 0S17 0 10.1 1.7C6.1 2.8 3 5.9 1.9 9.9 0 16.8 0 32 0 32s0 15.2 1.9 22.1C3 58.1 6.1 61.2 10.1 62.3 17 64 45 64 45 64s28 0 34.9-1.7c4-1.1 7.1-4.2 8.2-8.2C90 47.2 90 32 90 32s0-15.2-1.9-22.1z"/><path d="M36 45.6l23.3-13.6L36 18.4v27.2z" fill="white"/></svg>
  </div>
  <div class="auth-box">
    <?php if($sms_step): ?>
    <h1 style="font-size:20px;font-weight:700;text-align:center;margin-bottom:4px">📱 SMS Doğrulama</h1>
    <p style="font-size:13px;color:var(--text2);text-align:center;margin-bottom:20px"><?=$phone_display?> numarasına kod gönderildi.</p>
    <?php if($error): ?><div class="form-err"><?=e($error)?></div><?php endif?>
    <form method="POST">
      <?=csrf_field()?>
      <div class="form-group">
        <label class="form-label">6 Haneli SMS Kodu</label>
        <input type="text" name="sms_code" maxlength="6" pattern="[0-9]{6}" required autofocus class="nsinput" style="text-align:center;font-size:28px;letter-spacing:12px;font-family:monospace;border-radius:8px;padding:14px">
      </div>
      <button type="submit" class="nsbtn" style="width:100%;justify-content:center;border-radius:8px;padding:12px">Doğrula ve Giriş Yap</button>
      <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:10px">Kod 5 dakika geçerlidir</p>
    </form>
    <?php else: ?>
    <h1 style="font-size:20px;font-weight:700;text-align:center;margin-bottom:4px"><?=e($lang['login'])?></h1>
    <p style="font-size:13px;color:var(--text2);text-align:center;margin-bottom:20px"><?=e($_site_title)?>'a giriş yapın</p>
    <?php if($error): ?><div class="form-err"><?=e($error)?></div><?php endif?>
    <form method="POST">
      <?=csrf_field()?>
      <div class="form-group">
        <label class="form-label">E-posta / Kullanıcı Adı</label>
        <input type="text" name="email" required autofocus value="<?=e($_POST['email']??'')?>" class="nsinput" style="border-radius:8px" placeholder="ornek@mail.com">
      </div>
      <div class="form-group">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <label class="form-label" style="margin:0"><?=e($lang['password'])?></label>
          <a href="/forgot-password.php" style="font-size:12px;color:var(--acc)"><?=e($lang['forgot_password'])?></a>
        </div>
        <div style="position:relative">
          <input type="password" name="password" id="pwd" required class="nsinput" style="border-radius:8px;padding-right:44px">
          <button type="button" onclick="var p=document.getElementById('pwd');p.type=p.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px;font-size:13px;color:var(--text2)">
        <input type="checkbox" name="remember" style="accent-color:var(--acc)"><?=e($lang['remember_me'])?>
      </label>
      <button type="submit" class="nsbtn" style="width:100%;justify-content:center;border-radius:8px;padding:12px"><?=e($lang['login'])?></button>
    </form>
    <?php if($sms_active): ?>
    <div style="display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--text3);font-size:12px"><div style="flex:1;height:1px;background:var(--border)"></div>veya<div style="flex:1;height:1px;background:var(--border)"></div></div>
    <a href="/login-sms.php" class="nsbtn ghost" style="width:100%;justify-content:center;border-radius:8px;padding:11px">📱 Telefon ile Giriş</a>
    <?php endif?>
    <?php endif?>
  </div>
  <p style="text-align:center;font-size:13px;color:var(--text2);margin-top:16px">
    <?=e($lang['no_account'])?> <a href="/register.php" style="color:var(--acc);font-weight:500"><?=e($lang['register'])?></a>
  </p>
</div></div>
<?php include __DIR__.'/includes/footer.php';?>
