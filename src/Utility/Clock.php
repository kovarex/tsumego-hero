<?php

namespace App\Utility;

use DateTimeImmutable;

//The moment the code takes as now for the days of the players (DailyStreak, MistakeTraining). A test
//fixes it (freeze()) to play a moment of the day in a player's time zone; otherwise it is the real time.
class Clock
{
	private static ?DateTimeImmutable $frozen = null;

	public static function now(): DateTimeImmutable
	{
		return self::$frozen ?? new DateTimeImmutable();
	}

	//Fix now at $moment, or let the real time run again with null
	public static function freeze(?DateTimeImmutable $moment): void
	{
		self::$frozen = $moment;
	}
}
