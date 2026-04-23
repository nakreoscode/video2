<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
start_session();
logout_user();
set_flash('success', 'Başarıyla çıkış yaptınız.');
redirect('/login.php');
