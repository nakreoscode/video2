<?php
// includes/functions.php

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url, int $code = 302): void {
    if (headers_sent()) {
        echo '<script>window.location.href="' . addslashes($url) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        exit;
    }
    header("Location: $url", true, $code);
    exit;
}

function t(string $key, array $params = []): string {
    global $lang;
    if (!isset($lang) || !is_array($lang)) $lang = [];
    $str = $lang[$key] ?? $key;
    foreach ($params as $k => $v) $str = str_replace('{' . $k . '}', $v, $str);
    return $str;
}

function load_language(string $code = 'tr'): void {
    global $lang;
    $file = __DIR__ . '/../languages/' . $code . '.php';
    if (!file_exists($file)) $file = __DIR__ . '/../languages/tr.php';
    $lang = include $file;
}

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime'=>86400*30,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function format_duration(int $secs): string {
    if ($secs <= 0) return '0:00';
    $h = floor($secs / 3600); $m = floor(($secs % 3600) / 60); $s = $secs % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function format_views(int $n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000) return number_format($n / 1_000, 1) . 'B';
    return (string)$n;
}

function format_date(string $date, string $format = 'd.m.Y'): string {
    return date($format, strtotime($date));
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Az önce';
    if ($diff < 3600) return floor($diff/60) . ' dakika önce';
    if ($diff < 86400) return floor($diff/3600) . ' saat önce';
    if ($diff < 2592000) return floor($diff/86400) . ' gün önce';
    if ($diff < 31536000) return floor($diff/2592000) . ' ay önce';
    return floor($diff/31536000) . ' yıl önce';
}

function slugify(string $text): string {
    $map = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u'];
    $text = mb_strtolower(strtr($text, $map), 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    return preg_replace('/[\s-]+/', '-', trim($text));
}

function log_activity(string $action, string $desc = '', ?int $user_id = null): void {
    global $pdo;
    try {
        $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pdo->prepare("INSERT INTO activity_logs(user_id,action,description,ip,user_agent) VALUES(?,?,?,?,?)")->execute([$uid,$action,$desc,$ip,$ua]);
    } catch (Exception $e) {}
}

function calculate_age(?string $birth_date): ?int {
    if (!$birth_date) return null;
    return (int)(new DateTime($birth_date))->diff(new DateTime())->y;
}

function format_filesize(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes/1073741824,2).' GB';
    if ($bytes >= 1048576) return number_format($bytes/1048576,2).' MB';
    if ($bytes >= 1024) return number_format($bytes/1024,2).' KB';
    return $bytes.' B';
}

function avatar_url(string $avatar): string {
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    if ($avatar && $avatar !== 'default.png' && file_exists(__DIR__.'/../assets/img/avatars/'.$avatar))
        return '/assets/img/avatars/'.$avatar;
    return '/assets/img/default-avatar.svg';
}

function set_flash(string $type, string $message): void {
    start_session();
    $_SESSION['flash'] = ['type'=>$type,'message'=>$message];
}

function get_flash(): ?array {
    start_session();
    if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function get_ip(): string { return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function generate_token(int $length = 32): string { return bin2hex(random_bytes($length)); }
function detect_video_type(int $duration): string { return ($duration > 0 && $duration <= 60) ? 'short' : 'normal'; }

function send_notification(int $user_id, string $type, string $title, string $message, array $data = []): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,data) VALUES(?,?,?,?,?)")->execute([$user_id,$type,$title,$message,json_encode($data)]);
    } catch (Exception $e) {}
}

