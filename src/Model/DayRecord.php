<?php

use App\Utility\Auth;

class DayRecord extends AppModel
{
	//Each gem of the day has a counter column and an achievement. The digit of the day record's
	//"gems" (e.g. "0-2-1") picks the ranks whose solves the gem counts that day.
	private const GEMS = [
		['counter' => 'emerald_solves', 'category' => AchievementCondition::CATEGORY_EMERALD, 'goal' => 500, 'ranks' => [['15k'], ['12k'], ['10k']]],
		['counter' => 'sapphire_solves', 'category' => AchievementCondition::CATEGORY_SAPPHIRE, 'goal' => 200, 'ranks' => [['9k'], ['6k', '5k', '4k'], ['2k', '1k']]],
		['counter' => 'ruby_solves', 'category' => AchievementCondition::CATEGORY_RUBY, 'goal' => 5, 'ranks' => [['1d', '2d', '3d'], ['2d', '3d', '4d'], ['5d', '6d', '7d']]],
	];

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'day_record';
		parent::__construct($id, $table, $ds);
	}

	//Counts a solve of a problem of this rank towards today's gem, the solve reaching the goal
	//gives the player the gem's achievement. A player who has it already doesn't count that one.
	public static function countGemSolve(string $rank): void
	{
		$today = date('Y-m-d');
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['conditions' => ['date' => $today]]);
		if (!$dayRecord)
			return;
		$gemOfTheDay = explode('-', $dayRecord['DayRecord']['gems']);
		foreach (self::GEMS as $index => $gem)
		{
			if (!in_array($rank, $gem['ranks'][$gemOfTheDay[$index]] ?? [], true))
				continue;
			if ($dayRecord['DayRecord'][$gem['counter']] + 1 == $gem['goal'])
			{
				if (AchievementCondition::value(Auth::getUserID(), $gem['category']) !== null)
					return;
				AchievementCondition::put(Auth::getUserID(), $gem['category'], 1);
			}
			ClassRegistry::init('DayRecord')->updateAll([$gem['counter'] => $gem['counter'] . ' + 1'], ['date' => $today]);
			return;
		}
	}
}
