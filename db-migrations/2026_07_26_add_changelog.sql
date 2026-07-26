-- Replaces sudo/version.json, which only ever held ONE mutable record -
-- every "Update Version" click on sudo/changelog.php overwrote the
-- previous description, so despite the name there was no actual history,
-- and concurrent edits could clobber each other (plain file write, no
-- locking). tbl_changelog is append-only: the "current version" is just
-- its most recent row, and every past bump stays visible.
--
-- See shared-functions.php's get_current_version() / get_changelog_history()
-- / add_changelog_entry().
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_26_add_changelog.sql

CREATE TABLE IF NOT EXISTS `tbl_changelog` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `major` INT NOT NULL,
    `minor` INT NOT NULL,
    `patch` INT NOT NULL,
    `description` VARCHAR(500) NULL,
    `created_by` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_changelog_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed with the version this app was already on (from the old
-- sudo/version.json) so the history doesn't appear to start from zero.
-- The WHERE NOT EXISTS guard keeps this idempotent if the migration is
-- ever re-run.
INSERT INTO `tbl_changelog` (`major`, `minor`, `patch`, `description`, `created_by`, `created_at`)
SELECT 3, 0, 4, 'I added Digital Ocean Spaces.', NULL, '2025-05-26 00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `tbl_changelog`);
