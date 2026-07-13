-- Adds support for: typing indicator, message edit/delete, and linking a
-- conversation to a task. Run once against the `tasker` database:
--   mysql -u root tasker < db-migrations/2026_07_13_add_chat_features.sql

ALTER TABLE `chat_messages`
    ADD COLUMN `is_edited` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `edited_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `related_task_id` INT NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `chat_typing_status` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `sender_id` INT NOT NULL,
    `sender_type` VARCHAR(10) NOT NULL,
    `receiver_id` INT NOT NULL,
    `receiver_type` VARCHAR(10) NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_conversation_direction` (`sender_id`, `sender_type`, `receiver_id`, `receiver_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
