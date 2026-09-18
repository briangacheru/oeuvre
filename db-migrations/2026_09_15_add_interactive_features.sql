-- Schema for the September 2026 interactivity batch: deadline extension
-- requests, the writer-facing bonus progress meter (+ admin on/off flag),
-- writer availability status, Web Push subscriptions, @mentions/reactions
-- on task comments, and task_id on the activity log so a per-task timeline
-- can be queried directly instead of LIKE-parsing `details`.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_09_15_add_interactive_features.sql

-- 1. Deadline extension requests (writer asks, admin approves/denies).
CREATE TABLE IF NOT EXISTS `tbl_task_extension_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `writer_email` VARCHAR(255) NOT NULL,
    `current_due_date` DATETIME NOT NULL,
    `requested_due_date` DATETIME NOT NULL,
    `reason` VARCHAR(500) NOT NULL,
    `status` ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
    `admin_response` VARCHAR(500) NULL,
    `resolved_by` VARCHAR(255) NULL,
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_extension_task` (`task_id`),
    INDEX `idx_extension_status` (`status`, `created_at`),
    INDEX `idx_extension_writer` (`writer_email`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Generic on/off flags admins can flip for writer-facing features.
-- Reusable beyond the bonus progress meter (that's the first row seeded).
CREATE TABLE IF NOT EXISTS `tbl_feature_flags` (
    `flag_name` VARCHAR(64) PRIMARY KEY,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL,
    `updated_by` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tbl_feature_flags` (`flag_name`, `is_enabled`, `updated_at`, `updated_by`)
VALUES ('writer_bonus_progress_meter', 0, NOW(), 'system');

-- 3. Writer availability status, shown next to their name in the writer
-- picker on sudo/create-task.php.
ALTER TABLE `tblwriters`
    ADD COLUMN `availability_status` VARCHAR(10) NOT NULL DEFAULT 'available' AFTER `is_online`,
    ADD COLUMN `availability_updated_at` DATETIME NULL AFTER `availability_status`;

-- 4. Web Push subscriptions (writer + admin), keyed by a hash of the
-- endpoint URL since TEXT columns can't carry a UNIQUE index directly.
CREATE TABLE IF NOT EXISTS `tbl_push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_type` VARCHAR(10) NOT NULL,
    `user_email` VARCHAR(255) NOT NULL,
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `uniq_endpoint_hash` (`endpoint_hash`),
    INDEX `idx_push_user` (`user_type`, `user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. @mentions (stored as a comma-separated list of usernames for display)
-- and emoji reactions on task discussion comments.
ALTER TABLE `tbl_task_comments`
    ADD COLUMN `mentions` VARCHAR(500) NULL AFTER `comment`;

CREATE TABLE IF NOT EXISTS `tbl_comment_reactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comment_id` INT NOT NULL,
    `user_type` VARCHAR(10) NOT NULL,
    `user_email` VARCHAR(255) NOT NULL,
    `emoji` VARCHAR(10) NOT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `uniq_reaction` (`comment_id`, `user_email`, `emoji`),
    INDEX `idx_reaction_comment` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. task_id on the activity log so the task timeline (view-task.php) can
-- query directly instead of LIKE-matching the free-form `details` column.
-- Existing rows are left NULL - the timeline query falls back to matching
-- "Task #<id>" in `details` for history written before this migration.
ALTER TABLE `tbl_activity_log`
    ADD COLUMN `task_id` INT NULL AFTER `action`,
    ADD INDEX `idx_activity_log_task` (`task_id`, `created_at`);
