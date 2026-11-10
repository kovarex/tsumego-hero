<?php

namespace App\Utility;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

//The daily streak: the days in a row on which the player did what the day asks, which is the XP of
//PROBLEMS_A_DAY problems of the player's rating (solves and the mistake training's XP together) and
//REVIEWS_A_DAY reviews the mistake training counts as solved, or as many as were due. Both are fixed by
//the day's first result, so the solves that raise the rating during the day don't raise what it asks.
//A missed day takes a freeze, and without one it ends the streak.
//A player's day ends at DAY_START_HOUR in their own time zone (user.time_zone, from the browser,
//UTC until it is known). The row keeps the streak as it was at the last day that counted
//(streak_last_day), and what today did so far (streak_day_); what happened since is worked out from
//today whenever it is read, so missed days use up freezes or end the streak without any cron,
//however long the player was away.
//Every rule of the streak is here, so the numbers below are the only place to change them.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class DailyStreak
{
	//A freeze is earned every this many days of the streak; they add up without a limit
	public const int FREEZE_EVERY_DAYS = 7;
	//The hour of the player's local time at which their day turns
	public const int DAY_START_HOUR = 3;
	//What a day asks: the XP of this many problems of the player's rating, and this many reviews
	public const int PROBLEMS_A_DAY = 3;
	public const int REVIEWS_A_DAY = 3;

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
		return self::stored($user)->asOf(self::today($user['time_zone'] ?? null, $now ?? Clock::now()));
	}

	//The streak as the row keeps it, as of its last day
	/** @param UserRow $user */
	public static function stored(array $user): self
	{
		return new self(
			(int) ($user['streak_length'] ?? 0),
			(int) ($user['streak_best'] ?? 0),
			(int) ($user['streak_freezes'] ?? 0),
			self::dayOf($user['streak_last_day'] ?? null),
			false);
	}

	//The streak as of $today, from what was stored at the last day that counted. Each missed day takes
	//a freeze, which keeps the streak without making it longer; more missed days than freezes end it,
	//and the freezes are gone with it.
	public function asOf(string $today): self
	{
		if ($this->lastDay === null || $this->length <= 0)
			return new self(0, $this->best, $this->freezes, null, false);
		$gap = self::daysBetween($this->lastDay, $today);
		//A day ahead of today happens when the player moved west; it was done
		if ($gap <= 0)
			return new self($this->length, $this->best, $this->freezes, $this->lastDay, true);
		$missed = $gap - 1;
		if ($missed == 0)
			return new self($this->length, $this->best, $this->freezes, $this->lastDay, false);
		if ($missed <= $this->freezes)
			return new self($this->length, $this->best, $this->freezes - $missed, self::addDays($today, -1), false);
		return new self(0, $this->best, 0, null, false);
	}

	//The streak once $today counted; a day counts once
	public function countDay(string $today): self
	{
		if ($this->doneToday)
			return $this;
		$length = $this->length + 1;
		$freezes = $this->freezes + ($length % self::FREEZE_EVERY_DAYS == 0 ? 1 : 0);
		return new self($length, max($this->best, $length), $freezes, $today, true);
	}

	//The XP a day asks of a player of $rating
	public static function xpRequired(float $rating): int
	{
		return self::PROBLEMS_A_DAY * Rating::ratingToXP($rating, 1);
	}

	//What a result changes of the streak (PlayResultProcessorComponent::processDailyStreak), as fields of
	//the user row: the solve gave $xp XP, and $solvedReview says the mistake training counted it as a
	//solved review. The first result of the day that adds to it fixes what the day asks: the XP of the
	//rating in $user, and the reviews $reviewsDue gives, which is asked then only. Nothing changes for a
	//result that gives neither.
	/**
	 * @param UserRow $user
	 * @param callable(): int $reviewsDue
	 * @return array<string, int|string|null>
	 */
	public static function afterResult(array $user, int $xp, bool $solvedReview, callable $reviewsDue, ?DateTimeImmutable $now = null): array
	{
		if ($xp <= 0 && !$solvedReview)
			return [];
		$today = self::today($user['time_zone'] ?? null, $now ?? Clock::now());
		$progress = self::progressOf($user, $today);
		$reviews = $progress['reviews'] + ($solvedReview ? 1 : 0);
		$fields = [
			'streak_day' => $today,
			'streak_day_xp' => $progress['xp'] + max(0, $xp),
			'streak_day_reviews' => $reviews,
			'streak_day_reviews_required' => $progress['reviews_required'] ?? min(self::REVIEWS_A_DAY, $reviews + $reviewsDue()),
			'streak_day_xp_required' => $progress['xp_required'] ?? self::xpRequired((float) ($user['rating'] ?? 0)),
		];
		$streak = self::stored($user)->asOf($today);
		$met = $fields['streak_day_xp'] >= $fields['streak_day_xp_required'] && $fields['streak_day_reviews'] >= $fields['streak_day_reviews_required'];
		if ($streak->doneToday || !$met)
			return $fields;
		$streak = $streak->countDay($today);
		return $fields + [
			'streak_length' => $streak->length,
			'streak_best' => $streak->best,
			'streak_freezes' => $streak->freezes,
			'streak_last_day' => $streak->lastDay,
		];
	}

	//What $today did so far: the XP of its solves and the reviews solved, and the XP and the reviews it
	//asks, null until its first result fixed them. Counters of an earlier day are today's zero.
	/**
	 * @param UserRow $user
	 * @return array{xp: int, reviews: int, xp_required: int|null, reviews_required: int|null}
	 */
	public static function progressOf(array $user, string $today): array
	{
		if (self::dayOf($user['streak_day'] ?? null) !== $today)
			return ['xp' => 0, 'reviews' => 0, 'xp_required' => null, 'reviews_required' => null];
		$xpRequired = $user['streak_day_xp_required'] ?? null;
		$reviewsRequired = $user['streak_day_reviews_required'] ?? null;
		return [
			'xp' => (int) ($user['streak_day_xp'] ?? 0),
			'reviews' => (int) ($user['streak_day_reviews'] ?? 0),
			'xp_required' => $xpRequired === null ? null : (int) $xpRequired,
			'reviews_required' => $reviewsRequired === null ? null : (int) $reviewsRequired];
	}

	//How far today is towards what it asks, from 0 to 1: the share of the XP, averaged with the share
	//of the reviews when it asks any. The dim flame fills this far (webroot/js/DailyStreak.js).
	public static function progressShare(int $xp, int $xpRequired, int $reviews, int $reviewsRequired): float
	{
		$share = fn(int $done, int $required): float => $required <= 0 ? 1.0 : min(1.0, $done / $required);
		if ($reviewsRequired <= 0)
			return $share($xp, $xpRequired);
		return ($share($xp, $xpRequired) + $share($reviews, $reviewsRequired)) / 2;
	}

	//What the page shows and the result of a solve returns (webroot/js/DailyStreak.js): the streak, the
	//freezes, whether today is done, and what today did towards it, also as a share. Until the first
	//result of the day the XP asked is that of the rating now, and the reviews the most a day can ask.
	/**
	 * @param UserRow $user
	 * @return array{length: int, best: int, freezes: int, done_today: bool, xp: int, xp_required: int, reviews: int, reviews_required: int, progress: float}
	 */
	public static function view(array $user, ?DateTimeImmutable $now = null): array
	{
		$now ??= Clock::now();
		$streak = self::ofUser($user, $now);
		$progress = self::progressOf($user, self::today($user['time_zone'] ?? null, $now));
		$xpRequired = $progress['xp_required'] ?? self::xpRequired((float) ($user['rating'] ?? 0));
		$reviewsRequired = $progress['reviews_required'] ?? self::REVIEWS_A_DAY;
		return [
			'length' => $streak->length,
			'best' => $streak->best,
			'freezes' => $streak->freezes,
			'done_today' => $streak->doneToday,
			'xp' => $progress['xp'],
			'xp_required' => $xpRequired,
			'reviews' => $progress['reviews'],
			'reviews_required' => $reviewsRequired,
			'progress' => $streak->doneToday ? 1.0 : round(self::progressShare($progress['xp'], $xpRequired, $progress['reviews'], $reviewsRequired), 3),
		];
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

	//The browser puts its zone in the time_zone cookie when it differs from the one the page has
	//(webroot/js/DailyStreak.js); the next request stores it with the other settings of the cookies
	//(AppController::saveSettingsFromCookies). An invalid or unchanged zone changes nothing.
	/**
	 * @param UserRow $user
	 * @return array{time_zone?: string}
	 */
	public static function timeZoneChange(?string $cookie, array $user): array
	{
		if (!self::isValidTimeZone($cookie) || $cookie === ($user['time_zone'] ?? null))
			return [];
		return ['time_zone' => (string) $cookie];
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

	public static function addDays(string $day, int $days): string
	{
		return new DateTimeImmutable($day, new DateTimeZone('UTC'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
	}
}
