<?php

namespace App\Utility;

use App\Model\Table\ChallengeTable;
use App\Model\Table\ClassroomMemberTable;
use App\Model\Table\FriendshipTable;
use App\Model\Table\MessageTable;

//The counts the menu of every page shows a signed in player: unread messages, the memberships of
//classes waiting for their answer, the challenges waiting for them to play and the players asking
//to be their friend. Each table owns its
//query; they are asked in one statement, the round trips are what a page pays most for them.
class HeaderCounts
{
	public function __construct(public readonly int $unreadMessages, public readonly int $classesAwaitingAnswer, public readonly int $challengesWaiting, public readonly int $friendRequests) {}

	public static function forUser(int $userId): self
	{
		$columns = [];
		$params = [];
		foreach ([
			'unread_messages' => MessageTable::unreadCountQuery($userId),
			'classes_awaiting_answer' => ClassroomMemberTable::countAwaitingAnswerQuery($userId),
			'challenges_waiting' => ChallengeTable::countWaitingForQuery($userId),
			'friend_requests' => FriendshipTable::countRequestsToQuery($userId)] as $name => [$sql, $queryParams])
		{
			$columns[] = '(' . $sql . ') AS ' . $name;
			$params = [...$params, ...$queryParams];
		}
		$row = Util::query('SELECT ' . implode(', ', $columns), $params)[0];
		return new self((int) $row['unread_messages'], (int) $row['classes_awaiting_answer'], (int) $row['challenges_waiting'], (int) $row['friend_requests']);
	}
}
