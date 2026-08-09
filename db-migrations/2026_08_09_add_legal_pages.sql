-- Lets a superadmin edit the Terms of Service / Privacy Policy body shown
-- on terms.php / privacy.php from sudo/legal-pages.php, instead of those
-- being hardcoded HTML. `content` starts NULL for both rows - terms.php
-- and privacy.php fall back to their built-in default text (see
-- legal-content-defaults.php) until an admin actually saves an edit here,
-- so both pages keep working whether or not this migration/edit has
-- happened yet.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_09_add_legal_pages.sql

CREATE TABLE IF NOT EXISTS `tbl_legal_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `page_key` VARCHAR(20) NOT NULL,
    `content` LONGTEXT NULL DEFAULT NULL,
    `updated_by` VARCHAR(191) NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    UNIQUE KEY `uniq_page_key` (`page_key`)
);

INSERT INTO `tbl_legal_pages` (`page_key`)
SELECT * FROM (SELECT 'terms' AS page_key UNION ALL SELECT 'privacy') AS seed
WHERE NOT EXISTS (SELECT 1 FROM `tbl_legal_pages` WHERE `tbl_legal_pages`.`page_key` = seed.page_key);
