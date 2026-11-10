<?php

namespace App\Utility;

use App\Model\Table\AchievementConditionTable;

//What a player gets back every day: the hearts (damage) and the hero powers of the day (the used_ flags), and
//the count of potions drunk that day (achievement condition CATEGORY_POTION). They come back when the player's
//own day turns, at DailyStreak::DAY_START_HOUR in their time zone like the daily streak and the mistake
//training, not at midnight of the server. There is no cron for it: the row keeps the player's day of the last
//reset (user.daily_reset_day), and the first time the player is signed in on a later day (Auth::init(), on every
//request) the reset is done, however long they were away.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class DailyReset
{
	//The columns of the user that a new day sets back, and to what
	public const array COLUMNS = [
		'damage' => 0,
		'used_refinement' => 0,
		'used_sprint' => 0,
		'used_rejuvenation' => 0,
		'used_potion' => 0,
		'used_intuition' => 0,
		'used_revelation' => 0,
	];

	//Whether the player's day turned since their last reset
	/** @param UserRow $user */
	public static function isDue(array $user): bool
	{
		$last = DailyStreak::dayOf($user['daily_reset_day'] ?? null);
		return $last === null || $last < self::today($user);
	}

	//The row of the signed-in player with the reset of a new day done, when their day turned since the last one.
	//The UPDATE does it once whatever the requests running at the same moment: only the one that moves
	//daily_reset_day resets, so the hearts lost in another request after it aren't given back.
	/**
	 * @param UserRow $user
	 * @return UserRow
	 */
	public static function apply(array $user): array
	{
		// the row of the request knows its day, so all the other requests of the day cost no query
		if (!self::isDue($user))
			return $user;
		$today = self::today($user);
		$assignments = implode(', ', array_map(fn(string $column) => $column . ' = ?', array_keys(self::COLUMNS)));
		$reset = Util::execute(
			'UPDATE user SET ' . $assignments . ', daily_reset_day = ? WHERE id = ? AND (daily_reset_day IS NULL OR daily_reset_day < ?)',
			[...array_values(self::COLUMNS), $today, $user['id'], $today]) > 0;
		if (!$reset)
		{
			// another request of the same moment did it, so the row is read again for what it set
			$row = Util::query('SELECT ' . implode(', ', array_keys(self::COLUMNS)) . ', daily_reset_day FROM user WHERE id = ?', [$user['id']])[0] ?? [];
			return $row + $user;
		}
		Util::execute(
			'UPDATE achievement_condition SET value = 0 WHERE user_id = ? AND achievement_condition_category_id = ? AND value <> 0',
			[$user['id'], AchievementConditionTable::CATEGORY_POTION]);
		return ['daily_reset_day' => $today] + self::COLUMNS + $user;
	}

	//The hearts a player lost today, for showing another player's row (the profile): a row that waits for its
	//reset has lost none yet.
	/** @param UserRow $user */
	public static function damageOf(array $user): int
	{
		return self::isDue($user) ? 0 : (int) ($user['damage'] ?? 0);
	}

	/** @param UserRow $user */
	private static function today(array $user): string
	{
		return DailyStreak::today($user['time_zone'] ?? null, Clock::now());
	}
}
