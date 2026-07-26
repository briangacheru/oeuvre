-- Backs check_rate_limit()/rate_limit_message() in shared-functions.php - a
-- general-purpose sliding-window request counter used across both
-- interfaces (login, forgot-password, register, task submission/creation,
-- comments, search, PIN verify, password reset, OTP resend). Independent
-- of the existing per-account login-lockout table columns
-- (failed_login_attempts/locked_until on tblwriters/tbladmin, see
-- 2026_07_05_add_login_lockout.sql) - this one throttles a bucket of
-- requests regardless of success/failure, and works for unauthenticated
-- endpoints that have no account row to attach a counter to.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_26_add_rate_limits.sql

CREATE TABLE IF NOT EXISTS `tbl_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `identifier` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_rate_limits_lookup` (`action`, `identifier`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
