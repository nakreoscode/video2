-- NakreosStream - Veritabanı Şeması v4 Final
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100),
  `email` VARCHAR(150),
  `role` ENUM('superadmin','admin') DEFAULT 'admin',
  `active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `birth_date` DATE,
  `avatar` VARCHAR(255) DEFAULT 'default.png',
  `bio` TEXT,
  `twitter` VARCHAR(100),
  `instagram` VARCHAR(100),
  `youtube` VARCHAR(100),
  `tiktok` VARCHAR(100),
  `membership` ENUM('free','premium','ultimate') DEFAULT 'free',
  `balance` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('active','banned','pending') DEFAULT 'active',
  `sms_login_enabled` TINYINT(1) DEFAULT 0,
  `two_fa_enabled` TINYINT(1) DEFAULT 0,
  `lang` VARCHAR(5) DEFAULT 'tr',
  `channel_id` INT UNSIGNED DEFAULT NULL,
  `theme` VARCHAR(20) DEFAULT 'dark' COMMENT 'dark,light,netflix,twitch,spotify,cinema,minimal',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `token` VARCHAR(100) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sms_verifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `type` ENUM('login','register','phone_verify') DEFAULT 'login',
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platforms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `api_key` TEXT,
  `api_secret` TEXT,
  `access_token` TEXT,
  `api_key2` TEXT DEFAULT NULL,
  `api_key3` TEXT DEFAULT NULL,
  `api_key4` TEXT DEFAULT NULL,
  `api_key_index` TINYINT DEFAULT 0,
  `color` VARCHAR(20) DEFAULT '#ff0000',
  `icon` VARCHAR(10) DEFAULT '📺',
  `description` TEXT,
  `active` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) DEFAULT '🎬',
  `image` VARCHAR(255),
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `uploaded_videos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `tags` TEXT,
  `type` ENUM('normal','short') DEFAULT 'normal',
  `file_path` VARCHAR(500),
  `thumbnail` VARCHAR(500),
  `storage` ENUM('local','wasabi') DEFAULT 'wasabi',
  `duration` INT DEFAULT 0,
  `file_size` BIGINT DEFAULT 0,
  `format` VARCHAR(20),
  `status` ENUM('pending','processing','active','rejected') DEFAULT 'pending',
  `views` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `video_categories` (
  `video_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`video_id`,`category_id`),
  FOREIGN KEY (`video_id`) REFERENCES `uploaded_videos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_videos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `platform` VARCHAR(50) NOT NULL,
  `video_id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255),
  `thumbnail` VARCHAR(500),
  `channel` VARCHAR(150),
  `duration` INT DEFAULT 0,
  `type` ENUM('normal','short','image') DEFAULT 'normal',
  `saved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_user_video` (`user_id`,`platform`,`video_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `watch_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `platform` VARCHAR(50) NOT NULL,
  `video_id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255),
  `thumbnail` VARCHAR(500),
  `channel` VARCHAR(150),
  `watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `search_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED,
  `query` VARCHAR(255) NOT NULL,
  `platform` VARCHAR(50) DEFAULT 'all',
  `results_count` INT DEFAULT 0,
  `searched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `platform` VARCHAR(50) NOT NULL,
  `video_id` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('active','hidden','deleted') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `likes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `platform` VARCHAR(50) NOT NULL,
  `video_id` VARCHAR(100) NOT NULL,
  `liked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_like` (`user_id`,`platform`,`video_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `follows` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `follower_id` INT UNSIGNED NOT NULL,
  `following_id` INT UNSIGNED NOT NULL,
  `followed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_follow` (`follower_id`,`following_id`),
  FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `playlists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `visibility` ENUM('public','private') DEFAULT 'public',
  `thumbnail` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `playlist_videos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `playlist_id` INT UNSIGNED NOT NULL,
  `platform` VARCHAR(50) NOT NULL,
  `video_id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255),
  `thumbnail` VARCHAR(500),
  `channel` VARCHAR(150),
  `duration` INT DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`playlist_id`) REFERENCES `playlists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `method` ENUM('paytr','shopier','bank','crypto') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'TRY',
  `plan` ENUM('premium','ultimate') DEFAULT 'premium',
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `transaction_id` VARCHAR(255),
  `receipt_info` TEXT,
  `notes` TEXT,
  `approved_by` INT UNSIGNED,
  `approved_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('image','vast') DEFAULT 'image',
  `image_url` VARCHAR(500),
  `vast_code` TEXT,
  `link` VARCHAR(500),
  `duration` INT DEFAULT 5,
  `active` TINYINT(1) DEFAULT 1,
  `impressions` INT UNSIGNED DEFAULT 0,
  `clicks` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `api_key` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(100),
  `permissions` TEXT,
  `rate_limit` INT DEFAULT 100,
  `usage_count` INT UNSIGNED DEFAULT 0,
  `last_used_at` DATETIME,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255),
  `message` TEXT,
  `data` TEXT,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip` VARCHAR(50),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `events` TEXT,
  `secret` VARCHAR(100),
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Varsayılan veriler
INSERT IGNORE INTO `categories` (`name`,`slug`,`icon`,`sort_order`) VALUES
('Müzik','muzik','🎵',1),('Film','film','🎬',2),('Eğitim','egitim','📚',3),
('Oyun','oyun','🎮',4),('Spor','spor','⚽',5),('Haber','haber','📰',6),
('Eğlence','eglence','😄',7),('Genel','genel','📌',8);

INSERT IGNORE INTO `platforms` (`slug`,`name`,`active`) VALUES
('youtube','YouTube',0),('dailymotion','Dailymotion',0),('vimeo','Vimeo',0),
('twitch','Twitch',0),('tiktok','TikTok',0),('facebook','Facebook',0),
('instagram','Instagram',0),('unsplash','Unsplash',0),('pixabay','Pixabay',0);

INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
('site_title','NakreosStream'),('site_description','Video Arama ve İzleme Platformu'),
('site_keywords','video,stream,izle,ara'),('site_logo',''),
('sms_active','0'),('sms_username',''),('sms_password',''),('sms_sender','NakreosStr'),
('sms_login_required','0'),
('paytr_merchant_id',''),('paytr_merchant_key',''),('paytr_merchant_salt',''),
('shopier_api_key',''),('shopier_api_secret',''),
('bank_iban',''),('bank_name',''),('bank_owner',''),
('crypto_address',''),('crypto_network','TRC20'),
('active_gateway','paytr'),('payment_methods','bank,crypto'),
('wasabi_key',''),('wasabi_secret',''),('wasabi_bucket',''),
('wasabi_region','eu-central-1'),('wasabi_endpoint','https://s3.eu-central-1.wasabisys.com'),
('max_upload_size','500'),('premium_price','99.00'),('ultimate_price','199.00'),
('ad_duration','5'),('ad_position','preroll'),
('registration_open','1'),('maintenance_mode','0'),
('active_theme','dark'),('show_categories','1'),('show_trending','1'),
('show_shorts','1'),('show_images','1'),('mail_from','');

-- Varsayılan admin (şifre: admin123)
INSERT IGNORE INTO `admins` (`username`,`password`,`full_name`,`role`) VALUES
('admin','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Yönetici','superadmin');

-- ═══════════════════════════════════════
-- v8 YENİ TABLOLAR
-- ═══════════════════════════════════════

-- Kanallar tablosu
CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `avatar` VARCHAR(255),
  `banner` VARCHAR(255),
  `category_id` INT UNSIGNED DEFAULT NULL,
  `is_verified` TINYINT(1) DEFAULT 0,
  `subscriber_count` INT UNSIGNED DEFAULT 0,
  `video_count` INT UNSIGNED DEFAULT 0,
  `view_count` BIGINT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin duyuruları
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `icon` VARCHAR(10) DEFAULT '📢',
  `type` ENUM('info','success','warning','danger') DEFAULT 'info',
  `target` ENUM('all','premium','ultimate','free') DEFAULT 'all',
  `active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Duyuru okuma kaydı
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `announcement_id` INT UNSIGNED NOT NULL,
  `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_announcement` (`user_id`,`announcement_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Üyelik paketleri
CREATE TABLE IF NOT EXISTS `membership_plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` ENUM('free','premium','ultimate') NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `color` VARCHAR(20) DEFAULT '#666',
  `icon` VARCHAR(10) DEFAULT '⚪',
  `features` TEXT COMMENT 'JSON array',
  `max_upload_count` INT DEFAULT 0,
  `max_upload_size_mb` INT DEFAULT 100,
  `ads_free` TINYINT(1) DEFAULT 0,
  `api_access` TINYINT(1) DEFAULT 0,
  `download_videos` TINYINT(1) DEFAULT 0,
  `publish_ads` TINYINT(1) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tema özelleştirme tablosu
CREATE TABLE IF NOT EXISTS `themes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255),
  `icon` VARCHAR(10) DEFAULT '🎨',
  `css_file` VARCHAR(100) DEFAULT NULL COMMENT 'assets/themes/ altındaki CSS dosyası',
  `preview_color` VARCHAR(20) DEFAULT '#ff0000',
  `active` TINYINT(1) DEFAULT 1,
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════
-- v8 VARSAYILAN VERİLER
-- ═══════════════════════════════════════

