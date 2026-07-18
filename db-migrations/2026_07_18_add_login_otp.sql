-- One-time email verification codes, required after a 7-day (normal) or
-- 14-day (remember-me) session has expired and someone logs back in with
-- their password. Mirrors the reset_token/reset_expires pattern - only the
-- hash is stored, never the raw code.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_18_add_login_otp.sql

ALTER TABLE `tblwriters`
    ADD COLUMN `login_otp_hash`     VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN `login_otp_expires`  DATETIME    NULL DEFAULT NULL,
    ADD COLUMN `login_otp_attempts` INT NOT NULL DEFAULT 0;

ALTER TABLE `tbladmin`
    ADD COLUMN `login_otp_hash`     VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN `login_otp_expires`  DATETIME    NULL DEFAULT NULL,
    ADD COLUMN `login_otp_attempts` INT NOT NULL DEFAULT 0;
