-- NakreosStream v11 - Güncelleme SQL
-- Çalıştırma: her satırı ayrı ayrı çalıştır

ALTER TABLE `ads` ADD COLUMN IF NOT EXISTS `skip_after` INT DEFAULT 5;
ALTER TABLE `ads` ADD COLUMN IF NOT EXISTS `target_membership` VARCHAR(20) DEFAULT 'all';
ALTER TABLE `ads` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `uploaded_videos` MODIFY COLUMN `storage` ENUM('local','wasabi','idrive') DEFAULT 'local';

INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('ad_mode','hilltopads');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('show_popular_widget','1');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('age_warning_enabled','0');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('hilltop_enabled','0');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('hilltop_vast_url','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('hilltop_preroll_enabled','1');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('hilltop_skip_after','5');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('hilltop_target','free');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('storage_type','local');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('max_upload_size','500');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('wasabi_key','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('wasabi_secret','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('wasabi_bucket','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('wasabi_region','eu-central-1');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('wasabi_endpoint','https://s3.eu-central-1.wasabisys.com');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('idrive_key','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('idrive_secret','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('idrive_bucket','');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('idrive_region','us-east-1');
INSERT IGNORE INTO `settings`(`key`,`value`) VALUES('idrive_endpoint','https://s3.idrivecloud.io');
