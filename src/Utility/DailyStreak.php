<?php

namespace App\Utility;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

//The daily streak: the days in a row on which the player solved a problem that gave XP.
//A player's day ends at DAY_START_HOUR in their own time zone (user.time_zone, from the browser,
//UTC until it is known). The row keeps the streak as it was at the last day that counted
//(streak_last_day); what happened since is worked out from today whenever it is read, so missed
//days use up freezes or end the streak without any cron, however long the player was away.
//Every rule of the streak is here, so the numbers below are the only place to change them.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class DailyStreak
{
	//A freeze is earned each time the streak reaches a multiple of this many days
	public const int FREEZE_EVERY_DAYS = 7;
	public const int MAXIMUM_FREEZES = 2;
	//The hour of the player's local time at which their day turns
	public const int DAY_START_HOUR = 3;

	public function __construct(
		public readonly int $length,
		public readonly int $best,
		public readonly int $freezes,
		public readonly ?string $lastDay,
		public readonly bool $doneToday) {}

	//The streak of the player as it stands now, for showing it; nothing is written
	/** @param UserRow $user */
	public static function ofUser(array $user, ?DateTimeImmutable $now = null): self
	{
		return self::evaluate(
			(int) ($user['streak_length'] ?? 0),
			(int) ($user['streak_best'] ?? 0),
			(int) ($user['streak_freezes'] ?? 0),
			self::dayOf($user['streak_last_day'] ?? null),
			self::today($user['time_zone'] ?? null, $now ?? new DateTimeImmutable()));
	}

	//The streak as of $today, from what was stored at the last day that counted. Each missed day takes
	//a freeze, which keeps the streak without making it longer; more missed days than freezes end it,
	//and the freezes are gone with it.
	public static function evaluate(int $length, int $best, int $freezes, ?string $lastDay, string $today): self
	{
		if ($length <= 0 || $lastDay === null)
			return new self(0, $best, $freezes, null, false);
		$gap = self::daysBetween($lastDay, $today);
		//A day ahead of today happens when the player moved west; it was done
		if ($gap <= 0)
			return new self($length, $best, $freezes, $lastDay, true);
		$missed = $gap - 1;
		if ($missed == 0)
			return new self($length, $best, $freezes, $lastDay, false);
		if ($missed <= $freezes)
			return new self($length, $best, $freezes - $missed, self::addDays($today, -1), false);
		return new self(0, $best, 0, null, false);
	}

	//The streak after a solve that gave XP on $today: the first one of the day adds the day
	public function afterSolve(string $today): self
	{
		if ($this->doneToday)
			return $this;
		$length = $this->length + 1;
		$freezes = $length % self::FREEZE_EVERY_DAYS == 0 ? min(self::MAXIMUM_FREEZES, $this->freezes + 1) : $this->freezes;
		return new self($length, max($this->best, $length), $freezes, $today, true);
	}

	//Counts a solve that gave XP for the player (Level::addXPAsResultOfTsumegoSolving). Only the first
	//one of the day writes, every later one of the same day costs nothing.
	/** @param UserRow $user */
	public static function countSolve(array &$user, ?DateTimeImmutable $now = null): void
	{
		$now ??= new DateTimeImmutable();
		$today = self::today($user['time_zone'] ?? null, $now);
		$streak = self::ofUser($user, $now);
		if ($streak->doneToday)
			return;
		$streak = $streak->afterSolve($today);
		$user['streak_length'] = $streak->length;
		$user['streak_best'] = $streak->best;
		$user['streak_freezes'] = $streak->freezes;
		$user['streak_last_day'] = $streak->lastDay;
		Util::execute('UPDATE user SET streak_length = ?, streak_best = ?, streak_freezes = ?, streak_last_day = ? WHERE id = ?',
			[$streak->length, $streak->best, $streak->freezes, $streak->lastDay, $user['id']]);
	}

	//The player's day at $now, as Y-m-d: the local date, or the day before it until DAY_START_HOUR.
	//It is read from the wall clock, so the day changes at the same local hour across a DST change.
	public static function today(?string $timeZone, DateTimeImmutable $now): string
	{
		$local = $now->setTimezone(self::timeZone($timeZone));
		$day = $local->format('Y-m-d');
		return (int) $local->format('G') < self::DAY_START_HOUR ? self::addDays($day, -1) : $day;
	}

	//The zone of the player, UTC until the browser told it (or when it is not a valid one)
	public static function timeZone(?string $timeZone): DateTimeZone
	{
		return self::isValidTimeZone($timeZone) ? new DateTimeZone((string) $timeZone) : new DateTimeZone('UTC');
	}

	//Only a named zone the tz database knows, as Intl.DateTimeFormat gives it; not an offset or an abbreviation
	public static function isValidTimeZone(?string $timeZone): bool
	{
		if ($timeZone === null || $timeZone === '' || strlen($timeZone) > 64)
			return false;
		try
		{
			return ((array) new DateTimeZone($timeZone))['timezone_type'] === 3;
		}
		catch (Exception)
		{
			return false;
		}
	}

	//What the page shows and the result of a solve returns (webroot/js/DailyStreak.js)
	/** @return array{length: int, best: int, freezes: int, done_today: bool} */
	public function toArray(): array
	{
		return ['length' => $this->length, 'best' => $this->best, 'freezes' => $this->freezes, 'done_today' => $this->doneToday];
	}

	//The DATE column comes as a string or as a date object, depending on how the row was read
	private static function dayOf(mixed $value): ?string
	{
		if ($value === null || $value === '')
			return null;
		if ($value instanceof DateTimeInterface || (is_object($value) && method_exists($value, 'format')))
			return $value->format('Y-m-d');
		return substr((string) $value, 0, 10);
	}

	private static function daysBetween(string $from, string $to): int
	{
		$utc = new DateTimeZone('UTC');
		return intdiv(new DateTimeImmutable($to, $utc)->getTimestamp() - new DateTimeImmutable($from, $utc)->getTimestamp(), 86400);
	}

	private static function addDays(string $day, int $days): string
	{
		return new DateTimeImmutable($day, new DateTimeZone('UTC'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
	}
}
