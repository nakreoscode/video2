<?php
// category.php - /? adresine yönlendir (index.php tüm işi yapıyor)
require_once __DIR__ . '/includes/functions.php';
$slug = trim($_GET['slug'] ?? '');
if ($slug) {
    redirect('/?cat=' . urlencode($slug));
} else {
    redirect('/');
}
