-- Backs log_activity() in shared-functions.php - a persistent writer/admin
-- activity log (login, logout, task viewed, task submitted/resubmitted,
-- and any other events instrumented later), shown on the new
-- sudo/activity-log.php page alongside a live view of tbl_rate_limits.
--
-- Deliberately separate from tbl_rate_limits (2026_07_26_add_rate_limits.sql):
-- that table is a rolling short-term window that check_rate_limit() purges
-- as rows age out of their bucket's window (seconds/minutes), so it can
-- never serve as a durable history. This table is never purged by app code
-- - it's the actual log.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_26_add_activity_log.sql

CREATE TABLE IF NOT EXISTS `tbl_activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `actor_type` VARCHAR(10) NOT NULL,   -- 'writer' or 'admin'
    `email` VARCHAR(255) NOT NULL,
    `action` VARCHAR(30) NOT NULL,       -- 'login', 'logout', 'task_view', 'task_submit', ...
    `details` VARCHAR(255) NULL,         -- free-form context, e.g. "Task #1234: Essay on..."
    `created_at` DATETIME NOT NULL,
    INDEX `idx_activity_log_email` (`email`, `created_at`),
    INDEX `idx_activity_log_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
