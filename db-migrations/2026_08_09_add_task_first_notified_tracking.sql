-- Tracks whether a task's writer has ever received an assignment email, so
-- submit-task.php / update-task.php can tell "New Task Assigned" (first
-- notification - covers both brand-new tasks and duplicated tasks being
-- sent out for the first time) apart from "Task Updated" (a later edit of
-- an already-notified task), and so update-task.php can build a "what
-- changed" summary against the pre-edit values.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_09_add_task_first_notified_tracking.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `first_notified_at` DATETIME NULL DEFAULT NULL;

-- Backfill: any task that was ever published (publish = 1) already went
-- through the "New Task Assigned" send at least once historically, under
-- the existing $publish == 1 gate in both submit-task.php and
-- update-task.php. Leave publish = 0 / never-published tasks (including
-- freshly duplicated ones, which are inserted with no `publish` set) as
-- NULL, since those genuinely haven't been notified yet.
UPDATE `tbltasks`
SET `first_notified_at` = COALESCE(`create_date`, NOW())
WHERE `publish` = 1
  AND `first_notified_at` IS NULL;
