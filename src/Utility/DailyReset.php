<?php

namespace App\Utility;

use App\Model\Table\AchievementConditionTable;

//What a player gets back every day: the hearts (damage) and the hero powers of the day (the used_ flags), and
//the count of potions drunk that day (achievement condition CATEGORY_POTION). They come back when the player's
//own day turns, at DailyStreak::DAY_START_HOUR in their time zone like the daily streak and the mistake
//training, not at midnight of the server. The row keeps the player's day of the last reset (user.daily_reset_day).
//The hourly cron (`bin/cake cron_hourly`, resetDuePlayers()) resets every player whose day turned, so every reader
//of the row sees the day's values; the first request of the player's new day does it too (apply()), so the one who
//plays gets them at 03:00 sharp rather than at the next run. Both only reset a row whose day is behind, so a day is
//reset once whichever comes first.
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

	//Reset every player whose day turned since their last reset, a time zone at a time: the day of a zone is
	//worked out here, and one UPDATE takes all its players that are behind it, the potions of those players first,
	//as the condition reads the day before it moves. The index (time_zone, daily_reset_day) lists the zones with a
	//loose scan and gives each UPDATE a range of the players behind, so a run reads the due rows only and locks no
	//other player's row. Returns how many players were reset.
	public static function resetDuePlayers(): int
	{
		$now = Clock::now();
		$assignments = implode(', ', array_map(fn(string $column) => 'u.' . $column . ' = ?', array_keys(self::COLUMNS)));
		$reset = 0;
		foreach (Util::query('SELECT DISTINCT time_zone FROM user') as $row)
		{
			$timeZone = $row['time_zone'];
			$today = DailyStreak::today($timeZone, $now);
			$behind = 'u.time_zone <=> ? AND (u.daily_reset_day IS NULL OR u.daily_reset_day < ?)';
			Util::database()->transactional(function () use ($timeZone, $today, $behind, $assignments, &$reset) {
				Util::execute(
					'UPDATE achievement_condition c JOIN user u ON u.id = c.user_id SET c.value = 0'
					. ' WHERE c.achievement_condition_category_id = ? AND c.value <> 0 AND ' . $behind,
					[AchievementConditionTable::CATEGORY_POTION, $timeZone, $today]);
				$reset += Util::execute(
					'UPDATE user u SET ' . $assignments . ', u.daily_reset_day = ? WHERE ' . $behind,
					[...array_values(self::COLUMNS), $today, $timeZone, $today]);
			});
		}
		return $reset;
	}

	/** @param UserRow $user */
	private static function today(array $user): string
	{
		return DailyStreak::today($user['time_zone'] ?? null, Clock::now());
	}
}
