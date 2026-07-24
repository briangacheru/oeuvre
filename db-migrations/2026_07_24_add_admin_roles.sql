-- Replaces the old binary admin-access model (AdminName == 'Admin' meant
-- "approved, full access"; a separate superadmin == 1 flag meant "site
-- owner") with a real `role` column on tbladmin. Both old flags were only
-- ever set by hand-editing the database directly - there was no in-app way
-- to create/approve an admin or grant a role. This migration backfills
-- `role` from those flags so existing approved accounts keep working
-- exactly as before; going forward, roles are assigned via
-- sudo/manage-admins.php (superadmin only).
--
-- Roles, from least to most access:
--   pending    - freshly self-registered via register.php, zero access
--                (matches today's "AdminName not yet set to Admin" state)
--   support    - task/writer operations only (create/edit tasks, chat,
--                writer management) - no financial pages
--   finance    - financial operations only (overdrafts, budget, invoices,
--                marking tasks paid/unpaid) - no task/writer editing
--   admin      - full day-to-day operational access (support + finance
--                combined) - everything except site-wide settings and
--                admin account management
--   superadmin - admin + site settings (registration open/close,
--                site-wide notification banner) + can create/approve
--                admins and assign roles
--
-- AdminName and superadmin are intentionally left in place (not dropped) as
-- a rollback safety net; the application no longer reads them for access
-- control after this migration ships.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_24_add_admin_roles.sql

ALTER TABLE `tbladmin`
    ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `AdminName`;

UPDATE `tbladmin` SET `role` = 'admin'      WHERE `AdminName` = 'Admin' AND (`superadmin` IS NULL OR `superadmin` != 1);
UPDATE `tbladmin` SET `role` = 'superadmin' WHERE `AdminName` = 'Admin' AND `superadmin` = 1;
-- Everything else (AdminName never set to 'Admin') keeps the DEFAULT 'pending'.
