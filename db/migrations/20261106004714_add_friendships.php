<?php

use Phinx\Migration\AbstractMigration;

//Friends: a player asks another one (user_id asks friend_user_id), and the asked one accepts or
//declines. A declined or removed friendship is deleted, so a row is either pending or accepted.
//A pair has one row whichever side asked: the unique key on the lower and the higher id of the pair
//keeps a second one out even when both ask at the same moment. MariaDB refuses a generated column
//over a column whose foreign key cascades updates, so the keys to user only cascade deletes.
//The homepage shows the challenges the friends of a player finished (FriendActivity); the index on
//the player and the challenge of a time mode run takes it straight to their challenge runs, without
//reading every run of the time mode they played.
class AddFriendships extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `friendship_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
		$this->execute("INSERT INTO `friendship_status` (`id`, `name`) VALUES (1, 'pending'), (2, 'accepted');");

		$this->execute("CREATE TABLE `friendship` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'The player who asked.',
  `friend_user_id` int(10) UNSIGNED NOT NULL COMMENT 'The player who was asked.',
  `friendship_status_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted` datetime DEFAULT NULL,
  `lower_user_id` int(10) UNSIGNED AS (LEAST(`user_id`, `friend_user_id`)) PERSISTENT,
  `higher_user_id` int(10) UNSIGNED AS (GREATEST(`user_id`, `friend_user_id`)) PERSISTENT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pair` (`lower_user_id`, `higher_user_id`),
  KEY `user_id_status` (`user_id`, `friendship_status_id`),
  KEY `friend_user_id_status` (`friend_user_id`, `friendship_status_id`),
  KEY `friendship_status_id` (`friendship_status_id`),
  CONSTRAINT `friendship_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendship_friend_user_id` FOREIGN KEY (`friend_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friendship_friendship_status_id` FOREIGN KEY (`friendship_status_id`) REFERENCES `friendship_status` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute('ALTER TABLE `time_mode_session` ADD KEY `user_id_challenge_id` (`user_id`, `challenge_id`)');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `time_mode_session` DROP KEY `user_id_challenge_id`');
		$this->execute('DROP TABLE `friendship`');
		$this->execute('DROP TABLE `friendship_status`');
	}
}
