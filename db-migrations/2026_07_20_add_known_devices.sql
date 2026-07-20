-- New-device login detection: a device (browser) becomes "known" only after
-- completing a full login (password, plus the emailed verification code when
-- required). A long-lived, unguessable cookie then lets that same browser
-- skip the code for 30 days, refreshed on each recognized login. Logging in
-- from a browser with no matching row here always requires the code.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_20_add_known_devices.sql

CREATE TABLE IF NOT EXISTS `tblwriter_known_devices` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `writer_email` VARCHAR(255) NOT NULL,
    `device_token_hash` CHAR(64) NOT NULL,
    `device_label` VARCHAR(255) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `first_seen` DATETIME NOT NULL,
    `last_seen` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_writer_device` (`writer_email`, `device_token_hash`),
    KEY `idx_writer_email` (`writer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbladmin_known_devices` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `admin_email` VARCHAR(255) NOT NULL,
    `device_token_hash` CHAR(64) NOT NULL,
    `device_label` VARCHAR(255) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `first_seen` DATETIME NOT NULL,
    `last_seen` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_admin_device` (`admin_email`, `device_token_hash`),
    KEY `idx_admin_email` (`admin_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
