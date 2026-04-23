<?php
$page_title = 'Tema Yönetimi';
require_once __DIR__ . '/includes/header.php';

// Themes tablosu yoksa oluştur
try {
    $pdo->query("SELECT id FROM themes LIMIT 1");
} catch (Exception $e) {
    $pdo->query("CREATE TABLE IF NOT EXISTS `themes` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(30) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255),
        `icon` VARCHAR(10) DEFAULT '🎨',
        `css_file` VARCHAR(100) DEFAULT NULL,
        `preview_color` VARCHAR(20) DEFAULT '#ff0000',
        `active` TINYINT(1) DEFAULT 1,
        `is_default` TINYINT(1) DEFAULT 0,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Varsayılan temaları ekle
    $default_themes = [
        ['dark','Koyu (YouTube)','YouTube tarzı koyu tema','🌙',null,'#ff0000',1,1,1],
        ['light','Açık (YouTube)','YouTube tarzı açık tema','☀️',null,'#ff0000',1,0,2],
        ['netflix','Koyu Kırmızı','Netflix tarzı koyu tema','🎬',null,'#e50914',1,0,3],
        ['twitch','Twitch','Twitch tarzı mor tema','🎮',null,'#9147ff',1,0,4],
        ['spotify','Spotify','Spotify tarzı yeşil tema','🎵',null,'#1db954',1,0,5],
        ['cinema','Cinema','Plex/Cinema tarzı premium tema','🎥','cinema.css','#e50914',1,0,6],
        ['minimal','Minimal','Modern minimal açık tema','✨','minimal.css','#5b5cf6',1,0,7],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO themes(slug,name,description,icon,css_file,preview_color,active,is_default,sort_order) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach ($default_themes as $t) $ins->execute($t);
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    // Tema güncelle (ad, açıklama, ikon, renk)
    if ($action === 'update') {
        $id = (int)($_POST['theme_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🎨');
        $color = trim($_POST['preview_color'] ?? '#ff0000');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($id && $name) {
            $pdo->prepare("UPDATE themes SET name=?,description=?,icon=?,preview_color=?,active=? WHERE id=?")
                ->execute([$name, $desc, $icon, $color, $active, $id]);
            set_flash('success', 'Tema güncellendi.');
        }
    }

    // Varsayılan tema seç
    if ($action === 'set_default') {
        $slug = trim($_POST['slug'] ?? '');
        if ($slug) {
            $pdo->query("UPDATE themes SET is_default=0");
            $pdo->prepare("UPDATE themes SET is_default=1 WHERE slug=?")->execute([$slug]);
            set_setting('active_theme', $slug);
            set_flash('success', 'Varsayılan tema değiştirildi.');
        }
    }

    // Tema aktif/pasif
    if ($action === 'toggle') {
        $id = (int)($_POST['theme_id'] ?? 0);
        $pdo->prepare("UPDATE themes SET active=1-active WHERE id=?")->execute([$id]);
    }

    redirect('/admin/themes.php');
}

$themes = $pdo->query("SELECT * FROM themes ORDER BY sort_order")->fetchAll();
$active_theme = get_setting('active_theme', 'dark');
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <h2 style="font-size:16px;font-weight:600">🎨 Tema Yönetimi</h2>
  <p style="font-size:13px;color:var(--text2)">Tema adlarını, ikonlarını ve görünümlerini yönetin</p>
</div>

<!-- Aktif Tema Seçimi -->
<div class="ns-card" style="padding:20px;margin-bottom:20px">
  <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">🌐 Varsayılan Tema</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
    <?php foreach ($themes as $t): if (!$t['active']) continue; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="set_default">
      <input type="hidden" name="slug" value="<?= e($t['slug']) ?>">
      <button type="submit" style="width:100%;padding:14px 10px;border-radius:10px;border:2px solid <?= $active_theme===$t['slug']?'var(--acc)':'var(--border)' ?>;background:<?= $active_theme===$t['slug']?'rgba(255,0,0,.06)':'var(--bg3)' ?>;cursor:pointer;transition:.2s;font-family:'Roboto',sans-serif">
        <div style="display:flex;align-items:center;gap:6px;justify-content:center;margin-bottom:6px">
          <div style="width:14px;height:14px;border-radius:50%;background:<?= e($t['preview_color']) ?>;flex-shrink:0"></div>
          <span style="font-size:16px"><?= e($t['icon']) ?></span>
        </div>
        <p style="font-size:12px;font-weight:600;color:var(--text)"><?= e($t['name']) ?></p>
        <?php if ($active_theme===$t['slug']): ?>
        <p style="font-size:10px;color:var(--acc);margin-top:2px">● Aktif</p>
        <?php endif ?>
      </button>
    </form>
    <?php endforeach ?>
  </div>
</div>

<!-- Tema Listesi ve Düzenleme -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px">
  <?php foreach ($themes as $t): ?>
  <div class="ns-card" style="padding:18px;border-top:3px solid <?= e($t['preview_color']) ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:22px"><?= e($t['icon']) ?></span>
        <div>
          <p style="font-size:14px;font-weight:600"><?= e($t['name']) ?></p>
          <p style="font-size:11px;color:var(--text3);font-family:monospace"><?= e($t['slug']) ?></p>
        </div>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <?php if ($active_theme===$t['slug']): ?>
        <span style="font-size:10px;background:var(--acc);color:#fff;padding:2px 8px;border-radius:99px;font-weight:700">Aktif</span>
        <?php endif ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="theme_id" value="<?= $t['id'] ?>">
          <button class="btn sm <?= $t['active']?'':'green' ?>" style="border-radius:6px;padding:4px 10px;font-size:11px">
            <?= $t['active'] ? 'Kapat' : 'Aç' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Düzenleme Formu -->
    <form method="POST" style="display:flex;flex-direction:column;gap:8px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="theme_id" value="<?= $t['id'] ?>">

      <div style="display:grid;grid-template-columns:1fr 50px 50px;gap:6px">
        <div>
          <label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Tema Adı</label>
          <input type="text" name="name" value="<?= e($t['name']) ?>" class="ns-input" style="border-radius:7px;font-size:13px;padding:7px 10px">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">İkon</label>
          <input type="text" name="icon" value="<?= e($t['icon']) ?>" class="ns-input" style="border-radius:7px;font-size:18px;text-align:center;padding:5px 4px">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Renk</label>
          <input type="color" name="preview_color" value="<?= e($t['preview_color']) ?>" style="width:100%;height:34px;border-radius:7px;border:1px solid var(--border);cursor:pointer;background:none;padding:2px">
        </div>
      </div>

      <div>
        <label style="font-size:11px;color:var(--text2);display:block;margin-bottom:3px">Açıklama</label>
        <input type="text" name="description" value="<?= e($t['description']??'') ?>" class="ns-input" style="border-radius:7px;font-size:12px;padding:7px 10px" placeholder="Tema hakkında kısa açıklama">
      </div>

      <?php if ($t['css_file']): ?>
      <p style="font-size:11px;color:var(--text3)">📁 CSS: assets/themes/<?= e($t['css_file']) ?></p>
      <?php endif ?>

      <div style="display:flex;gap:6px;align-items:center">
        <button type="submit" class="btn sm" style="border-radius:6px">💾 Kaydet</button>
        <?php if ($active_theme !== $t['slug']): ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_default">
          <input type="hidden" name="slug" value="<?= e($t['slug']) ?>">
          <button class="btn sm green" style="border-radius:6px">✓ Varsayılan Yap</button>
        </form>
        <?php endif ?>
      </div>
    </form>
  </div>
  <?php endforeach ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
