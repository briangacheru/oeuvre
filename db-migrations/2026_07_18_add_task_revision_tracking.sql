-- Tracks how many times a task has been sent back for revision, and which
-- revision cycle each submitted file belongs to, so resubmissions can be
-- badged "Revision 1", "Revision 2", etc.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_18_add_task_revision_tracking.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `revision_count` INT NOT NULL DEFAULT 0;

ALTER TABLE `tbl_task_files`
    ADD COLUMN `revision_number` INT NOT NULL DEFAULT 0;
