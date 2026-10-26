<?php

use Phinx\Migration\AbstractMigration;

/**
 * Messages players send each other. A conversation is the messages between two
 * players, so it needs no table of its own. The recipient can ask to get an email
 * when a message arrives.
 *
 * The migration first ran as version 20261023120000, which another branch also used. The test
 * site already holds its tables under that version, so this one leaves what is there alone.
 */
class AddMessages extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE IF NOT EXISTS `message` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(10) UNSIGNED NOT NULL,
  `recipient_user_id` int(10) UNSIGNED NOT NULL,
  `text` text NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime NULL DEFAULT NULL COMMENT 'When the recipient opened the conversation, NULL while unread',
  PRIMARY KEY (`id`),
  KEY `message_sender_recipient` (`sender_user_id`, `recipient_user_id`),
  KEY `message_recipient_sender` (`recipient_user_id`, `sender_user_id`),
  KEY `message_recipient_read_at` (`recipient_user_id`, `read_at`),
  CONSTRAINT `message_sender_user_id` FOREIGN KEY (`sender_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `message_recipient_user_id` FOREIGN KEY (`recipient_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("ALTER TABLE `user` ADD COLUMN IF NOT EXISTS `email_on_new_message` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether a new message is announced by an email';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `user` DROP COLUMN `email_on_new_message`;');
		$this->execute('DROP TABLE `message`;');
	}
}
