<?php

namespace App\Command;

use App\Utility\DailyCron;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Throwable;

//bin/cake cron_daily: the daily work of the site (DailyCron), run by the scheduler at midnight UTC. Running it again
//the same day only publishes what is due. A failure exits with an error code and its message, for the task's log.
class CronDailyCommand extends Command
{
	public static function defaultName(): string
	{
		return 'cron_daily';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser->setDescription('The daily work of the site: publishing, the day record, the status resets, the solved counts, the trash.');
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		try
		{
			$io->out(DailyCron::run() ? 'Did the work of the day.' : 'The day was done already, published what was due.');
			return static::CODE_SUCCESS;
		}
		catch (Throwable $exception)
		{
			$io->err('cron_daily failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
			return static::CODE_ERROR;
		}
	}
}
