<?php

// Bakım modu kontrolü
function check_maintenance(): void {
    // Admin ve login sayfaları hariç
    $skip = ['login','admin','logout','ajax','install'];
    $current = trim($_SERVER['REQUEST_URI']??'/', '/');
    foreach ($skip as $s) {
        if (str_contains($current, $s)) return;
    }
    global $pdo;
    try {
        $st = $pdo->query("SELECT value FROM settings WHERE `key`='maintenance_mode' LIMIT 1");
        if ($st && $st->fetchColumn() === '1') {
            // Session zaten aktifse tekrar başlatma
            if (session_status() === PHP_SESSION_NONE) session_start();
            // Admin ise geçir
            if (!empty($_SESSION['admin_id'])) return;
            http_response_code(503);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Bakım Modu</title>
            <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Roboto,sans-serif;background:#0f0f0f;color:#f1f1f1;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}
            .box{max-width:500px;padding:48px 32px}.icon{font-size:72px;margin-bottom:24px}.title{font-size:28px;font-weight:700;margin-bottom:12px}.desc{font-size:16px;color:#aaa;line-height:1.6}</style></head>
            <body><div class="box"><div class="icon">🔧</div><h1 class="title">Bakım Yapılıyor</h1>
            <p class="desc">Sitemiz şu anda bakım modunda. Kısa süre içinde geri döneceğiz.</p></div></body></html>';
            exit;
        }
    } catch(Exception $e) {}
}

require_once __DIR__ . '/functions.php';

function get_user(): ?array {
    global $pdo;
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user !== null) return $user;
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");
    $st->execute([$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;
    return $user;
}

function require_login(string $redirect = '/login.php'): array {
    $user = get_user();
    if (!$user) { set_flash('error','Bu sayfaya erişmek için giriş yapmalısınız.'); redirect($redirect.'?return='.urlencode($_SERVER['REQUEST_URI'])); }
    return $user;
}

function require_admin(): void {
    start_session();
    if (empty($_SESSION['admin_logged_in'])) redirect('/admin/login.php');
}

function login_user(array $user): void {
    global $pdo;
    start_session(); session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['membership'] = $user['membership'];
    try { $pdo->prepare("UPDATE users SET updated_at=NOW() WHERE id=?")->execute([$user['id']]); } catch(Exception $e){}
    log_activity('login','Kullanıcı giriş',$user['id']);
}

function logout_user(): void {
    start_session();
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) log_activity('logout','Çıkış',$uid);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
}

function register_user(array $data): array {
    global $pdo;
    $errors = [];
    $uname = trim($data['username'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $pass  = $data['password'] ?? '';
    $pass2 = $data['password_confirm'] ?? '';
    if (strlen($uname) < 3) $errors[] = 'Kullanıcı adı en az 3 karakter.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $uname)) $errors[] = 'Kullanıcı adı harf, rakam ve _ içerebilir.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli e-posta girin.';
    if (strlen($pass) < 6) $errors[] = 'Şifre en az 6 karakter.';
    if ($pass !== $pass2) $errors[] = 'Şifreler eşleşmiyor.';
    if ($errors) return ['success'=>false,'errors'=>$errors];
    $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
    $st->execute([$uname,$email]);
    if ($st->fetch()) return ['success'=>false,'errors'=>['Kullanıcı adı veya e-posta kullanımda.']];
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
    $st = $pdo->prepare("INSERT INTO users(username,email,password,full_name,phone,birth_date,lang) VALUES(?,?,?,?,?,?,?)");
    $st->execute([$uname,$email,$hash,$data['full_name']??'',$data['phone']??null,!empty($data['birth_date'])?$data['birth_date']:null,$data['lang']??'tr']);
    $id = (int)$pdo->lastInsertId();
    log_activity('register','Yeni kayıt',$id);
    return ['success'=>true,'user_id'=>$id];
}

function attempt_login(string $ident, string $password): array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM users WHERE (email=? OR username=?) LIMIT 1");
    $st->execute([$ident,$ident]); $user = $st->fetch();
    if (!$user) return ['success'=>false,'error'=>'E-posta/kullanıcı adı veya şifre hatalı.'];
    if (!password_verify($password, $user['password'])) return ['success'=>false,'error'=>'E-posta/kullanıcı adı veya şifre hatalı.'];
    if ($user['status']==='banned') return ['success'=>false,'error'=>'Hesabınız askıya alınmıştır.'];
    if ($user['status']==='pending') return ['success'=>false,'error'=>'Hesabınız onaylanmamıştır.'];
    return ['success'=>true,'user'=>$user];
}

function create_password_reset(string $email): ?string {
    global $pdo;
    $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]); if (!$st->fetch()) return null;
    $token = generate_token(32); $expires = date('Y-m-d H:i:s',time()+3600);
    $pdo->prepare("INSERT INTO password_resets(email,token,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE token=VALUES(token),expires_at=VALUES(expires_at),used=0")->execute([$email,$token,$expires]);
    return $token;
}

function verify_reset_token(string $token): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at>NOW() LIMIT 1");
    $st->execute([$token]); return $st->fetch() ?: null;
}

function reset_password(string $token, string $new_password): bool {
    global $pdo;
    $reset = verify_reset_token($token); if (!$reset) return false;
    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost'=>12]);
    $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([$hash,$reset['email']]);
    $pdo->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);
    return true;
}

function is_premium(): bool { $u=get_user(); return $u&&in_array($u['membership'],['premium','ultimate']); }
function is_ultimate(): bool { $u=get_user(); return $u&&$u['membership']==='ultimate'; }

function admin_login(string $username, string $password): bool {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT * FROM admins WHERE username=? AND active=1 LIMIT 1");
        $st->execute([$username]); $admin = $st->fetch();
        if ($admin && password_verify($password,$admin['password'])) {
            start_session(); session_regenerate_id(true);
            $_SESSION['admin_logged_in']=true;
            $_SESSION['admin_username']=$admin['username'];
            $_SESSION['admin_role']=$admin['role'];
            $pdo->prepare("UPDATE admins SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);
            return true;
        }
    } catch(Exception $e) {}
    // Fallback config.php
    $cfg_u=defined('ADMIN_USER')?ADMIN_USER:'admin';
    $cfg_p=defined('ADMIN_PASS')?ADMIN_PASS:'admin123';
    if ($username===$cfg_u && $password===$cfg_p) {
        start_session(); session_regenerate_id(true);
        $_SESSION['admin_logged_in']=true;
        $_SESSION['admin_username']=$username;
        $_SESSION['admin_role']='superadmin';
        return true;
    }
    return false;
}
