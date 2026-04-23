<?php
// api/v1.php - NakreosStream REST API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/VideoSearch.php';

function api_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function api_success(array $data): void {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// API key doğrulama
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
if (!$api_key) api_error('API key gerekli.', 401);

$key_row = $pdo->prepare("SELECT ak.*, u.id as user_id, u.username, u.membership FROM api_keys ak JOIN users u ON u.id=ak.user_id WHERE ak.api_key=? AND ak.active=1 LIMIT 1");
$key_row->execute([$api_key]);
$key_row = $key_row->fetch();
if (!$key_row) api_error('Geçersiz API key.', 401);

// Rate limit kontrolü
$hour_ago = date('Y-m-d H:i:s', time() - 3600);
$usage = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id=? AND action='api_request' AND created_at > ?");
$usage->execute([$key_row['user_id'], $hour_ago]);
if ($usage->fetchColumn() >= $key_row['rate_limit']) {
    api_error("Rate limit aşıldı. {$key_row['rate_limit']} istek/saat.", 429);
}

// Kullanım sayacı güncelle
$pdo->prepare("UPDATE api_keys SET usage_count=usage_count+1, last_used_at=NOW() WHERE id=?")->execute([$key_row['id']]);
log_activity('api_request', $_GET['action'] ?? '', $key_row['user_id']);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'search':
        $q        = trim($_GET['q'] ?? '');
        $platform = $_GET['platform'] ?? 'all';
        $type     = $_GET['type'] ?? 'all';
        $limit    = min((int)($_GET['limit'] ?? 10), 50);
        if (!$q) api_error('q parametresi gerekli.');
        $searcher = new VideoSearch($pdo);
        $results  = $searcher->search($q, $platform, $type, $limit);
        api_success(['query' => $q, 'count' => count($results), 'results' => $results]);
        break;

    case 'trending':
        $platform = $_GET['platform'] ?? 'youtube';
        $limit    = min((int)($_GET['limit'] ?? 10), 50);
        $searcher = new VideoSearch($pdo);
        $results  = $searcher->getTrending($platform, $limit);
        api_success(['count' => count($results), 'results' => $results]);
        break;

    case 'user':
        $user = $pdo->prepare("SELECT id,username,full_name,membership,created_at FROM users WHERE id=? LIMIT 1");
        $user->execute([$key_row['user_id']]);
        api_success(['user' => $user->fetch()]);
        break;

    case 'saved':
        $limit  = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        $saved  = $pdo->prepare("SELECT * FROM saved_videos WHERE user_id=? ORDER BY saved_at DESC LIMIT ? OFFSET ?");
        $saved->execute([$key_row['user_id'], $limit, $offset]);
        api_success(['saved' => $saved->fetchAll()]);
        break;

    case 'playlists':
        $playlists = $pdo->prepare("SELECT * FROM playlists WHERE user_id=? AND visibility='public' ORDER BY created_at DESC");
        $playlists->execute([$key_row['user_id']]);
        api_success(['playlists' => $playlists->fetchAll()]);
        break;

    case 'categories':
        $cats = $pdo->query("SELECT id,name,slug,icon FROM categories WHERE active=1 ORDER BY sort_order")->fetchAll();
        api_success(['categories' => $cats]);
        break;

    case 'platforms':
        $platforms = $pdo->query("SELECT slug,name,active FROM platforms ORDER BY name")->fetchAll();
        api_success(['platforms' => $platforms]);
        break;

    default:
        api_success([
            'version'   => '1.0',
            'endpoints' => ['search','trending','user','saved','playlists','categories','platforms'],
            'docs'      => 'GET /api/v1.php?action=search&q=keyword&api_key=YOUR_KEY',
        ]);
}
