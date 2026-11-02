<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The achievement a player gets for solving every problem of the collections that share it, which
//AchievementChecker::checkCompletionAchievement() checks when a page of one of them is finished.
//The groups were id lists in AchievementChecker (SPECIAL_SET_GROUPS), which this column replaces.
final class AddSetCompletionAchievement extends AbstractMigration
{
	//achievement id => the collections of the group
	private const GROUPS = [
		92 => [50, 52, 53, 54], // Life & Death - Elementary
		93 => [41, 49, 65, 66], // Life & Death - Intermediate
		94 => [186, 187, 196, 203], // Life & Death - Advanced
		95 => [190, 193, 198], // 1000 Weiqi problems, first half
		115 => [216], // 1000 Weiqi problems, second half
	];

	public function up(): void
	{
		$this->execute('ALTER TABLE `set` ADD COLUMN completion_achievement_id INT(10) UNSIGNED NULL DEFAULT NULL,
			ADD CONSTRAINT `set_completion_achievement_id` FOREIGN KEY (`completion_achievement_id`) REFERENCES `achievement` (`id`) ON UPDATE CASCADE');
		foreach (self::GROUPS as $achievementID => $setIDs)
			$this->execute('UPDATE `set` SET completion_achievement_id = ' . $achievementID . ' WHERE id IN (' . implode(', ', $setIDs) . ')');
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `set` DROP FOREIGN KEY `set_completion_achievement_id`, DROP COLUMN completion_achievement_id');
	}
}
