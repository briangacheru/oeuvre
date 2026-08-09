-- Lets admins pin important projects to a dedicated "Pinned Projects" card
-- row on sudo/projects.php, shown between the Active Projects and All
-- Projects sections. Toggled via sudo/toggle_project_pin.php. Run once
-- against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_08_03_add_project_pinning.sql

ALTER TABLE `tbl_projects`
    ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_achieved`;
