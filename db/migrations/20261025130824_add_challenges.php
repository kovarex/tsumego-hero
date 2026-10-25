<?php

use Phinx\Migration\AbstractMigration;

/**
 * Challenges between players: one player picks a range of difficulty, the time for a problem and
 * how many problems, the site draws the problems, and every player in the challenge plays the same
 * ones like a time mode run. The run of a player is a time_mode_session of the challenge, which has
 * no category nor rank of the time mode, since the challenge says what they would.
 */
class AddChallenges extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("CREATE TABLE `challenge` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by_user_id` int(10) UNSIGNED NOT NULL,
  `minimum_time_mode_rank_id` int(10) UNSIGNED NOT NULL COMMENT 'The easiest problems drawn are of this rank.',
  `maximum_time_mode_rank_id` int(10) UNSIGNED NOT NULL COMMENT 'The hardest problems drawn are of this rank.',
  `seconds_per_problem` smallint(5) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by_user_id` (`created_by_user_id`),
  CONSTRAINT `challenge_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenge_minimum_time_mode_rank_id` FOREIGN KEY (`minimum_time_mode_rank_id`) REFERENCES `time_mode_rank` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `challenge_maximum_time_mode_rank_id` FOREIGN KEY (`maximum_time_mode_rank_id`) REFERENCES `time_mode_rank` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("CREATE TABLE `challenge_problem` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `order` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_id_order` (`challenge_id`, `order`),
  KEY `tsumego_id` (`tsumego_id`),
  CONSTRAINT `challenge_problem_challenge_id` FOREIGN KEY (`challenge_id`) REFERENCES `challenge` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenge_problem_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("CREATE TABLE `challenge_participant` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `invited_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL when the player joined by the link or created the challenge.',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_id_user_id` (`challenge_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `challenge_participant_challenge_id` FOREIGN KEY (`challenge_id`) REFERENCES `challenge` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenge_participant_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenge_participant_invited_by_user_id` FOREIGN KEY (`invited_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

		$this->execute("ALTER TABLE `time_mode_session`
  MODIFY `time_mode_category_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL for the run of a challenge.',
  MODIFY `time_mode_rank_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL for the run of a challenge.',
  ADD `challenge_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'The challenge this is the run of, NULL for a run of the time mode.',
  ADD UNIQUE KEY `challenge_id_user_id` (`challenge_id`, `user_id`),
  ADD CONSTRAINT `time_mode_session_challenge_id` FOREIGN KEY (`challenge_id`) REFERENCES `challenge` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
	}

	public function down(): void
	{
		$this->execute('DELETE FROM `time_mode_session` WHERE `challenge_id` IS NOT NULL;');
		$this->execute("ALTER TABLE `time_mode_session`
  DROP FOREIGN KEY `time_mode_session_challenge_id`,
  DROP KEY `challenge_id_user_id`,
  DROP `challenge_id`,
  MODIFY `time_mode_category_id` int(10) UNSIGNED NOT NULL,
  MODIFY `time_mode_rank_id` int(10) UNSIGNED NOT NULL;");
		$this->execute('DROP TABLE `challenge_participant`;');
		$this->execute('DROP TABLE `challenge_problem`;');
		$this->execute('DROP TABLE `challenge`;');
	}
}
