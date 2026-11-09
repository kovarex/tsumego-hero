<?php

declare(strict_types=1);

use App\Model\Table\AchievementConditionTable;
use App\Utility\SetBestScores;
use Phinx\Migration\AbstractMigration;

//The best accuracy and the best average time of a collection were taken from the set page as the player saw it:
//one part of the collection, the size of the player's choice, and only the ranks and tags they picked, over every
//attempt they ever made, a reset or not. A clean part or a few problems made the collection's best 100%. They are
//now of the whole collection since its resets (SetBestScores), so the kept ones are worked out again by that rule:
//a collection the player has solved whole gets the scores of that run, which replace the ones kept; one they
//haven't keeps none, as nothing shows it was ever solved whole.
final class RecomputeSetBestScores extends AbstractMigration
{
	public function up(): void
	{
		$categories = AchievementConditionTable::CATEGORY_SET_TIME . ', ' . AchievementConditionTable::CATEGORY_SET_ACCURACY;
		$query = fn(string $sql): array => $this->fetchAll($sql);
		$changed = $removed = $kept = 0;
		foreach ($this->fetchAll('SELECT DISTINCT user_id, set_id FROM achievement_condition
			WHERE achievement_condition_category_id IN (' . $categories . ') AND set_id IS NOT NULL') as $row)
		{
			$userID = (int) $row['user_id'];
			$setID = (int) $row['set_id'];
			$where = 'user_id = ' . $userID . ' AND set_id = ' . $setID . ' AND achievement_condition_category_id = ';
			$best = SetBestScores::of($userID, $setID, $query);
			if ($best === null)
			{
				$removed += $this->execute('DELETE FROM achievement_condition WHERE user_id = ' . $userID . ' AND set_id = ' . $setID
					. ' AND achievement_condition_category_id IN (' . $categories . ')') > 0 ? 1 : 0;
				continue;
			}
			$rows = $this->execute('UPDATE achievement_condition SET value = ' . $best->averageSeconds . ' WHERE ' . $where . AchievementConditionTable::CATEGORY_SET_TIME)
				+ $this->execute('UPDATE achievement_condition SET value = ' . $best->accuracy . ' WHERE ' . $where . AchievementConditionTable::CATEGORY_SET_ACCURACY);
			$rows > 0 ? $changed++ : $kept++;
		}
		$this->getOutput()?->writeln("Best scores of collections: $changed changed, $kept unchanged, $removed removed as the collection isn't solved whole");
	}

	//The scores replaced were of a part or a filtered page, nothing worth putting back
	public function down(): void {}
}
