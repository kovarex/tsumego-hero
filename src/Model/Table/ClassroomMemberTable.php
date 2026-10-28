<?php

namespace App\Model\Table;

use App\Model\Entity\ClassroomMember;
use App\Utility\Util;
use Cake\ORM\TableRegistry;

//A player in a class, or on the way into it: invited by the teacher or asking to join, until the
//other side accepts (ClassroomMemberStatusTable). Declining, cancelling, leaving and removing all
//delete the row, so the player can be invited or ask again later.
class ClassroomMemberTable extends AppTable
{
	//The membership of a player in a class, in whatever state it is.
	public static function of(int $classroomId, int $userId): ?ClassroomMember
	{
		/** @var ClassroomMember|null $membership */
		$membership = TableRegistry::getTableLocator()->get('ClassroomMember')->find()
			->where(['classroom_id' => $classroomId, 'user_id' => $userId])
			->first();
		return $membership;
	}

	public static function isPupil(int $classroomId, int $userId): bool
	{
		return self::of($classroomId, $userId)?->classroom_member_status_id === ClassroomMemberStatusTable::ACCEPTED;
	}

	//The players of a class and the ones on the way into it, by the state of their membership,
	//ordered by name. A deleted account is left out.
	/** @return array<int, array<array<string, mixed>>> Rows of membership_id and USER_COLUMNS, by ClassroomMemberStatusTable id */
	public static function ofClassroom(int $classroomId): array
	{
		$rows = Util::query('SELECT m.id AS membership_id, m.classroom_member_status_id, ' . ClassroomTable::USER_COLUMNS . '
			FROM classroom_member m
			JOIN user u ON u.id = m.user_id AND u.deleted IS NULL
			WHERE m.classroom_id = ?
			ORDER BY u.display_name',
			[$classroomId]);
		$byStatus = [
			ClassroomMemberStatusTable::INVITED => [],
			ClassroomMemberStatusTable::REQUESTED => [],
			ClassroomMemberStatusTable::ACCEPTED => [],
		];
		foreach ($rows as $row)
			$byStatus[(int) $row['classroom_member_status_id']][] = $row;
		return $byStatus;
	}

	//Starts a membership from one side, or completes it when the other side already started it:
	//an invitation to a player who asked to join, or a request to join from an invited player,
	//means both sides agree.
	//$statusId: ClassroomMemberStatusTable::INVITED for the teacher, REQUESTED for the player
	//Returns the status the membership has now
	public static function offer(int $classroomId, int $userId, int $statusId): int
	{
		$membership = self::of($classroomId, $userId);
		if ($membership === null)
		{
			$classroomMemberTable = TableRegistry::getTableLocator()->get('ClassroomMember');
			$classroomMemberTable->saveOrFail($classroomMemberTable->newEntity([
				'classroom_id' => $classroomId,
				'user_id' => $userId,
				'classroom_member_status_id' => $statusId]));
			return $statusId;
		}

		if ($membership->classroom_member_status_id === $statusId || $membership->classroom_member_status_id === ClassroomMemberStatusTable::ACCEPTED)
			return $membership->classroom_member_status_id;

		self::accept($membership->id);
		return ClassroomMemberStatusTable::ACCEPTED;
	}

	public static function accept(int $membershipId): void
	{
		TableRegistry::getTableLocator()->get('ClassroomMember')->updateAll(
			['classroom_member_status_id' => ClassroomMemberStatusTable::ACCEPTED],
			['id' => $membershipId]);
	}

	public static function remove(int $membershipId): void
	{
		TableRegistry::getTableLocator()->get('ClassroomMember')->deleteAll(['id' => $membershipId]);
	}

	//How many memberships wait for the answer of a player: invitations to their classes for
	//them, and players asking to join the classes they teach. The menu shows it.
	public static function countAwaitingAnswer(int $userId): int
	{
		$rows = Util::query('SELECT
				(SELECT COUNT(*) FROM classroom_member m
					JOIN classroom c ON c.id = m.classroom_id
					JOIN user t ON t.id = c.teacher_user_id AND t.deleted IS NULL
					WHERE m.user_id = ? AND m.classroom_member_status_id = ?)
				+ (SELECT COUNT(*) FROM classroom c
					JOIN classroom_member m ON m.classroom_id = c.id AND m.classroom_member_status_id = ?
					JOIN user u ON u.id = m.user_id AND u.deleted IS NULL
					WHERE c.teacher_user_id = ?) AS total',
			[$userId, ClassroomMemberStatusTable::INVITED, ClassroomMemberStatusTable::REQUESTED, $userId]);
		return (int) $rows[0]['total'];
	}
}
