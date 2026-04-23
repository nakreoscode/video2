<?php
// includes/db.php - PDO Veritabanı Bağlantısı

if (!defined('NS_LOADED')) {
    define('NS_LOADED', true);
}

$config_file = __DIR__ . '/../config.php';
if (!file_exists($config_file)) {
    // Kurulum yapılmamışsa yönlendir
    if (!defined('IN_INSTALL')) {
        header('Location: /install/');
        exit;
    }
    return;
}

require_once $config_file;

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4;port=" . DB_PORT;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Veritabanı bağlantı hatası: " . $e->getMessage());
    }
    die("Veritabanı bağlantısı kurulamadı. Lütfen config.php dosyasını kontrol edin.");
}

/**
 * Ayar değeri al
 */
function get_setting(string $key, string $default = ''): string {
    global $pdo;
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $st = $pdo->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
    $st->execute([$key]);
    $row = $st->fetch();
    $cache[$key] = $row ? (string)$row['value'] : $default;
    return $cache[$key];
}

/**
 * Ayar değeri güncelle
 */
function set_setting(string $key, string $value): void {
    global $pdo;
    $st = $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=?");
    $st->execute([$key, $value, $value]);
}
