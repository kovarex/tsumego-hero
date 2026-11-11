<?php

namespace App\Command;

use App\Utility\UserRatingDay;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Throwable;

//bin/cake backfill_rating_days: fills user_rating_day with the days played before the table was
//(UserRatingDay::backfill()), run once after the deploy that added it. It is safe to run while the site is live and to
//run again: a day keeps its highest rating. It prints only a summary at the end, or the error it failed with.
class BackfillRatingDaysCommand extends Command
{
	public static function defaultName(): string
	{
		return 'backfill_rating_days';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser->setDescription('Fills the highest rating of each day of each player from their attempts.');
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		try
		{
			$filled = UserRatingDay::backfill();
			$io->out('backfill_rating_days: ' . $filled['days'] . ' days of ' . $filled['players'] . ' players');
			return static::CODE_SUCCESS;
		}
		catch (Throwable $exception)
		{
			$io->err('backfill_rating_days failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
			return static::CODE_ERROR;
		}
	}
}
