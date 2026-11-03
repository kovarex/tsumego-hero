<?php

namespace App\Model\Table;

//How a problem of a time mode run ended: still to play, solved, failed, out of time or skipped.
class TimeModeAttemptStatusTable extends AppTable
{
	// the rows of time_mode_attempt_status
	public const QUEUED = 1;
	public const SOLVED = 2;
	public const FAILED = 3;
	public const TIMEOUT = 4;
	public const SKIPPED = 5;

	//The names of the rows, as the results show them
	private const NAMES = [
		self::QUEUED => 'queued',
		self::SOLVED => 'solved',
		self::FAILED => 'failed',
		self::TIMEOUT => 'timeout',
		self::SKIPPED => 'skipped'];

	public static function name(int $statusID): string
	{
		return self::NAMES[$statusID] ?? 'unknown';
	}
}
