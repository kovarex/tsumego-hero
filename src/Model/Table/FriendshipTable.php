<?php

namespace App\Model\Table;

use App\Utility\Util;

//Friends. One player asks (user_id), the other (friend_user_id) accepts or declines; either can
//end it later. A pair has one row whichever side asked (the unique key `pair`), and a declined,
//withdrawn or ended friendship is deleted, so the row only ever is pending or accepted.
//Friends see what the other did lately on the homepage (FriendActivity), which is why it takes both.
class FriendshipTable extends AppTable
{
	//Where the friendship of the viewer with another player stands, as the friend button shows it
	public const NONE = 'none';
	public const ASKED_BY_VIEWER = 'asked-by-viewer';
	public const ASKED_BY_OTHER = 'asked-by-other';
	public const FRIENDS = 'friends';

	public static function stateBetween(int $viewerId, int $otherId): string
	{
		$rows = Util::query('SELECT user_id, friendship_status_id FROM friendship WHERE lower_user_id = ? AND higher_user_id = ?',
			[min($viewerId, $otherId), max($viewerId, $otherId)]);
		if (empty($rows))
			return self::NONE;
		if ((int) $rows[0]['friendship_status_id'] === FriendshipStatusTable::ACCEPTED)
			return self::FRIENDS;
		return (int) $rows[0]['user_id'] === $viewerId ? self::ASKED_BY_VIEWER : self::ASKED_BY_OTHER;
	}

	//The viewer asks the other player. When the other one asked the viewer already, this is the answer
	//to that, and they are friends. Asking again changes nothing.
	public static function ask(int $viewerId, int $otherId): void
	{
		if (self::stateBetween($viewerId, $otherId) === self::ASKED_BY_OTHER)
		{
			self::accept($viewerId, $otherId);
			return;
		}
		Util::execute('INSERT IGNORE INTO friendship (user_id, friend_user_id, friendship_status_id) VALUES (?, ?, ?)',
			[$viewerId, $otherId, FriendshipStatusTable::PENDING]);
	}

	//The viewer accepts what the other player asked; false when there was no such request
	public static function accept(int $viewerId, int $askerId): bool
	{
		return Util::execute('UPDATE friendship SET friendship_status_id = ?, accepted = NOW()
			WHERE user_id = ? AND friend_user_id = ? AND friendship_status_id = ?',
			[FriendshipStatusTable::ACCEPTED, $askerId, $viewerId, FriendshipStatusTable::PENDING]) > 0;
	}

	//Ends whatever is between the two: declines a request, takes one back, or ends a friendship
	public static function remove(int $viewerId, int $otherId): void
	{
		Util::execute('DELETE FROM friendship WHERE lower_user_id = ? AND higher_user_id = ?',
			[min($viewerId, $otherId), max($viewerId, $otherId)]);
	}

	//The ids of the friends of a player, as a subquery with the one parameter of the player twice.
	//Each side walks its own index (user_id_status, friend_user_id_status).
	/** @return array{string, list<mixed>} */
	public static function friendIdsQuery(int $userId): array
	{
		return ['SELECT friend_user_id AS id FROM friendship WHERE user_id = ? AND friendship_status_id = ?
			UNION ALL SELECT user_id FROM friendship WHERE friend_user_id = ? AND friendship_status_id = ?',
			[$userId, FriendshipStatusTable::ACCEPTED, $userId, FriendshipStatusTable::ACCEPTED]];
	}

	//The friends of a player and the requests on both sides, each a list of user rows in the
	//prefixed form of ClassroomTable::USER_COLUMNS, by name. Deleted accounts are left out.
	/** @return array{friends: list<array<string, mixed>>, requestsToMe: list<array<string, mixed>>, requestsFromMe: list<array<string, mixed>>} */
	public static function listsOf(int $userId): array
	{
		$rows = Util::query('SELECT f.friendship_status_id, f.user_id = ? AS asked_by_me, f.created, f.accepted, ' . ClassroomTable::USER_COLUMNS . '
			FROM friendship f
			JOIN user u ON u.id = IF(f.user_id = ?, f.friend_user_id, f.user_id) AND u.deleted IS NULL
			WHERE f.user_id = ? OR f.friend_user_id = ?
			ORDER BY u.display_name',
			[$userId, $userId, $userId, $userId]);
		$lists = ['friends' => [], 'requestsToMe' => [], 'requestsFromMe' => []];
		foreach ($rows as $row)
			if ((int) $row['friendship_status_id'] === FriendshipStatusTable::ACCEPTED)
				$lists['friends'][] = $row;
			else
				$lists[$row['asked_by_me'] ? 'requestsFromMe' : 'requestsToMe'][] = $row;
		return $lists;
	}

	//How many players ask the player to be their friend; the menu shows it (HeaderCounts)
	/** @return array{string, list<mixed>} */
	public static function countRequestsToQuery(int $userId): array
	{
		return ['SELECT COUNT(*) FROM friendship f JOIN user u ON u.id = f.user_id AND u.deleted IS NULL
			WHERE f.friend_user_id = ? AND f.friendship_status_id = ?',
			[$userId, FriendshipStatusTable::PENDING]];
	}
}