-- Üyelik paketleri
INSERT IGNORE INTO `membership_plans` (`slug`,`name`,`price`,`color`,`icon`,`features`,`ads_free`,`api_access`,`download_videos`,`publish_ads`,`max_upload_count`,`max_upload_size_mb`) VALUES
('free','Ücretsiz',0.00,'#666','⚪','["Video izle","Arama yap","Yorum yap","Playlist oluştur"]',0,0,0,0,5,100),
('premium','Premium',99.00,'#1a73e8','💙','["Reklamsız izle","Sınırsız kaydet","API erişimi","Öncelikli destek","Video yükle"]',1,1,0,0,50,500),
('ultimate','Ultimate',199.00,'#7c3aed','💜','["Tüm Premium özellikler","Video indir","Reklam yayınla","Sınırsız yükleme","Özel rozet"]',1,1,1,1,0,2000);

-- Temalar
INSERT IGNORE INTO `themes` (`slug`,`name`,`description`,`icon`,`css_file`,`preview_color`,`active`,`is_default`,`sort_order`) VALUES
('dark','Koyu (YouTube)','YouTube tarzı koyu tema','🌙',NULL,'#ff0000',1,1,1),
('light','Açık (YouTube)','YouTube tarzı açık tema','☀️',NULL,'#ff0000',1,0,2),
('netflix','Koyu Kırmızı','Netflix tarzı koyu tema','🎬',NULL,'#e50914',1,0,3),
('twitch','Twitch','Twitch tarzı mor tema','🎮',NULL,'#9147ff',1,0,4),
('spotify','Spotify','Spotify tarzı yeşil tema','🎵',NULL,'#1db954',1,0,5),
('cinema','Cinema','Plex/Cinema tarzı premium tema','🎥','cinema.css','#e50914',1,0,6),
('minimal','Minimal','Modern minimal açık tema','✨','minimal.css','#5b5cf6',1,0,7);

-- Platform varsayılan renkler ve ikonlar
UPDATE `platforms` SET `color`='#ff0000', `icon`='▶' WHERE `slug`='youtube';
UPDATE `platforms` SET `color`='#0066dc', `icon`='📺' WHERE `slug`='dailymotion';
UPDATE `platforms` SET `color`='#1ab7ea', `icon`='🎥' WHERE `slug`='vimeo';
UPDATE `platforms` SET `color`='#9147ff', `icon`='🎮' WHERE `slug`='twitch';
UPDATE `platforms` SET `color`='#ff0050', `icon`='🎵' WHERE `slug`='tiktok';
UPDATE `platforms` SET `color`='#4267b2', `icon`='📘' WHERE `slug`='facebook';
UPDATE `platforms` SET `color`='#e1306c', `icon`='📷' WHERE `slug`='instagram';
UPDATE `platforms` SET `color`='#111', `icon`='🖼' WHERE `slug`='unsplash';
UPDATE `platforms` SET `color`='#2ec66e', `icon`='🌿' WHERE `slug`='pixabay';

-- v8 ayarları
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES
('active_theme','dark'),
('available_themes','dark,light,netflix,twitch,spotify,cinema,minimal');
