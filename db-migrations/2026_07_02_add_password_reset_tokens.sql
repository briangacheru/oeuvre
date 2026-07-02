-- Password-reset tokens for the writer (tblwriters) and admin (tbladmin) tables.
-- Replaces the old guessable md5(id) reset links with single-use, expiring tokens.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_02_add_password_reset_tokens.sql

ALTER TABLE `tblwriters`
    ADD COLUMN `reset_token`   VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN `reset_expires` DATETIME    NULL DEFAULT NULL;

ALTER TABLE `tbladmin`
    ADD COLUMN `reset_token`   VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN `reset_expires` DATETIME    NULL DEFAULT NULL;
