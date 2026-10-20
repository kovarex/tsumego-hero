<?php

use App\Utility\Util;

class TsumegoStatus extends AppModel
{
	public static string $NOT_VISITED = 'N';
	public static string $VISITED = 'V';
	public static string $SOLVED = 'S';
	public static string $MASTERED = 'C';
	public static string $REVIEW = 'W';
	public static string $LOCKED = 'F';
	public static string $FORGOTTEN = 'X';
	public static string $GOLDEN = 'G';

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'tsumego_status';
		parent::__construct($id, $table, $ds);
	}

	// when two statuses are to be merged, we need to decide which one is more valuable to keep for the user
	public static function less(string $status1, string $status2): bool
	{
		return self::value($status1) < self::value($status2);
	}

	private static function value(string $status): int
	{
		switch ($status)
		{
			case self::$LOCKED: return 0; // failed
			case self::$NOT_VISITED: return 1; // nothing
			case self::$VISITED: return 2; // visited
			case self::$FORGOTTEN: return 3; // once solve but then failed
			case self::$SOLVED: return 4; // once solved
			case self::$REVIEW: return 5; // half XP after once solved
			case self::$MASTERED: return 6; // double solved
			case self::$GOLDEN: return 7; // golden tsumego
			default: throw new Exception("Unknown status: $status");
		}
	}

	public static array $labels = [
		'N' => 'Not visited',
		'V' => 'Visited',
		'S' => 'Solved',
		'F' => 'Locked',
		'W' => 'Review',
		'C' => 'Mastered',
		'X' => 'Forgotten',
		'G' => 'Golden',
	];

	public static array $descriptions = [
		'N' => 'You haven\'t seen this problem yet.',
		'V' => 'You have seen this problem but have not solved it.',
		'S' => 'You got it right. It gives no more XP and becomes available for Review after one week.',
		'F' => 'You can\'t play this problem today after misplaying with no hearts left. It resets to Visited tomorrow.',
		'W' => 'A week has passed since you solved this. Solve it for half XP. Get it right and it becomes Mastered, fail and it becomes Forgotten.',
		'C' => 'You passed the review. It gives no more XP.',
		'X' => 'You failed a Review. You are locked out for today. It resets to Review tomorrow.',
		'G' => 'You have one attempt at 8x XP. Created by activating the Refinement hero power.',
	];

	public static function label(string $status): string
	{
		return self::$labels[$status] ?? '';
	}

	public static function description(string $status): string
	{
		return self::$descriptions[$status] ?? '';
	}

	/**
	 * How long untouched progress is kept. A problem nobody has touched for this long
	 * counts as old progress, which the settings page offers to throw away.
	 */
	private static int $OLD_PROGRESS_YEARS = 1;

	/**
	 * The old progress that is worth something: what the settings page counts and
	 * offers to reset.
	 */
	public static function countOldProgress(int $userID): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total FROM tsumego_status
			WHERE user_id = ? AND status IN (?, ?, ?) AND updated <= NOW() - INTERVAL ? YEAR;',
			[$userID, self::$SOLVED, self::$MASTERED, self::$REVIEW, self::$OLD_PROGRESS_YEARS])[0]['total'];
	}

	/**
	 * Throws away the progress nobody touched for OLD_PROGRESS_YEARS and keeps the solved
	 * count of the account in step. A golden problem waits for its one attempt, which is
	 * not progress that was left behind, so it stays.
	 */
	public static function deleteOldProgress(int $userID): int
	{
		$deleted = Util::execute('DELETE FROM tsumego_status
			WHERE user_id = ? AND updated <= NOW() - INTERVAL ? YEAR AND status <> ?;',
			[$userID, self::$OLD_PROGRESS_YEARS, self::$GOLDEN]);

		Util::execute('UPDATE user
			LEFT JOIN (
				SELECT user_id, COUNT(*) AS cnt
				FROM tsumego_status
				WHERE status IN (?, ?, ?, ?) AND user_id = ?
				GROUP BY user_id
			) ts ON ts.user_id = user.id
			SET user.solved = COALESCE(ts.cnt, 0)
			WHERE user.id = ?;',
			[self::$SOLVED, self::$REVIEW, self::$MASTERED, self::$FORGOTTEN, $userID, $userID]);

		return $deleted;
	}
}
