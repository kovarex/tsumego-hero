<?php

namespace App\Utility;

use Cake\Cache\Cache;

//The "Site Growth" graph of the home page: how many problems the site offers and how many players
//it has, month by month.
//A problem counts while it is in a public collection of the site, from the day it was published
//(its schedule date, or its creation for a problem that never went through the schedule) to the
//day it, or the last public collection it is in, went to the trash. Problems of the sandbox and
//of the collections players assembled don't count.
//The history is worked out from what the database holds now, so a problem restored from the trash
//counts as if it was never deleted, and one purged from the trash is gone from the whole history.
class SiteGrowth
{
	private const CACHE_KEY = 'homepage_chart';

	//The points of the graph, one for each month in which something changed, oldest first.
	/** @return array<int, array{date: string, problems: int|null, users: int|null}> */
	public static function chartData(): array
	{
		$chartData = Cache::read(self::CACHE_KEY, 'long');
		if ($chartData === false)
		{
			$chartData = self::build();
			Cache::write(self::CACHE_KEY, $chartData, 'long');
		}
		return $chartData;
	}

	//Drops the cached graph, so that a deletion, a restore or a publication shows on it at once.
	public static function forget(): void
	{
		Cache::delete(self::CACHE_KEY, 'long');
	}

	/** @return array<int, array{date: string, problems: int|null, users: int|null}> */
	public static function build(): array
	{
		$problemsByMonth = self::cumulative(self::problemChangesByMonth());
		$usersByMonth = self::cumulative(self::userChangesByMonth());

		$allMonths = array_unique(array_merge(array_keys($problemsByMonth), array_keys($usersByMonth)));
		sort($allMonths);

		// a month in which only one of the two changed carries the last value of the other forward
		$lastProblems = null;
		$lastUsers = null;
		$chartData = [];
		foreach ($allMonths as $month)
		{
			$lastProblems = $problemsByMonth[$month] ?? $lastProblems;
			$lastUsers = $usersByMonth[$month] ?? $lastUsers;
			$chartData[] = ['date' => $month, 'problems' => $lastProblems, 'users' => $lastUsers];
		}
		return $chartData;
	}

	//How many problems became public in each month, less those that stopped being public in it.
	/** @return array<string, int> by the first day of the month, oldest first */
	private static function problemChangesByMonth(): array
	{
		return self::byMonth(Util::query("
			WITH public_problem AS (
				SELECT
					COALESCE(published.since, tsumego.created) AS since,
					COALESCE(LEAST(tsumego.deleted, collections.until), tsumego.deleted, collections.until) AS until
				FROM tsumego
				JOIN (
					-- a problem leaves the site with the last of its public collections
					SELECT set_connection.tsumego_id, IF(SUM(`set`.deleted IS NULL) > 0, NULL, MAX(`set`.deleted)) AS until
					FROM set_connection
					JOIN `set` ON `set`.id = set_connection.set_id AND `set`.public = 1 AND `set`.user_id IS NULL
					GROUP BY set_connection.tsumego_id
				) collections ON collections.tsumego_id = tsumego.id
				LEFT JOIN (
					SELECT tsumego_id, MIN(date) AS since FROM schedule WHERE published = 1 GROUP BY tsumego_id
				) published ON published.tsumego_id = tsumego.id
			)
			SELECT DATE_FORMAT(changed, '%Y-%m-01') AS month, SUM(delta) AS delta
			FROM (
				SELECT since AS changed, 1 AS delta FROM public_problem
				UNION ALL
				SELECT until, -1 FROM public_problem WHERE until IS NOT NULL
			) changes
			GROUP BY 1
			ORDER BY 1"));
	}

	//How many players registered in each month.
	/** @return array<string, int> by the first day of the month, oldest first */
	private static function userChangesByMonth(): array
	{
		return self::byMonth(Util::query("
			SELECT DATE_FORMAT(created, '%Y-%m-01') AS month, COUNT(*) AS delta
			FROM user
			WHERE created IS NOT NULL
			GROUP BY 1
			ORDER BY 1"));
	}

	//The rows of a monthly query as month => change. What has no date (old problems without a
	//creation time) belongs to the very beginning, so it goes to the first month.
	/** @return array<string, int> */
	private static function byMonth(array $rows): array
	{
		$undated = 0;
		$changes = [];
		foreach ($rows as $row)
			if ($row['month'] === null)
				$undated += (int) $row['delta'];
			else
				$changes[(string) $row['month']] = (int) $row['delta'];
		if ($undated !== 0 && $changes)
			$changes[array_key_first($changes)] += $undated;
		return $changes;
	}

	/**
	 * @param array<string, int> $changes
	 * @return array<string, int>
	 */
	private static function cumulative(array $changes): array
	{
		$total = 0;
		$totals = [];
		foreach ($changes as $month => $change)
		{
			$total += $change;
			$totals[$month] = $total;
		}
		return $totals;
	}
}
