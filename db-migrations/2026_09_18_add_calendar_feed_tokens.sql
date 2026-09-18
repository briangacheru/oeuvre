-- Per-user secret token for the iCalendar (.ics) subscription feed, so
-- Google Calendar / Apple Calendar / Outlook can pull task due dates
-- without a login session (calendar clients fetch feeds anonymously).
-- Generated lazily the first time someone opens the "Subscribe" dialog on
-- calendar.php / sudo/calendar.php and can be regenerated from there to
-- revoke a leaked URL. Writers see only their own tasks; admins see all.
--
-- Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_09_18_add_calendar_feed_tokens.sql

ALTER TABLE `tblwriters`
    ADD COLUMN `calendar_token` VARCHAR(64) NULL DEFAULT NULL,
    ADD UNIQUE KEY `uniq_writer_calendar_token` (`calendar_token`);

ALTER TABLE `tbladmin`
    ADD COLUMN `calendar_token` VARCHAR(64) NULL DEFAULT NULL,
    ADD UNIQUE KEY `uniq_admin_calendar_token` (`calendar_token`);
