<?php

namespace App\Utility;

use Cake\Cache\Cache;

//The "Active players" chart of the home page: how many players solved at least one problem each day, in level mode or
//in the time mode (user_day.solved), on the server clock. The mistake training does not count.
//The days before today are counted once, into active_players_day, by the daily cron (DailyCron), and read from there
//once a day into the 'long' cache; today is counted live, and the ranges built from both are kept 5 minutes in the
//'short' cache, so a view of the home page reads only the cache.
class ActivePlayers
{
	private const string HISTORY_KEY = 'active_players';
	private const string RANGES_KEY = 'active_players_ranges';

	//Every range of the chart (ChartRanges): the players of each day, or the average players a day of each week or month
	/** @return array<string, array{period: 'day'|'week'|'month', points: list<array{0: string, 1: int|float}>, partial: ?string}> */
	public static function ranges(): array
	{
		$today = date('Y-m-d');
		$cached = Cache::read(self::RANGES_KEY, 'short');
		if (is_array($cached) && ($cached['today'] ?? null) === $today)
			return $cached['ranges'];
		$days = self::history($today);
		$days[$today] = self::count($today);
		$ranges = ChartRanges::build($days, true, $today);
		Cache::write(self::RANGES_KEY, ['today' => $today, 'ranges' => $ranges], 'short');
		return $ranges;
	}

	//The players of each day before today, oldest first, from the first day anyone solved a problem; a day nobody did
	//is 0. Read from active_players_day, kept for the rest of the day. Days the cron has not stored yet are stored
	//first, a count of a day each.
	/** @return array<string, int> */
	public static function history(string $today): array
	{
		$cached = Cache::read(self::HISTORY_KEY, 'long');
		if (is_array($cached) && ($cached['today'] ?? null) === $today)
			return $cached['days'];
		self::storeDays($today);
		$counts = array_map('intval', array_column(
			Util::query('SELECT day, players FROM active_players_day WHERE day < ? ORDER BY day', [$today]), 'players', 'day'));
		$days = [];
		if ($counts)
			for ($day = (string) array_key_first($counts); $day < $today; $day = date('Y-m-d', strtotime($day . ' +1 day')))
				$days[$day] = $counts[$day] ?? 0;
		Cache::write(self::HISTORY_KEY, ['today' => $today, 'days' => $days], 'long');
		return $days;
	}

	//Stores the players of each day after the last stored one up to the day before $today, or of every day from the
	//first solve when none is stored, so a cron that missed days catches up; a day stored is never counted again.
	//Returns the days stored.
	public static function storeDays(string $today): int
	{
		$last = Util::query('SELECT MAX(day) AS last FROM active_players_day')[0]['last'];
		$from = $last !== null
			? date('Y-m-d', strtotime($last . ' +1 day'))
			: Util::query('SELECT MIN(day) AS first FROM user_day WHERE solved = 1')[0]['first'];
		$stored = 0;
		for ($day = $from; $day !== null && $day < $today; $day = date('Y-m-d', strtotime($day . ' +1 day')))
			$stored += Util::execute('INSERT IGNORE INTO active_players_day (day, players) VALUES (?, ?)', [$day, self::count($day)]);
		return $stored;
	}

	//The players who solved a problem on the day, through the index on (day, solved)
	public static function count(string $day): int
	{
		return (int) Util::query('SELECT COUNT(*) AS players FROM user_day WHERE day = ? AND solved = 1', [$day])[0]['players'];
	}

	//Drops the kept history and ranges, so the next view reads them again
	public static function forget(): void
	{
		Cache::delete(self::HISTORY_KEY, 'long');
		Cache::delete(self::RANGES_KEY, 'short');
	}
}
