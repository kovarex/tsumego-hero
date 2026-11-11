<?php

namespace App\Command;

use App\Utility\DailyReset;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Throwable;

//bin/cake cron_hourly: gives the players whose day turned since the last run their hearts and hero powers back
//(DailyReset::resetDuePlayers()), as each player's day turns at 03:00 in their own time zone. Run every hour, or more
//often; a run with nobody behind changes nothing. It prints nothing unless it fails, as the scheduler mails any
//output: a failure exits with an error code and its message.
class CronHourlyCommand extends Command
{
	public static function defaultName(): string
	{
		return 'cron_hourly';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser->setDescription('Gives the players whose day turned their hearts and hero powers back.');
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		try
		{
			DailyReset::resetDuePlayers();
			return static::CODE_SUCCESS;
		}
		catch (Throwable $exception)
		{
			$io->err('cron_hourly failed: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
			return static::CODE_ERROR;
		}
	}
}
