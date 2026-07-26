-- Backs the Equity/M-Pesa statement import feature on sudo/transactions.php.
-- is_internal_transfer flags rows the importer auto-detected (or the admin
-- manually marked) as money moving between the user's own accounts, so
-- they can be excluded from Income/Expense totals. Not yet wired into the
-- existing budget/chart dashboards (sudo/budget.php, sudo/chart-data.php)
-- — that retrofit is a separate follow-up once it can be verified against
-- real data.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_26_add_transaction_import_fields.sql

ALTER TABLE `tblbudget`
    ADD COLUMN `is_internal_transfer` TINYINT(1) NOT NULL DEFAULT 0;
