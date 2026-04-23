-- NakreosStream v7 - Schema Update
-- Mevcut schema.sql'e ek olarak çalıştırın

-- Platforms tablosuna çoklu API key desteği
ALTER TABLE `platforms` 
  ADD COLUMN IF NOT EXISTS `api_key2` TEXT DEFAULT NULL AFTER `api_key`,
  ADD COLUMN IF NOT EXISTS `api_key3` TEXT DEFAULT NULL AFTER `api_key2`,
  ADD COLUMN IF NOT EXISTS `api_key4` TEXT DEFAULT NULL AFTER `api_key3`,
  ADD COLUMN IF NOT EXISTS `api_key_index` TINYINT DEFAULT 0 AFTER `api_key4`,
  ADD COLUMN IF NOT EXISTS `color` VARCHAR(20) DEFAULT '#ff0000' AFTER `active`,
  ADD COLUMN IF NOT EXISTS `icon` VARCHAR(10) DEFAULT '📺' AFTER `color`,
  ADD COLUMN IF NOT EXISTS `description` TEXT AFTER `icon`;

-- Kanallar tablosu (madde 5)
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

-- Users tablosuna kanal ve tema alanları
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `channel_id` INT UNSIGNED DEFAULT NULL AFTER `tiktok`,
  ADD COLUMN IF NOT EXISTS `theme` VARCHAR(20) DEFAULT 'dark' AFTER `lang`,
  ADD COLUMN IF NOT EXISTS `sms_login_enabled` TINYINT(1) DEFAULT 0 AFTER `theme`;

-- Bildirimler tablosu güncelle
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'system' AFTER `id`,
  ADD COLUMN IF NOT EXISTS `link` VARCHAR(255) DEFAULT NULL AFTER `message`,
  ADD COLUMN IF NOT EXISTS `icon` VARCHAR(10) DEFAULT '🔔' AFTER `link`;

-- Admin duyuruları tablosu (madde 9)
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `icon` VARCHAR(10) DEFAULT '📢',
  `type` ENUM('info','success','warning','danger') DEFAULT 'info',
  `target` ENUM('all','premium','ultimate','free') DEFAULT 'all',
  `active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Duyuru okuma tablosu
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `announcement_id` INT UNSIGNED NOT NULL,
  `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_announcement` (`user_id`,`announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paket özellikleri tablosu (madde 4)
CREATE TABLE IF NOT EXISTS `membership_plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` ENUM('free','premium','ultimate') NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `color` VARCHAR(20) DEFAULT '#666',
  `icon` VARCHAR(10) DEFAULT '⚪',
  `features` TEXT COMMENT 'JSON array of features',
  `max_upload_count` INT DEFAULT 0 COMMENT '0=unlimited',
  `max_upload_size_mb` INT DEFAULT 100,
  `ads_free` TINYINT(1) DEFAULT 0,
  `api_access` TINYINT(1) DEFAULT 0,
  `download_videos` TINYINT(1) DEFAULT 0,
  `publish_ads` TINYINT(1) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan paket verileri
INSERT IGNORE INTO `membership_plans` (`slug`,`name`,`price`,`color`,`icon`,`features`,`ads_free`,`api_access`,`download_videos`,`publish_ads`,`max_upload_count`,`max_upload_size_mb`) VALUES
('free','Ücretsiz',0.00,'#666','⚪','["Video izle","Arama yap","Yorum yap","Playlist oluştur"]',0,0,0,0,5,100),
('premium','Premium',99.00,'#1a73e8','💙','["Reklamsız izle","Sınırsız kaydet","API erişimi","Öncelikli destek","Video yükle"]',1,1,0,0,50,500),
('ultimate','Ultimate',199.00,'#7c3aed','💜','["Tüm Premium özellikler","Video indir","Reklam yayınla","Sınırsız yükleme","Öncelikli destek","Özel rozet"]',1,1,1,1,0,2000);

-- Sistem ayarlarına tema ayarı ekle
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES
('active_theme','dark'),
('site_theme_youtube','youtube'),
('available_themes','dark,light,netflix,twitch,spotify'),
('premium_price','99.00'),
('ultimate_price','199.00');
