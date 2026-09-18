-- Per-task quality rating by admin (1-5 stars + optional note), captured
-- when a task is completed on sudo/view-task.php (or later from the
-- "Quality Rating" card there). Until now writer levels and monthly bonuses
-- keyed off lateness and volume only; the average rating now also feeds:
--   * tbl_writer_levels.min_quality_rating - optional per-level gate (a
--     writer with rated tasks must average at least this to hold the level)
--   * tbl_writer_performance.average_quality_rating / rated_tasks - cached
--     alongside the other performance metrics
--   * tbl_monthly_bonuses.quality_bonus - the long-dormant
--     quality_bonus_threshold / quality_bonus_percentage settings on
--     sudo/bonus-settings.php finally have a signal to act on
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_09_18_add_task_quality_rating.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `quality_rating` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `completed_on`,
    ADD COLUMN `quality_rating_note` VARCHAR(500) NULL DEFAULT NULL AFTER `quality_rating`,
    ADD COLUMN `quality_rated_by` VARCHAR(255) NULL DEFAULT NULL AFTER `quality_rating_note`,
    ADD COLUMN `quality_rated_at` DATETIME NULL DEFAULT NULL AFTER `quality_rated_by`;

ALTER TABLE `tbl_writer_levels`
    ADD COLUMN `min_quality_rating` DECIMAL(3,2) NULL DEFAULT NULL AFTER `max_completed_tasks`;

ALTER TABLE `tbl_writer_performance`
    ADD COLUMN `average_quality_rating` DECIMAL(3,2) NULL DEFAULT NULL AFTER `on_time_rate`,
    ADD COLUMN `rated_tasks` INT NOT NULL DEFAULT 0 AFTER `average_quality_rating`;

ALTER TABLE `tbl_monthly_bonuses`
    ADD COLUMN `quality_bonus` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `perfect_month_bonus`,
    ADD COLUMN `average_quality_rating` DECIMAL(3,2) NULL DEFAULT NULL AFTER `quality_bonus`;
