-- "Sign in with Google" for both the writer (tblwriters) and admin
-- (tbladmin) interfaces. google_id stores the provider's stable subject id
-- (the OAuth "sub" claim), NOT the email - a later email change on the
-- Google account must not orphan the link. password is made nullable
-- because an account created purely through Google has no local password
-- to verify against (password_verify() is only ever reached for accounts
-- that do have one - see login.php's password_verify() check, which is
-- skipped entirely on the Google callback path).
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_28_add_google_oauth.sql

ALTER TABLE `tblwriters`
    MODIFY `password` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN `google_id` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
    ADD UNIQUE KEY `uniq_writer_google_id` (`google_id`);

ALTER TABLE `tbladmin`
    MODIFY `password` VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN `google_id` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
    ADD UNIQUE KEY `uniq_admin_google_id` (`google_id`);
