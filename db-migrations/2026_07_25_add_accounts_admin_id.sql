-- Makes the "Account Distribution Dashboard" (sudo/14.php, backed by
-- sudo/accounts_api.php) per-admin instead of one global shared dataset.
-- Every admin who visits this dashboard today sees the exact same flat
-- list of financial accounts/balances - there is no admin_id/tenant
-- concept anywhere in the schema. This adds one, scoped to the admin
-- (tbladmin.email, matching the `$_SESSION['odmsaid']` convention used
-- everywhere else in sudo/ - see sudo/permissions.php's getAdminRole()).
--
-- `account_types` is intentionally left global/shared (a taxonomy like
-- "Savings"/"MMF"/"Sacco" that every admin's accounts pick from) - only
-- `accounts` needs the new column. `balance_history` has no admin_id of
-- its own; it's scoped transitively through `balance_history.account_id
-- -> accounts.admin_id`, which sudo/accounts_api.php's queries now join
-- through. `tbl_exchange_rates` stays global too (objective FX data).
--
-- Existing rows are backfilled to whichever admin account was created
-- first - the "only current user" this data actually belongs to today.
-- If that's wrong for your setup, update the backfilled admin_id values
-- by hand before/after running this (or edit the subquery below to name
-- the correct email directly) - the ALTER at the end only requires that
-- every row ends up with SOME value, not a specific one.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_25_add_accounts_admin_id.sql

ALTER TABLE `accounts`
    ADD COLUMN `admin_id` VARCHAR(255) NULL AFTER `id`;

UPDATE `accounts`
SET `admin_id` = (SELECT `email` FROM `tbladmin` ORDER BY `AdminRegdate` ASC LIMIT 1)
WHERE `admin_id` IS NULL;

ALTER TABLE `accounts`
    MODIFY COLUMN `admin_id` VARCHAR(255) NOT NULL,
    ADD INDEX `idx_accounts_admin_id` (`admin_id`);
