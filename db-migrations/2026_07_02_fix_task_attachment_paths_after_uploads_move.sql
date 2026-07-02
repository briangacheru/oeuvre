-- The uploads/ folder used for to-do task attachments moved from
-- sudo/uploads/ to a shared root-level uploads/ (both interfaces already
-- expected project attachments there; only the task attachments path was
-- still sudo-relative). Any attachment uploaded before this change has
-- file_path stored as 'uploads/tasks/...' (resolved from within sudo/);
-- it must become '../uploads/tasks/...' so it still resolves correctly
-- from a page served at /sudo/todo.
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_02_fix_task_attachment_paths_after_uploads_move.sql

UPDATE `task_attachments`
SET `file_path` = CONCAT('../', `file_path`)
WHERE `file_path` LIKE 'uploads/tasks/%';
