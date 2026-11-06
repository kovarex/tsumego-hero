<?php

use Phinx\Migration\AbstractMigration;

//A challenge has at most one rematch: the unique key keeps two players who click Rematch at the
//same moment from starting two.
class AddChallengeRematchOfChallengeId extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `challenge`
  ADD `rematch_of_challenge_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'The challenge this is the rematch of, NULL for a challenge started from the form.',
  ADD UNIQUE KEY `rematch_of_challenge_id` (`rematch_of_challenge_id`),
  ADD CONSTRAINT `challenge_rematch_of_challenge_id` FOREIGN KEY (`rematch_of_challenge_id`) REFERENCES `challenge` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `challenge`
  DROP FOREIGN KEY `challenge_rematch_of_challenge_id`,
  DROP KEY `rematch_of_challenge_id`,
  DROP `rematch_of_challenge_id`;');
	}
}
