<?php

namespace App\Model\Table;

use App\Utility\Auth;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\TableRegistry;

//What the daily cron picks for a day: the user of the day, the quote and the gems.
class DayRecordTable extends AppTable
{
	//Each gem of the day has a counter column and an achievement. The digit of the day record's
	//"gems" (e.g. "0-2-1") picks the ranks whose solves the gem counts that day.
	private const GEMS = [
		['counter' => 'emerald_solves', 'category' => AchievementConditionTable::CATEGORY_EMERALD, 'goal' => 500, 'ranks' => [['15k'], ['12k'], ['10k']]],
		['counter' => 'sapphire_solves', 'category' => AchievementConditionTable::CATEGORY_SAPPHIRE, 'goal' => 200, 'ranks' => [['9k'], ['6k', '5k', '4k'], ['2k', '1k']]],
		['counter' => 'ruby_solves', 'category' => AchievementConditionTable::CATEGORY_RUBY, 'goal' => 5, 'ranks' => [['1d', '2d', '3d'], ['2d', '3d', '4d'], ['5d', '6d', '7d']]],
	];

	//Counts a solve of a problem of this rank towards today's gem, the solve reaching the goal
	//gives the player the gem's achievement. A player who has it already doesn't count that one.
	public static function countGemSolve(string $rank): void
	{
		$today = date('Y-m-d');
		$dayRecordTable = TableRegistry::getTableLocator()->get('DayRecord');
		$dayRecord = $dayRecordTable->find()->where(['date' => $today])->first();
		if (!$dayRecord)
			return;
		$gemOfTheDay = explode('-', $dayRecord->gems);
		foreach (self::GEMS as $index => $gem)
		{
			if (!in_array($rank, $gem['ranks'][$gemOfTheDay[$index]] ?? [], true))
				continue;
			if ($dayRecord->get($gem['counter']) + 1 == $gem['goal'])
			{
				if (AchievementConditionTable::value(Auth::getUserID(), $gem['category']) !== null)
					return;
				AchievementConditionTable::put(Auth::getUserID(), $gem['category'], 1);
			}
			$dayRecordTable->updateAll([new QueryExpression($gem['counter'] . ' = ' . $gem['counter'] . ' + 1')], ['date' => $today]);
			return;
		}
	}
}
