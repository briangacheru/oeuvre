-- Account lockout after repeated failed logins, for both interfaces.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_05_add_login_lockout.sql

ALTER TABLE `tblwriters`
    ADD COLUMN `failed_login_attempts` INT NOT NULL DEFAULT 0,
    ADD COLUMN `locked_until` DATETIME NULL DEFAULT NULL;

ALTER TABLE `tbladmin`
    ADD COLUMN `failed_login_attempts` INT NOT NULL DEFAULT 0,
    ADD COLUMN `locked_until` DATETIME NULL DEFAULT NULL;
