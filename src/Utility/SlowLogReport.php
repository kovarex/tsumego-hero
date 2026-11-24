<?php

namespace App\Utility;

//What the slow log (RequestTiming) says about a period: the routes that were slow most often with their median and
//95th percentile, where the time of the slow requests went, the queries that took most of it, the hours they came
//in and the minutes in which many came at once, as a stall of the whole site makes every request slow at the same
//time. `bin/cake slow_report` prints it.
class SlowLogReport
{
	//The parts of a request's time in the slow log, by the name the report gives them
	public const array PARTS = [
		'queue' => 'queue_ms',
		'boot' => 'boot_ms',
		'db connect' => 'db_connect_ms',
		'db' => 'db_ms',
		'cache' => 'cache_ms',
		'external' => 'external_ms',
		'php' => 'php_ms',
	];

	//The entries of the period, oldest first
	/** @var list<array<string, mixed>> */
	public array $entries = [];

	//The lines of the files that are no JSON of an entry
	public int $unreadableLines = 0;

	//Reads the entries of the files written at $since or later (a Unix time, null for all of them)
	/** @param list<string> $files */
	public static function fromFiles(array $files, ?int $since = null): self
	{
		$report = new self();
		foreach ($files as $file)
		{
			$handle = @fopen($file, 'r');
			if ($handle === false)
				continue;
			while (($line = fgets($handle)) !== false)
			{
				if (trim($line) === '')
					continue;
				$entry = json_decode($line, true);
				$time = is_array($entry) && is_string($entry['time'] ?? null) ? strtotime($entry['time']) : false;
				if ($time === false || !isset($entry['total_ms']))
				{
					$report->unreadableLines++;
					continue;
				}
				if ($since === null || $time >= $since)
					$report->entries[] = $entry + ['timestamp' => $time];
			}
			fclose($handle);
		}
		usort($report->entries, fn(array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);
		return $report;
	}

	//The value at $fraction of the sorted values, by the nearest rank
	/** @param list<float> $sorted */
	public static function percentile(array $sorted, float $fraction): float
	{
		if (!$sorted)
			return 0.0;
		return $sorted[max(0, (int) ceil($fraction * count($sorted)) - 1)];
	}

	//By route ("GET Sets/view"), the most frequent first: how many, the median, the 95th percentile and the
	//longest total, and the share of the time of each part
	/** @return list<array{route: string, count: int, p50: float, p95: float, max: float, shares: array<string, float>}> */
	public function routes(int $limit): array
	{
		$byRoute = [];
		foreach ($this->entries as $entry)
			$byRoute[$entry['method'] . ' ' . $entry['route']][] = $entry;
		$routes = [];
		foreach ($byRoute as $route => $entries)
		{
			$totals = array_map(fn(array $entry) => (float) $entry['total_ms'], $entries);
			sort($totals);
			$routes[] = [
				'route' => (string) $route,
				'count' => count($entries),
				'p50' => self::percentile($totals, 0.5),
				'p95' => self::percentile($totals, 0.95),
				'max' => (float) end($totals),
				'shares' => self::shares($entries),
			];
		}
		usort($routes, fn(array $a, array $b) => [$b['count'], $b['p95']] <=> [$a['count'], $a['p95']]);
		return array_slice($routes, 0, $limit);
	}

	//The share of each part in the time of the entries, in percent
	/**
	 * @param list<array<string, mixed>> $entries
	 * @return array<string, float>
	 */
	public static function shares(array $entries): array
	{
		$total = array_sum(array_map(fn(array $entry) => (float) $entry['total_ms'], $entries));
		$shares = [];
		foreach (self::PARTS as $name => $field)
		{
			$part = array_sum(array_map(fn(array $entry) => (float) ($entry[$field] ?? 0), $entries));
			$shares[$name] = $total > 0 ? round(100 * $part / $total, 1) : 0.0;
		}
		return $shares;
	}

	//The queries that took the most time over all the entries, the same SQL of different entries as one
	/** @return list<array{sql: string, ms: float, count: int, requests: int}> */
	public function queries(int $limit): array
	{
		$queries = [];
		foreach ($this->entries as $entry)
			foreach ((array) ($entry['queries'] ?? []) as $query)
			{
				$sql = (string) ($query['sql'] ?? '');
				$queries[$sql]['ms'] = ($queries[$sql]['ms'] ?? 0.0) + (float) ($query['ms'] ?? 0);
				$queries[$sql]['count'] = ($queries[$sql]['count'] ?? 0) + (int) ($query['count'] ?? 0);
				$queries[$sql]['requests'] = ($queries[$sql]['requests'] ?? 0) + 1;
			}
		uasort($queries, fn(array $a, array $b) => $b['ms'] <=> $a['ms']);
		$slowest = [];
		foreach (array_slice($queries, 0, $limit, true) as $sql => $query)
			$slowest[] = ['sql' => (string) $sql] + $query;
		return $slowest;
	}

	//How many entries came in each hour of the day (UTC), all 24 of them
	/** @return array<int, int> */
	public function hours(): array
	{
		$hours = array_fill(0, 24, 0);
		foreach ($this->entries as $entry)
			$hours[(int) gmdate('G', $entry['timestamp'])]++;
		return $hours;
	}

	//The minutes with the most entries, at least two: how many, by how many players and on how many routes.
	//Many players slow in the same minute on many routes is a stall of the whole site, not of a page.
	/** @return list<array{minute: string, count: int, players: int, routes: int}> */
	public function bursts(int $limit): array
	{
		$minutes = [];
		foreach ($this->entries as $entry)
			$minutes[gmdate('Y-m-d H:i', $entry['timestamp'])][] = $entry;
		$bursts = [];
		foreach ($minutes as $minute => $entries)
			if (count($entries) >= 2)
				$bursts[] = [
					'minute' => (string) $minute,
					'count' => count($entries),
					'players' => count(array_unique(array_filter(array_map(fn(array $entry) => (int) ($entry['user_id'] ?? 0), $entries)))),
					'routes' => count(array_unique(array_map(fn(array $entry) => $entry['method'] . ' ' . $entry['route'], $entries))),
				];
		usort($bursts, fn(array $a, array $b) => [$b['count'], $b['minute']] <=> [$a['count'], $a['minute']]);
		return array_slice($bursts, 0, $limit);
	}

	//How many of the entries ran with a PHP session started, which the site never starts
	public function withSession(): int
	{
		return count(array_filter($this->entries, fn(array $entry) => !empty($entry['session'])));
	}

	//How many of the entries ended in a fatal error of PHP, its time limit among them
	public function fatal(): int
	{
		return count(array_filter($this->entries, fn(array $entry) => isset($entry['error'])));
	}
}
