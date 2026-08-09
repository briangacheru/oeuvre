-- Lets a superadmin choose where NEW task-attachment uploads are stored
-- (DigitalOcean Spaces vs local disk / cPanel) from sudo/settings.php, via
-- storage-helper.php's get_storage_provider()/storage_upload_file()/
-- storage_delete_file(). Seeded to 'digitalocean' since that's the only
-- backend that existed before this migration - so nothing changes for
-- existing installs until an admin actually switches it.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_09_add_storage_settings.sql

CREATE TABLE IF NOT EXISTS `tbl_storage_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider` VARCHAR(20) NOT NULL DEFAULT 'digitalocean',
    `updated_by` VARCHAR(191) NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL
);

INSERT INTO `tbl_storage_settings` (`id`, `provider`)
SELECT 1, 'digitalocean' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `tbl_storage_settings` WHERE `id` = 1);
