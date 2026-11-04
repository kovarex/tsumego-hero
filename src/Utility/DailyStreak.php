<?php

namespace App\Utility;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

//The daily streak: the days in a row on which the player did what the day asks. It has two modes,
//picked in the settings (user.streak_mode_setting_id), each with its own length and best:
//- normal: a solve that gives XP, or a review the mistake training counts as solved;
//- golden: the XP of GOLDEN_PROBLEMS problems of the player's rating, and GOLDEN_REVIEWS reviews of
//  the mistake training, or as many as there are.
//A day counts in the mode that is on when its requirement is met. A missed day takes a freeze, and
//without one it ends both streaks; the freezes and the last day are shared.
//A player's day ends at DAY_START_HOUR in their own time zone (user.time_zone, from the browser,
//UTC until it is known). The row keeps the streak as it was at the last day that counted
//(streak_last_day); what happened since is worked out from today whenever it is read, so missed
//days use up freezes or end the streaks without any cron, however long the player was away.
//Every rule of the streak is here, so the numbers below are the only place to change them.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class DailyStreak
{
	//A freeze is earned each time the two streaks together reach a multiple of this many days; they add up without a limit
	public const int FREEZE_EVERY_DAYS = 7;
	//The hour of the player's local time at which their day turns
	public const int DAY_START_HOUR = 3;
	//The ids of the streak_mode_setting table
	public const int MODE_NORMAL = 1;
	public const int MODE_GOLDEN = 2;
	//What a golden day asks: the XP of this many problems of the player's rating, and this many reviews
	public const int GOLDEN_PROBLEMS = 3;
	public const int GOLDEN_REVIEWS = 3;

	public function __construct(
		public readonly int $length,
		public readonly int $best,
		public readonly int $goldenLength,
		public readonly int $goldenBest,
		public readonly int $freezes,
		public readonly ?string $lastDay,
		public readonly bool $doneToday) {}

	//The streak of the player as it stands now, for showing it; nothing is written
	/** @param UserRow $user */
	public static function ofUser(array $user, ?DateTimeImmutable $now = null): self
	{
		return self::stored($user)->asOf(self::today($user['time_zone'] ?? null, $now ?? new DateTimeImmutable()));
	}

	//The streak as the row keeps it, as of its last day
	/** @param UserRow $user */
	public static function stored(array $user): self
	{
		return new self(
			(int) ($user['streak_length'] ?? 0),
			(int) ($user['streak_best'] ?? 0),
			(int) ($user['golden_streak_length'] ?? 0),
			(int) ($user['golden_streak_best'] ?? 0),
			(int) ($user['streak_freezes'] ?? 0),
			self::dayOf($user['streak_last_day'] ?? null),
			false);
	}

	//The streak as of $today, from what was stored at the last day that counted. Each missed day takes
	//a freeze, which keeps the streaks without making them longer; more missed days than freezes end
	//both, and the freezes are gone with them.
	public function asOf(string $today): self
	{
		if ($this->lastDay === null || $this->length + $this->goldenLength <= 0)
			return new self(0, $this->best, 0, $this->goldenBest, $this->freezes, null, false);
		$gap = self::daysBetween($this->lastDay, $today);
		//A day ahead of today happens when the player moved west; it was done
		if ($gap <= 0)
			return $this->with(lastDay: $this->lastDay, doneToday: true);
		$missed = $gap - 1;
		if ($missed == 0)
			return $this->with(lastDay: $this->lastDay, doneToday: false);
		if ($missed <= $this->freezes)
			return $this->with(freezes: $this->freezes - $missed, lastDay: self::addDays($today, -1), doneToday: false);
		return new self(0, $this->best, 0, $this->goldenBest, 0, null, false);
	}

	//The streak once $today counted in $mode; a day counts once, in the mode it counted in first
	public function countDay(int $mode, string $today): self
	{
		if ($this->doneToday)
			return $this;
		$golden = $mode === self::MODE_GOLDEN;
		$length = $this->length + ($golden ? 0 : 1);
		$goldenLength = $this->goldenLength + ($golden ? 1 : 0);
		$freezes = $this->freezes + (($length + $goldenLength) % self::FREEZE_EVERY_DAYS == 0 ? 1 : 0);
		return new self($length, max($this->best, $length), $goldenLength, max($this->goldenBest, $goldenLength), $freezes, $today, true);
	}

	private function with(?int $freezes = null, ?string $lastDay = null, bool $doneToday = false): self
	{
		return new self($this->length, $this->best, $this->goldenLength, $this->goldenBest, $freezes ?? $this->freezes, $lastDay, $doneToday);
	}

	//The XP a golden day asks of a player of $rating
	public static function goldenXp(float $rating): int
	{
		return self::GOLDEN_PROBLEMS * Rating::ratingToXP($rating, 1);
	}

	//What a result changes of the streak (PlayResultProcessorComponent::processDailyStreak), as fields of
	//the user row: the solve gave $xp XP, and $solvedReview says the mistake training counted it as a
	//solved review. $reviewsDue gives how many reviews are due now; it is asked only in the golden mode,
	//until the day counts. Nothing changes for a result that gives neither.
	/**
	 * @param UserRow $user
	 * @param callable(): int $reviewsDue
	 * @return array<string, int|string|null>
	 */
	public static function afterResult(array $user, int $xp, bool $solvedReview, callable $reviewsDue, ?DateTimeImmutable $now = null): array
	{
		if ($xp <= 0 && !$solvedReview)
			return [];
		$today = self::today($user['time_zone'] ?? null, $now ?? new DateTimeImmutable());
		$progress = self::progressOf($user, $today);
		$fields = [
			'streak_day' => $today,
			'streak_day_xp' => $progress['xp'] + max(0, $xp),
			'streak_day_reviews' => $progress['reviews'] + ($solvedReview ? 1 : 0),
			'streak_day_reviews_required' => $progress['reviews_required'],
		];
		$streak = self::stored($user)->asOf($today);
		if ($streak->doneToday)
			return $fields;

		$mode = self::modeOf($user);
		if ($mode === self::MODE_GOLDEN)
		{
			$fields['streak_day_reviews_required'] = min(self::GOLDEN_REVIEWS, $fields['streak_day_reviews'] + $reviewsDue());
			if ($fields['streak_day_xp'] < self::goldenXp((float) ($user['rating'] ?? 0)) || $fields['streak_day_reviews'] < $fields['streak_day_reviews_required'])
				return $fields;
		}
		$streak = $streak->countDay($mode, $today);
		return $fields + [
			'streak_length' => $streak->length,
			'streak_best' => $streak->best,
			'golden_streak_length' => $streak->goldenLength,
			'golden_streak_best' => $streak->goldenBest,
			'streak_freezes' => $streak->freezes,
			'streak_last_day' => $streak->lastDay,
		];
	}

	/** @param UserRow $user */
	public static function modeOf(array $user): int
	{
		return (int) ($user['streak_mode_setting_id'] ?? self::MODE_NORMAL) === self::MODE_GOLDEN ? self::MODE_GOLDEN : self::MODE_NORMAL;
	}

	//What $today did so far: the XP of its solves and the reviews solved, and the reviews the golden mode
	//asks of it, null until a golden solve worked it out. Counters of an earlier day are today's zero.
	/**
	 * @param UserRow $user
	 * @return array{xp: int, reviews: int, reviews_required: int|null}
	 */
	public static function progressOf(array $user, string $today): array
	{
		if (self::dayOf($user['streak_day'] ?? null) !== $today)
			return ['xp' => 0, 'reviews' => 0, 'reviews_required' => null];
		$required = $user['streak_day_reviews_required'] ?? null;
		return ['xp' => (int) ($user['streak_day_xp'] ?? 0), 'reviews' => (int) ($user['streak_day_reviews'] ?? 0), 'reviews_required' => $required === null ? null : (int) $required];
	}

	//What the page shows and the result of a solve returns (webroot/js/DailyStreak.js): both streaks,
	//the freezes, whether today is done, the mode, and what today did towards a golden day
	/**
	 * @param UserRow $user
	 * @return array{length: int, best: int, golden_length: int, golden_best: int, freezes: int, done_today: bool, golden: bool, xp: int, xp_required: int, reviews: int, reviews_required: int}
	 */
	public static function view(array $user, ?DateTimeImmutable $now = null): array
	{
		$now ??= new DateTimeImmutable();
		$streak = self::ofUser($user, $now);
		$progress = self::progressOf($user, self::today($user['time_zone'] ?? null, $now));
		return [
			'length' => $streak->length,
			'best' => $streak->best,
			'golden_length' => $streak->goldenLength,
			'golden_best' => $streak->goldenBest,
			'freezes' => $streak->freezes,
			'done_today' => $streak->doneToday,
			'golden' => self::modeOf($user) === self::MODE_GOLDEN,
			'xp' => $progress['xp'],
			'xp_required' => self::goldenXp((float) ($user['rating'] ?? 0)),
			'reviews' => $progress['reviews'],
			'reviews_required' => $progress['reviews_required'] ?? self::GOLDEN_REVIEWS,
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

	private static function addDays(string $day, int $days): string
	{
		return new DateTimeImmutable($day, new DateTimeZone('UTC'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
	}
}
