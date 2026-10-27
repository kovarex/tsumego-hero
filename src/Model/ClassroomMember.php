<?php

use App\Utility\Util;

//A player in a class, or on the way into it: invited by the teacher or asking to join, until the
//other side accepts (ClassroomMemberStatus). Declining, cancelling, leaving and removing all
//delete the row, so the player can be invited or ask again later.
class ClassroomMember extends AppModel
{
	public $useTable = 'classroom_member';

	//The membership of a player in a class, in whatever state it is.
	/** @return array<string, mixed>|null */
	public static function of(int $classroomId, int $userId): ?array
	{
		$rows = Util::query('SELECT * FROM classroom_member WHERE classroom_id = ? AND user_id = ?', [$classroomId, $userId]);
		return $rows[0] ?? null;
	}

	public static function isPupil(int $classroomId, int $userId): bool
	{
		$membership = self::of($classroomId, $userId);
		return $membership !== null && (int) $membership['classroom_member_status_id'] === ClassroomMemberStatus::ACCEPTED;
	}

	//The players of a class and the ones on the way into it, by the state of their membership,
	//ordered by name. A deleted account is left out.
	/** @return array<int, array<array<string, mixed>>> Rows of membership_id and USER_COLUMNS, by ClassroomMemberStatus id */
	public static function ofClassroom(int $classroomId): array
	{
		$rows = Util::query('SELECT m.id AS membership_id, m.classroom_member_status_id, ' . Classroom::USER_COLUMNS . '
			FROM classroom_member m
			JOIN user u ON u.id = m.user_id AND u.deleted IS NULL
			WHERE m.classroom_id = ?
			ORDER BY u.display_name',
			[$classroomId]);
		$byStatus = [
			ClassroomMemberStatus::INVITED => [],
			ClassroomMemberStatus::REQUESTED => [],
			ClassroomMemberStatus::ACCEPTED => [],
		];
		foreach ($rows as $row)
			$byStatus[(int) $row['classroom_member_status_id']][] = $row;
		return $byStatus;
	}

	//Starts a membership from one side, or completes it when the other side already started it:
	//an invitation to a player who asked to join, or a request to join from an invited player,
	//means both sides agree.
	//$statusId: ClassroomMemberStatus::INVITED for the teacher, REQUESTED for the player
	//Returns the status the membership has now
	public static function offer(int $classroomId, int $userId, int $statusId): int
	{
		$membership = self::of($classroomId, $userId);
		if ($membership === null)
		{
			Util::execute('INSERT INTO classroom_member (classroom_id, user_id, classroom_member_status_id) VALUES (?, ?, ?)',
				[$classroomId, $userId, $statusId]);
			return $statusId;
		}

		$currentStatusId = (int) $membership['classroom_member_status_id'];
		if ($currentStatusId === $statusId || $currentStatusId === ClassroomMemberStatus::ACCEPTED)
			return $currentStatusId;

		self::accept((int) $membership['id']);
		return ClassroomMemberStatus::ACCEPTED;
	}

	public static function accept(int $membershipId): void
	{
		Util::execute('UPDATE classroom_member SET classroom_member_status_id = ? WHERE id = ?',
			[ClassroomMemberStatus::ACCEPTED, $membershipId]);
	}

	public static function remove(int $membershipId): void
	{
		Util::execute('DELETE FROM classroom_member WHERE id = ?', [$membershipId]);
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
			[$userId, ClassroomMemberStatus::INVITED, ClassroomMemberStatus::REQUESTED, $userId]);
		return (int) $rows[0]['total'];
	}
}
