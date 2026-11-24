<?php

namespace App\Command;

use App\Utility\RequestTiming;
use App\Utility\SlowLogReport;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

//bin/cake slow_report: sums up the slow log (logs/slow.log and its rotated files, see RequestTiming) of the last
//days: the routes slow most often with their median and 95th percentile, where the time went (the wait for PHP,
//its start, the database, the caches, the calls out, the rest of PHP), the queries that took most of it, the
//hours of the day and the minutes in which many requests were slow at once.
class SlowReportCommand extends Command
{
	public static function defaultName(): string
	{
		return 'slow_report';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser
			->setDescription('Sums up the slow log: the slow routes, where their time went, the slowest queries, when.')
			->addOption('days', ['help' => 'The days back to look at', 'default' => '7'])
			->addOption('all', ['help' => 'Everything the log has, whatever its age', 'boolean' => true])
			->addOption('top', ['help' => 'How many routes and queries to list', 'default' => '15'])
			->addOption('file', ['help' => 'The slow log, its rotated files (.1, .2...) are read too; the configured one by default']);
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		$files = RequestTiming::logFiles($args->getOption('file') !== null ? (string) $args->getOption('file') : null);
		$since = $args->getOption('all') ? null : time() - (int) $args->getOption('days') * 24 * 3600;
		$report = SlowLogReport::fromFiles($files, $since);
		$top = max(1, (int) $args->getOption('top'));

		if (!$report->entries)
		{
			$io->out('No slow requests ' . ($since === null ? 'in the log' : 'since ' . gmdate('Y-m-d H:i', $since) . ' UTC')
				. ' (' . ($files ? implode(', ', $files) : 'no log file at ' . RequestTiming::logPath()) . ').');
			return static::CODE_SUCCESS;
		}

		$first = $report->entries[0]['timestamp'];
		$last = $report->entries[count($report->entries) - 1]['timestamp'];
		$io->out(count($report->entries) . ' slow requests, ' . gmdate('Y-m-d H:i', $first) . ' to ' . gmdate('Y-m-d H:i', $last) . ' UTC'
			. ' (over ' . RequestTiming::thresholdMilliseconds() . ' ms, from ' . implode(', ', $files) . ')');
		if ($report->unreadableLines)
			$io->out($report->unreadableLines . ' lines of the log could not be read.');
		$io->out('Where their time went: ' . self::sharesText(SlowLogReport::shares($report->entries)));
		$io->out('From crawlers and scripts: ' . $report->fromBots() . '. With a PHP session started: ' . $report->withSession()
			. '. Ended by a fatal error (time limit...): ' . $report->fatal() . '.');

		$io->out();
		$io->out('Routes, the most often slow first, and the share of each part of their time:');
		$names = array_keys(SlowLogReport::PARTS);
		$io->out(sprintf('%-40s %6s %8s %8s %8s  ', 'route', 'count', 'p50 ms', 'p95 ms', 'max ms') . implode(' ', array_map(fn(string $name) => sprintf('%6s', self::shortName($name)), $names)));
		foreach ($report->routes($top) as $route)
			$io->out(sprintf('%-40s %6d %8.0f %8.0f %8.0f  ', mb_strimwidth($route['route'], 0, 40), $route['count'], $route['p50'], $route['p95'], $route['max'])
				. implode(' ', array_map(fn(string $name) => sprintf('%5.0f%%', $route['shares'][$name]), $names)));

		$io->out();
		$io->out('Queries that took the most time, the ones of every slow request together:');
		$io->out(sprintf('%10s %7s %8s  %s', 'total ms', 'runs', 'requests', 'SQL'));
		foreach ($report->queries($top) as $query)
			$io->out(sprintf('%10.0f %7d %8d  %s', $query['ms'], $query['count'], $query['requests'], $query['sql']));

		$io->out();
		$io->out('Slow requests by hour of the day (UTC):');
		$hours = $report->hours();
		$most = max(1, max($hours));
		foreach ($hours as $hour => $count)
			$io->out(sprintf('  %02d %5d %s', $hour, $count, str_repeat('#', (int) ceil(40 * $count / $most))));

		$bursts = $report->bursts($top);
		if ($bursts)
		{
			$io->out();
			$io->out('Minutes with the most slow requests (many players on many routes at once is the whole site stalling):');
			foreach ($bursts as $burst)
				$io->out(sprintf('  %s UTC  %4d requests, %3d players, %3d routes', $burst['minute'], $burst['count'], $burst['players'], $burst['routes']));
		}
		return static::CODE_SUCCESS;
	}

	/** @param array<string, float> $shares */
	private static function sharesText(array $shares): string
	{
		return implode(', ', array_map(fn(string $name, float $share) => $name . ' ' . $share . '%', array_keys($shares), $shares));
	}

	private static function shortName(string $name): string
	{
		return match ($name)
		{
			'db connect' => 'dbconn',
			'external' => 'ext',
			default => $name,
		};
	}
}
