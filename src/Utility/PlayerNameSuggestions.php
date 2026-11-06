<?php

namespace App\Utility;

use App\Model\Table\FriendshipTable;

//The players a name being typed can mean, for the fields that take names of players (the invitations
//of a challenge). Friends come first: they match anywhere in their name, and there are few of them.
//The other players match from the start of their name, which the unique index on display_name
//answers without reading the whole user table. Only what the list shows goes out: the name, the
//rank and whether the player is a friend. Deleted accounts and the asking player are left out.
class PlayerNameSuggestions
{
	public const int MINIMUM_LENGTH = 2;
	public const int LIMIT = 8;

	/** @return list<array{name: string, rank: string, friend: bool}> */
	public static function for(int $viewerId, string $typed): array
	{
		$typed = trim($typed);
		if (mb_strlen($typed) < self::MINIMUM_LENGTH)
			return [];
		$escaped = addcslashes($typed, '\\%_');
		[$friendIdsSql, $friendIdsParams] = FriendshipTable::friendIdsQuery($viewerId);
		$limit = self::LIMIT;
		$rows = Util::query("(SELECT u.display_name, u.rating, 1 AS friend
				FROM ($friendIdsSql) f
				JOIN user u ON u.id = f.id AND u.deleted IS NULL
				WHERE u.display_name LIKE ?
				ORDER BY u.display_name LIMIT $limit)
			UNION ALL
			(SELECT u.display_name, u.rating, 0
				FROM user u
				WHERE u.display_name LIKE ? AND u.deleted IS NULL AND u.id <> ?
					AND u.id NOT IN ($friendIdsSql)
				ORDER BY u.display_name LIMIT $limit)
			ORDER BY friend DESC, display_name LIKE ? DESC, display_name
			LIMIT $limit",
			[...$friendIdsParams, '%' . $escaped . '%', $escaped . '%', $viewerId, ...$friendIdsParams, $escaped . '%']);
		return array_map(fn(array $row) => [
			'name' => (string) $row['display_name'],
			'rank' => Rating::getReadableRankFromRating((float) $row['rating']),
			'friend' => (bool) $row['friend']], $rows);
	}
}
