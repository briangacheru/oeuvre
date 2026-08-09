-- Tracks exactly when a task was cancelled (is_deleted set to 1), so
-- view-task.php / sudo/view-task.php can show it next to the Cancelled
-- status badge. Written with SQL NOW() (this DB server's clock is UTC,
-- same convention as tbl_task_comments.created_at / tbl_task_files.upload_time
-- - see the timestamp-timezone memory), converted to Africa/Nairobi on
-- display, not stored pre-converted.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_09_add_task_cancelled_at.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL;
