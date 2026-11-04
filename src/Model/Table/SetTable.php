<?php

namespace App\Model\Table;

use App\Model\Entity\Set;
use App\Utility\Rating;
use App\Utility\Util;
use Cake\Routing\Router;

//The collections of problems: the ones the site publishes, the sandbox builds of the admins and
//the collections the players assemble (their Favorites among them).
/** @phpstan-import-type SetRow from \App\Utility\RowTypes */
class SetTable extends AppTable
{
	//A collection the site publishes: public, owned by nobody and not in the trash. A collection
	//a player assembled and a sandbox build are not site content, so they do not count wherever
	//the site's own collections are meant.
	/** @param SetRow|Set $set A set row or entity */
	public static function isOfficial(array|Set $set): bool
	{
		return (int) $set['public'] === 1 && ($set['user_id'] ?? null) === null && empty($set['deleted']);
	}

	//The same rule as isOfficial() as an SQL condition on the given table alias, for hand-written
	//queries and Cake's find conditions alike. A query over the site's collections uses this, never
	//a bare public flag, which a shared player collection carries too.
	public static function officialCondition(string $alias = 'set'): string
	{
		return '`' . $alias . '`.public = 1 AND `' . $alias . '`.user_id IS NULL AND `' . $alias . '`.deleted IS NULL';
	}

	//The address of a collection. The pages of a long collection are numbered from one and its
	//first page is the collection itself, so a link to page one carries no number.
	//$id: Collection id, or the readable address of a rank or tag page
	//$partition: Page of the collection, counting from one
	//$full: Prefix the address with the site's own domain
	public static function url(int|string $id, int $partition = 1, bool $full = false): string
	{
		// The address is put together here, as the reverse routing tries the routes one by one and the
		// collections page links hundreds of cards. It is what the /sets/:id route gives (SetTest checks
		// that), an id with a slash doesn't fit that route and is left to the router.
		if (!str_contains((string) $id, '/'))
			return Router::url('/sets/' . $id . ($partition > 1 ? '/' . $partition : ''), $full);
		$url = ['controller' => 'Sets', 'action' => 'view', 'id' => $id];
		if ($partition > 1)
			$url['partition'] = $partition;
		return Router::url($url, $full);
	}

	public static function getProblemCount(int $setID): int
	{
		return (int) Util::query("SELECT COUNT(*) AS total FROM set_connection"
			. " JOIN tsumego ON tsumego.id = set_connection.tsumego_id AND tsumego.deleted IS NULL"
			. " WHERE set_connection.set_id = ?", [$setID])[0]["total"];
	}

	//How many collections of a player a viewer may open: the shared ones, and all of them for the
	//player whose collections they are. A player who has none is not offered a page that would be
	//empty.
	//$includePrivate: The viewer is the owner or an admin
	public static function countVisibleTo(int $userId, bool $includePrivate): int
	{
		$condition = $includePrivate ? '' : ' AND public = 1';
		$rows = Util::query("SELECT COUNT(*) AS total FROM `set` WHERE user_id = ?$condition", [$userId]);
		return (int) $rows[0]['total'];
	}

	//The cards of the collections matching $condition (on the alias s), in their order: each with its
	//problems (those not in the trash), their average difficulty and how much of them the player solved.
	//One query, the database counts and sums the problems of all the collections at once.
	//$userID: The player whose progress the cards show, null for none
	/** @return list<array{id: int, name: string, amount: int, color: string, difficulty: string, solved: int}> */
	public static function cards(string $condition, array $params, ?int $userID): array
	{
		$rows = Util::query("
SELECT
	s.id,
	s.title,
	s.color,
	COUNT(sc.tsumego_id) AS amount,
	COALESCE(SUM(t.rating), 0) AS rating_sum,
	COALESCE(SUM(CASE WHEN " . TsumegoStatusTable::solvedCondition('ts.status') . " THEN 1 ELSE 0 END), 0) AS solved
FROM `set` s
LEFT JOIN (set_connection sc
	JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL) ON sc.set_id = s.id
LEFT JOIN tsumego_status ts ON ts.tsumego_id = sc.tsumego_id AND ts.user_id = ?
WHERE $condition
GROUP BY s.id, s.title, s.color
ORDER BY s.order", [$userID, ...$params]);

		$cards = [];
		foreach ($rows as $row)
		{
			$amount = (int) $row['amount'];
			$cards[] = [
				'id' => (int) $row['id'],
				'name' => $row['title'],
				'amount' => $amount,
				'color' => $row['color'],
				'difficulty' => Rating::getReadableRankFromRating($amount > 0 ? (float) $row['rating_sum'] / $amount : 0.0),
				'solved' => $amount > 0 ? Util::getPercentButAvoid100UntilComplete((int) $row['solved'], $amount) : 0,
			];
		}
		return $cards;
	}
}
