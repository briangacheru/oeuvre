-- Backs the new drag-to-reorder feature on sudo/todo.php (non-completed
-- tasks only). sort_order is a tiebreaker WITHIN a due-date bucket
-- (overdue/today/tomorrow/week/later/no_date) - bucket membership itself
-- stays entirely due_date-driven (computed in PHP), so identical
-- sort_order values reused across different buckets don't collide.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_26_add_todo_sort_order.sql

ALTER TABLE `tbltodos`
    ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0;
