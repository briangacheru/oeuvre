-- Adds support for recording how a task's payment was settled - either a
-- transaction code (with the date/time it was paid) or an overdraft
-- (absorbed into the writer's overdraft ledger, no transaction code/date
-- needed). Captured via the "Mark as Paid" modal on sudo/view-task.php and
-- sudo/unpaid-tasks.php. Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_30_add_task_payment_method.sql

ALTER TABLE `tbltasks`
    ADD COLUMN `payment_method` ENUM('transaction_code','overdraft') NULL DEFAULT NULL AFTER `paid_on`,
    ADD COLUMN `transaction_code` VARCHAR(100) NULL DEFAULT NULL AFTER `payment_method`;
