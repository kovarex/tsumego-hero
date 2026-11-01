<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A finished collection records the player's best time and accuracy on it and checks the accuracy
//and speed achievements, unless it is left out of them. Set 210, a multiple-choice semeai quiz
//where a quick guess would make the speed achievements free, was left out by its id in
//SetsController, which this column replaces.
final class AddSetIncludedInSetAchievements extends AbstractMigration
{
	public function up(): void
	{
		$this->execute('ALTER TABLE `set` ADD COLUMN included_in_set_achievements TINYINT(1) NOT NULL DEFAULT 1');
		$this->execute('UPDATE `set` SET included_in_set_achievements = 0 WHERE id = 210');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `set` DROP COLUMN included_in_set_achievements');
	}
}
