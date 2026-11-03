<?php

namespace App\Utility;

use App\Model\Table\ChallengeTable;
use App\Model\Table\ClassroomMemberTable;
use App\Model\Table\MessageTable;

//The counts the menu of every page shows a signed in player: unread messages, the memberships of
//classes waiting for their answer and the challenges waiting for them to play. Each table owns its
//query; they are asked in one statement, the round trips are what a page pays most for them.
class HeaderCounts
{
	public function __construct(public readonly int $unreadMessages, public readonly int $classesAwaitingAnswer, public readonly int $challengesWaiting) {}

	public static function forUser(int $userId): self
	{
		$columns = [];
		$params = [];
		foreach ([
			'unread_messages' => MessageTable::unreadCountQuery($userId),
			'classes_awaiting_answer' => ClassroomMemberTable::countAwaitingAnswerQuery($userId),
			'challenges_waiting' => ChallengeTable::countWaitingForQuery($userId)] as $name => [$sql, $queryParams])
		{
			$columns[] = '(' . $sql . ') AS ' . $name;
			$params = [...$params, ...$queryParams];
		}
		$row = Util::query('SELECT ' . implode(', ', $columns), $params)[0];
		return new self((int) $row['unread_messages'], (int) $row['classes_awaiting_answer'], (int) $row['challenges_waiting']);
	}
}
