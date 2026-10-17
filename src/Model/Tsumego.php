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
	 * The problems of the site and how many of them the player solved.
	 *
	 * Both numbers are counted over the same set of problems, so the second one can never
	 * pass the first: a solve of a problem that does not belong to a collection of the site
	 * (a sandbox build, an upload, a player collection) counts on neither side.
	 *
	 * @return array{total: int, solved: int}
	 */
	public static function publicProblemProgress(?int $userId = null): array
	{
		$row = Util::query(
			"SELECT "
			. "COUNT(DISTINCT tsumego.id) AS total, "
			. "COUNT(DISTINCT tsumego_status.tsumego_id) AS solved "
			. "FROM tsumego "
			. "LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego.id "
			. "AND tsumego_status.user_id = ? AND tsumego_status.status IN ('S', 'C', 'W') "
			. "WHERE EXISTS (SELECT 1 FROM set_connection sc "
			. "JOIN `set` ON `set`.id = sc.set_id AND `set`.public = 1 AND `set`.user_id IS NULL "
			. "WHERE sc.tsumego_id = tsumego.id) "
			. "AND tsumego.deleted IS NULL",
			[$userId]
		)[0];

		return ['total' => (int) $row['total'], 'solved' => (int) $row['solved']];
	}

	/**
	 * Count problems that belong to at least one public set and are not deleted.
	 */
	public static function countPublicProblems(): int
	{
		return self::publicProblemProgress()['total'];
	}
}
