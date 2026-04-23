<?php
// install/index.php
define('IN_INSTALL', true);
$step  = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Veritabanı bağlantı testi
        $host   = trim($_POST['db_host'] ?? 'localhost');
        $port   = (int)($_POST['db_port'] ?? 3306);
        $name   = trim($_POST['db_name'] ?? '');
        $user   = trim($_POST['db_user'] ?? '');
        $pass   = $_POST['db_pass'] ?? '';
        try {
            $pdo_test = new PDO("mysql:host={$host};dbname={$name};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Tabloları oluştur
            // Ana schema + v9 güncellemesi
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
$sql9 = @file_get_contents(__DIR__ . '/../database/schema_v9.sql');
if($sql9) $sql .= "\n" . $sql9;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) {
                if ($q) $pdo_test->exec($q);
            }
            // config.php yaz
            $admin_user = trim($_POST['admin_user'] ?? 'admin');
            $admin_pass = trim($_POST['admin_pass'] ?? 'admin123');
            $cfg = "<?php\n"
                . "define('DB_HOST', " . var_export($host, true) . ");\n"
                . "define('DB_PORT', " . var_export($port, true) . ");\n"
                . "define('DB_NAME', " . var_export($name, true) . ");\n"
                . "define('DB_USER', " . var_export($user, true) . ");\n"
                . "define('DB_PASS', " . var_export($pass, true) . ");\n"
                . "define('ADMIN_USER', " . var_export($admin_user, true) . ");\n"
                . "define('ADMIN_PASS', " . var_export($admin_pass, true) . ");\n"
                . "define('DEBUG_MODE', false);\n"
                . "define('BASE_URL', " . var_export((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], true) . ");\n";
            file_put_contents(__DIR__ . '/../config.php', $cfg);
            header('Location: ?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Bağlantı hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NakreosStream Kurulum</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { background: #0a0a0a; }
  .gradient-text { background: linear-gradient(135deg, #ef4444, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg">
  <!-- Logo -->
  <div class="text-center mb-8">
    <h1 class="text-4xl font-black gradient-text">🎬 NakreosStream</h1>
    <p class="text-gray-400 mt-2">Kurulum Sihirbazı</p>
  </div>

  <!-- Steps -->
  <div class="flex items-center justify-center gap-2 mb-8">
    <?php foreach ([1=>'Hoş Geldin',2=>'Veritabanı',3=>'Tamamlandı'] as $i=>$label): ?>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
        <?= $step >= $i ? 'bg-red-500 text-white' : 'bg-gray-800 text-gray-500' ?>">
        <?= $step > $i ? '✓' : $i ?>
      </div>
      <span class="text-xs <?= $step >= $i ? 'text-white' : 'text-gray-600' ?>"><?= $label ?></span>
      <?php if ($i < 3): ?><div class="w-8 h-px <?= $step > $i ? 'bg-red-500' : 'bg-gray-700' ?>"></div><?php endif ?>
    </div>
    <?php endforeach ?>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">

  <?php if ($step === 1): ?>
    <h2 class="text-xl font-bold text-white mb-4">Kuruluma Hoş Geldiniz!</h2>
    <p class="text-gray-400 mb-6">NakreosStream kurulumuna başlamadan önce aşağıdaki gereksinimlerin karşılandığından emin olun:</p>
    <?php
    $reqs = [
        'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0', '>='),
        'PDO MySQL'  => extension_loaded('pdo_mysql'),
        'cURL'       => extension_loaded('curl'),
        'JSON'       => extension_loaded('json'),
        'Yazılabilir /' => is_writable(dirname(__DIR__)),
    ];
    ?>
    <div class="space-y-2 mb-6">
    <?php foreach ($reqs as $req => $ok): ?>
      <div class="flex items-center justify-between p-3 rounded-lg bg-gray-800">
        <span class="text-gray-300 text-sm"><?= $req ?></span>
        <span class="<?= $ok ? 'text-green-400' : 'text-red-400' ?> text-sm font-bold">
          <?= $ok ? '✓ Tamam' : '✗ Eksik' ?>
        </span>
      </div>
    <?php endforeach ?>
    </div>
    <?php if (!in_array(false, $reqs)): ?>
      <a href="?step=2" class="block w-full bg-red-500 hover:bg-red-600 text-white text-center py-3 rounded-xl font-bold transition">Devam Et →</a>
    <?php else: ?>
      <p class="text-red-400 text-sm">Lütfen eksik gereksinimleri karşılayın.</p>
    <?php endif ?>

  <?php elseif ($step === 2): ?>
    <h2 class="text-xl font-bold text-white mb-6">Veritabanı Ayarları</h2>
    <?php if ($error): ?><div class="bg-red-500/20 border border-red-500 text-red-400 rounded-lg p-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div><?php endif ?>
    <form method="POST" class="space-y-4">
      <?php
      $fields = [
        ['db_host','Veritabanı Sunucusu','localhost','text'],
        ['db_port','Port','3306','number'],
        ['db_name','Veritabanı Adı','nakreosstream','text'],
        ['db_user','Kullanıcı Adı','root','text'],
        ['db_pass','Şifre','','password'],
        ['admin_user','Admin Kullanıcı Adı','admin','text'],
        ['admin_pass','Admin Şifresi','','password'],
      ];
      foreach ($fields as [$name,$label,$placeholder,$type]):
      ?>
      <div>
        <label class="block text-gray-400 text-sm mb-1"><?= $label ?></label>
        <input type="<?= $type ?>" name="<?= $name ?>" value="<?= htmlspecialchars($_POST[$name] ?? $placeholder) ?>"
          placeholder="<?= $placeholder ?>"
          class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-red-500 text-sm">
      </div>
      <?php endforeach ?>
      <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold transition mt-2">
        Kur ve Devam Et →
      </button>
    </form>

  <?php elseif ($step === 3): ?>
    <div class="text-center">
      <div class="text-6xl mb-4">🎉</div>
      <h2 class="text-2xl font-bold text-white mb-3">Kurulum Tamamlandı!</h2>
      <p class="text-gray-400 mb-6">NakreosStream başarıyla kuruldu. Güvenlik için <code class="text-red-400 bg-gray-800 px-1 rounded">/install</code> klasörünü silin.</p>
      <div class="space-y-3">
        <a href="/admin/" class="block bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-bold transition">Admin Paneline Git</a>
        <a href="/" class="block bg-gray-800 hover:bg-gray-700 text-white py-3 rounded-xl font-bold transition">Siteye Git</a>
      </div>
    </div>
  <?php endif ?>

  </div>
</div>
</body>
</html>
