<?php

namespace App\Command;

use App\Utility\Util;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

//bin/cake rum_report [--days=7] [--by-day]: the timings the players' browsers measured on the play page
//(rum_sample, see RumController::METRICS), as the median, the 75th and the 95th percentile of each
//metric, with the median of the server's part of it, to compare a change with what was before it.
class RumReportCommand extends Command
{
	public static function defaultName(): string
	{
		return 'rum_report';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser
			->setDescription('The percentiles of the play page timings the browsers measured.')
			->addOption('days', ['default' => '7', 'help' => 'The last this many days'])
			->addOption('by-day', ['boolean' => true, 'help' => 'A row for each day, not one for the whole time']);
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		$byDay = (bool) $args->getOption('by-day');
		$samples = Util::query('SELECT metric, DATE(created) AS day, milliseconds, server_milliseconds FROM rum_sample
			WHERE created >= CURDATE() - INTERVAL ? DAY ORDER BY metric, created', [max(1, (int) $args->getOption('days')) - 1]);
		$groups = [];
		foreach ($samples as $sample)
			$groups[$sample['metric'] . ($byDay ? ' ' . $sample['day'] : '')][] = $sample;

		$io->out(sprintf('%-28s %7s %8s %8s %8s %10s', 'metric', 'count', 'p50 ms', 'p75 ms', 'p95 ms', 'server p50'));
		foreach ($groups as $name => $group)
		{
			$milliseconds = array_map('intval', array_column($group, 'milliseconds'));
			$server = array_map('intval', array_filter(array_column($group, 'server_milliseconds'), fn($value) => $value !== null));
			$io->out(sprintf('%-28s %7d %8d %8d %8d %10s', $name, count($group),
				self::percentile($milliseconds, 50), self::percentile($milliseconds, 75), self::percentile($milliseconds, 95),
				$server ? self::percentile($server, 50) : '-'));
		}
		if (!$groups)
			$io->out('no samples in that time');
		return self::CODE_SUCCESS;
	}

	//The nearest-rank percentile
	/** @param list<int> $values */
	public static function percentile(array $values, int $percent): int
	{
		sort($values);
		return $values[max(0, (int) ceil(count($values) * $percent / 100) - 1)];
	}
}
