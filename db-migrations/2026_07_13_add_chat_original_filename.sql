-- Chat attachments are stored on disk under a randomized filename (to avoid
-- collisions between different senders), which meant the original filename
-- the user uploaded was lost entirely - the chat bubble displayed the random
-- name instead. This column preserves it for display purposes only; the
-- actual stored/served file still uses the randomized file_url. Run once
-- against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_13_add_chat_original_filename.sql

ALTER TABLE `chat_messages`
    ADD COLUMN `original_file_name` VARCHAR(255) NULL DEFAULT NULL;
