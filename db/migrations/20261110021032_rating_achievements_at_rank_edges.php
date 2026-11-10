<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The rating achievements unlock at the first rating of their rank (Rating::getRankMinimalRating, the same edges as
//Rating.js): 6 kyu from 1450, 5 dan from 2450. Their descriptions said 1500 to 2500, 50 more than the check asked.
final class RatingAchievementsAtRankEdges extends AbstractMigration
{
	//id => [rank, the first rating of the rank, the rating the old description said]
	private const array ACHIEVEMENTS = [
		59 => ['6 kyu', 1450, 1500],
		60 => ['5 kyu', 1550, 1600],
		61 => ['4 kyu', 1650, 1700],
		62 => ['3 kyu', 1750, 1800],
		63 => ['2 kyu', 1850, 1900],
		64 => ['1 kyu', 1950, 2000],
		65 => ['1 dan', 2050, 2100],
		66 => ['2 dan', 2150, 2200],
		67 => ['3 dan', 2250, 2300],
		68 => ['4 dan', 2350, 2400],
		69 => ['5 dan', 2450, 2500]];

	public function up(): void
	{
		foreach (self::ACHIEVEMENTS as $id => [$rank, $rating])
			$this->execute("UPDATE achievement SET description = 'Reach " . $rank . ", a rating of " . $rating . ".' WHERE id = " . $id);
	}

	public function down(): void
	{
		foreach (self::ACHIEVEMENTS as $id => [, , $oldRating])
			$this->execute("UPDATE achievement SET description = 'Reach a rating of " . $oldRating . ".' WHERE id = " . $id);
	}
}
