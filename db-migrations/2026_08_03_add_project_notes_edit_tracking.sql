-- Adds edit tracking to project activity log notes (tbl_project_notes) -
-- sudo/project-details.php's "Activity Log" card. `updated_at` stays NULL
-- until a note is edited via sudo/edit_project_note.php, at which point
-- the UI shows an "Edited" badge next to the note. Run once against the
-- `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_03_add_project_notes_edit_tracking.sql

ALTER TABLE `tbl_project_notes`
    ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;
