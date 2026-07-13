-- Adds support for recording why a task was cancelled (sudo/tasks-in-progress.php
-- cancel-task modal). Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_13_add_task_cancellation_reason.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `cancellation_reason` TEXT NULL DEFAULT NULL;
