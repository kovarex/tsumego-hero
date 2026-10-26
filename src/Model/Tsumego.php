<?php

use App\Utility\Util;

class Tsumego extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] = 'tsumego';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'title' => [
			'rule' => 'notBlank',
		],
		'sgf1' => [
			'rule' => 'notBlank',
		],
	];

	/**
	 * Moves the rating of the problem by the change a result brought, within its minimum and maximum.
	 * Only the rating is written, and the change is added to the rating stored now: the row a request
	 * read at its start may be older than an admin's edit or another player's result on the problem.
	 */
	public static function addToRating(int $tsumegoID, float $change): void
	{
		Util::execute('UPDATE tsumego
			SET rating = CASE
				WHEN rating + ? < minimum_rating THEN minimum_rating
				WHEN rating + ? > maximum_rating THEN maximum_rating
				ELSE rating + ?
			END
			WHERE id = ?', [$change, $change, $change, $tsumegoID]);
	}

	/**
	 * The problems the site holds: every problem that is not in the trash, whatever collection it
	 * sits in and whether or not that collection is published yet. A problem that belongs to three
	 * sets counts once. This is the number the solve counter of a player runs against on the
	 * profile, so a solve of a problem of a collection nobody published yet is inside the fraction.
	 */
	public static function problemCount(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total FROM tsumego WHERE deleted IS NULL')[0]['total'];
	}

	/**
	 * The problems of the site and how many of them the player solved.
	 *
	 * Both numbers are counted over the same set of problems, so the second one can never
	 * pass the first: a solve of a problem that does not belong to a collection of the site
	 * (a sandbox build, an upload, a player collection) counts on neither side.
	 *
	 * @return array{total: int, solved: int}
	 */
	public static function publicProblemProgress(int $userId): array
	{
		$inOfficialSet = fn(string $tsumegoRef): string
			=> 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id'
				. ' AND ' . Set::officialCondition() . ' WHERE sc.tsumego_id = ' . $tsumegoRef . ')';

		$row = Util::query(
			'SELECT '
			. '(SELECT COUNT(*) FROM tsumego t WHERE t.deleted IS NULL AND ' . $inOfficialSet('t.id') . ') AS total, '
			// The solved side only walks the statuses of that one player, which is what keeps this
			// from joining every problem of the site.
			. '(SELECT COUNT(DISTINCT ts.tsumego_id) FROM tsumego_status ts'
			. ' JOIN tsumego t ON t.id = ts.tsumego_id AND t.deleted IS NULL'
			. " WHERE ts.user_id = ? AND ts.status IN ('S', 'C', 'W')"
			. ' AND ' . $inOfficialSet('ts.tsumego_id') . ') AS solved',
			[$userId]
		)[0];

		return ['total' => (int) $row['total'], 'solved' => (int) $row['solved']];
	}
}
