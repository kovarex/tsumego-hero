<?php

use Phinx\Migration\AbstractMigration;

/**
 * A player invited to a challenge can turn it down instead of leaving it waiting in their list.
 */
class AddChallengeParticipantDeclined extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `challenge_participant`
  ADD `declined` datetime DEFAULT NULL COMMENT 'When the player turned the challenge down, NULL while they are in it.';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `challenge_participant` DROP `declined`;');
	}
}