// Tema CSS değişkenlerini döndür
function get_theme_vars(string $theme): string {
    return match($theme) {
        'light'   => ':root{--bg:#fff;--bg2:#f2f2f2;--bg3:#e5e5e5;--border:#d3d3d3;--acc:#ff0000;--acc2:#cc0000;--text:#0f0f0f;--text2:#606060;--text3:#909090;--nav:#fff;--shadow:rgba(0,0,0,.15);--inp:#f8f8f8;--hov:rgba(0,0,0,.05)}',
        'netflix' => ':root{--bg:#141414;--bg2:#1f1f1f;--bg3:#2a2a2a;--border:#333;--acc:#e50914;--acc2:#b20710;--text:#fff;--text2:#b3b3b3;--text3:#6d6d6d;--nav:#141414;--shadow:rgba(0,0,0,.7);--inp:#2a2a2a;--hov:rgba(229,9,20,.1)}',
        'twitch'  => ':root{--bg:#0e0e10;--bg2:#18181b;--bg3:#1f1f23;--border:#2d2d35;--acc:#9147ff;--acc2:#7d2ff0;--text:#efeff1;--text2:#adadb8;--text3:#6b6b78;--nav:#0e0e10;--shadow:rgba(0,0,0,.7);--inp:#1f1f23;--hov:rgba(145,71,255,.15)}',
        'spotify' => ':root{--bg:#121212;--bg2:#181818;--bg3:#282828;--border:#333;--acc:#1db954;--acc2:#1aa34a;--text:#fff;--text2:#b3b3b3;--text3:#6d6d6d;--nav:#000;--shadow:rgba(0,0,0,.7);--inp:#282828;--hov:rgba(29,185,84,.1)}',
        'cinema'  => ':root{--bg:#0a0a0f;--bg2:#12121a;--bg3:#1a1a26;--border:#252535;--acc:#e50914;--acc2:#b20710;--text:#fff;--text2:#a0a0b8;--text3:#606078;--nav:#0a0a0f;--shadow:rgba(0,0,0,.8);--inp:#1a1a26;--hov:rgba(229,9,20,.08)}',
        'minimal' => ':root{--bg:#f5f5f7;--bg2:#ffffff;--bg3:#ebebed;--border:#e0e0e5;--acc:#5b5cf6;--acc2:#4a4be0;--text:#1a1a2e;--text2:#6b7280;--text3:#9ca3af;--nav:#fff;--shadow:rgba(0,0,0,.08);--inp:#fff;--hov:rgba(91,92,246,.06)}',
        default   => ':root{--bg:#0f0f0f;--bg2:#181818;--bg3:#272727;--border:#3f3f3f;--acc:#ff0000;--acc2:#cc0000;--text:#f1f1f1;--text2:#aaa;--text3:#717171;--nav:#0f0f0f;--shadow:rgba(0,0,0,.6);--inp:#121212;--hov:rgba(255,255,255,.08)}',
    };
}

// Aktif tema al (cookie > user > db > default)
function get_active_theme(): string {
    // İzin verilen temaları DB'den al
    $allowed = ['dark','light','netflix','twitch','spotify','cinema','minimal'];
    global $pdo;
    try {
        $slugs = $pdo->query("SELECT slug FROM themes WHERE active=1")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($slugs)) $allowed = $slugs;
    } catch(Exception $e){}
    if (!empty($_COOKIE['ns_theme']) && in_array($_COOKIE['ns_theme'],$allowed)) return $_COOKIE['ns_theme'];
    global $pdo;
    try {
        $st=$pdo->query("SELECT value FROM settings WHERE `key`='active_theme' LIMIT 1");
        $v=$st->fetchColumn();
        if ($v && in_array($v,$allowed)) return $v;
    } catch(Exception $e){}
    return 'dark';
}

// Duyuruları kullanıcıya gönder
function send_announcement_notifications(int $announcement_id, string $target='all'): void {
    global $pdo;
    try {
        $ann = $pdo->prepare("SELECT * FROM announcements WHERE id=? LIMIT 1");
        $ann->execute([$announcement_id]); $ann=$ann->fetch();
        if (!$ann) return;
        $where = $target==='all' ? '' : "WHERE membership='{$target}'";
        $users = $pdo->query("SELECT id FROM users {$where}")->fetchAll();
        foreach ($users as $u) {
            $pdo->prepare("INSERT IGNORE INTO notifications(user_id,type,title,message,icon,link,is_read) VALUES(?,?,?,?,?,?,0)")
                ->execute([$u['id'],'announcement',$ann['title'],$ann['message'],$ann['icon'],'/']);
        }
    } catch(Exception $e){}
}

// Kullanıcı kanalını al veya oluştur
function get_user_channel(int $user_id): ?array {
    global $pdo;
    try {
        $st=$pdo->prepare("SELECT * FROM channels WHERE user_id=? LIMIT 1");
        $st->execute([$user_id]); return $st->fetch() ?: null;
    } catch(Exception $e){ return null; }
}

// Membership plan detayları
function get_plan(string $slug): array {
    global $pdo;
    try {
        $st=$pdo->prepare("SELECT * FROM membership_plans WHERE slug=? LIMIT 1");
        $st->execute([$slug]); $r=$st->fetch();
        if ($r) { $r['features_arr']=json_decode($r['features'],true)??[]; return $r; }
    } catch(Exception $e){}
    return ['slug'=>$slug,'name'=>ucfirst($slug),'price'=>0,'features'=>'[]','features_arr'=>[],'color'=>'#666','icon'=>'⚪'];
}
