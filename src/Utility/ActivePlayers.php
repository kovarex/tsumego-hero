<?php

namespace App\Utility;

use Cake\Cache\Cache;

//The "Active players" chart of the home page: how many players solved at least one problem each day, in level mode
//(an attempt solved that day, user_day.solved), by DATE(created) on the server clock. The time mode and the mistake
//training write no tsumego_attempt, so their solves don't count.
//The days before today are counted once a day and kept in the 'long' cache; today is counted live, so far.
class ActivePlayers
{
	private const string CACHE_KEY = 'active_players';

	//Every range of the chart (ChartRanges): the players of each day, or the average players a day of each week or month
	/** @return array<string, array{period: 'day'|'week'|'month', points: list<array{0: string, 1: int|float}>, partial: ?string}> */
	public static function ranges(): array
	{
		$today = date('Y-m-d');
		$days = self::history($today);
		$days[$today] = self::count($today);
		return ChartRanges::build($days, true, $today);
	}

	//The players of each day before today, oldest first, from the first day anyone solved a problem; a day nobody did
	//is 0. Counted through the index on (day, solved), kept for the rest of the day.
	/** @return array<string, int> */
	public static function history(string $today): array
	{
		$cached = Cache::read(self::CACHE_KEY, 'long');
		if (is_array($cached) && ($cached['today'] ?? null) === $today)
			return $cached['days'];
		$counts = array_map('intval', array_column(
			Util::query('SELECT day, COUNT(*) AS players FROM user_day WHERE solved = 1 AND day < ? GROUP BY day ORDER BY day', [$today]), 'players', 'day'));
		$days = [];
		if ($counts)
			for ($day = (string) array_key_first($counts); $day < $today; $day = date('Y-m-d', strtotime($day . ' +1 day')))
				$days[$day] = $counts[$day] ?? 0;
		Cache::write(self::CACHE_KEY, ['today' => $today, 'days' => $days], 'long');
		return $days;
	}

	//The players who solved a problem on the day
	public static function count(string $day): int
	{
		return (int) Util::query('SELECT COUNT(*) AS players FROM user_day WHERE day = ? AND solved = 1', [$day])[0]['players'];
	}

	//Drops the kept days, so the next ask counts them again
	public static function forget(): void
	{
		Cache::delete(self::CACHE_KEY, 'long');
	}
}
