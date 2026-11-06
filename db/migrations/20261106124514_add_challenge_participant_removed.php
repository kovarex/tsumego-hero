<?php

use Phinx\Migration\AbstractMigration;

//The creator of a challenge can remove a player who left their run waiting, so the challenge can finish without them.
class AddChallengeParticipantRemoved extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `challenge_participant`
  ADD `removed` datetime DEFAULT NULL COMMENT 'When the creator of the challenge removed the player, NULL while they are in it.';");
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `challenge_participant` DROP `removed`;');
	}
}
