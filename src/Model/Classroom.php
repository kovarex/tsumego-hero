<?php

use App\Utility\Util;

//A class of a teacher: the players who agreed to be in it, and the problems the teacher assigns
//to them (ClassroomMember, ClassroomAssignment). Named classroom because class is a word of PHP.
class Classroom extends AppModel
{
	public const MAXIMUM_NAME_LENGTH = 100;

	public $useTable = 'classroom';

	//The columns of a user that User::renderLink() and User::avatarOrNull() read, for the queries
	//that list players of a class, on the alias `u`.
	public const USER_COLUMNS = 'u.id AS user_id, u.display_name AS user_display_name, u.rating AS user_rating,
		u.external_id AS user_external_id, u.picture AS user_picture, u.email AS user_email, u.avatar_setting_id AS user_avatar_setting_id, u.uploaded_avatar_hash AS user_uploaded_avatar_hash';

	//The name a class is saved with, null when nothing is left of it.
	public static function cleanName(string $name): ?string
	{
		$name = trim(preg_replace('/\s+/u', ' ', $name));
		if ($name === '')
			return null;
		return mb_substr($name, 0, self::MAXIMUM_NAME_LENGTH);
	}

	//The classes a player teaches, with how many players are in each and how many ask to join.
	/** @return array<array{id: int, name: string, pupil_count: int, request_count: int}> */
	public static function taughtBy(int $teacherId): array
	{
		$rows = Util::query('SELECT c.id, c.name,
				(SELECT COUNT(*) FROM classroom_member m JOIN user u ON u.id = m.user_id AND u.deleted IS NULL
					WHERE m.classroom_id = c.id AND m.classroom_member_status_id = ?) AS pupil_count,
				(SELECT COUNT(*) FROM classroom_member m JOIN user u ON u.id = m.user_id AND u.deleted IS NULL
					WHERE m.classroom_id = c.id AND m.classroom_member_status_id = ?) AS request_count
			FROM classroom c
			WHERE c.teacher_user_id = ?
			ORDER BY c.name, c.id',
			[ClassroomMemberStatus::ACCEPTED, ClassroomMemberStatus::REQUESTED, $teacherId]);
		return array_map(fn(array $row) => [
			'id' => (int) $row['id'],
			'name' => $row['name'],
			'pupil_count' => (int) $row['pupil_count'],
			'request_count' => (int) $row['request_count'],
		], $rows);
	}

	//The classes a player is in, is invited to or asked to join, by the state of the membership,
	//with the teacher of each.
	/**
	 * @return array<int, array<array<string, mixed>>> Rows of classroom_id, name, membership_id and the teacher's
	 *   USER_COLUMNS, by ClassroomMemberStatus id
	 */
	public static function ofPupil(int $userId): array
	{
		$rows = Util::query('SELECT c.id AS classroom_id, c.name, m.id AS membership_id, m.classroom_member_status_id, ' . self::USER_COLUMNS . '
			FROM classroom_member m
			JOIN classroom c ON c.id = m.classroom_id
			JOIN user u ON u.id = c.teacher_user_id AND u.deleted IS NULL
			WHERE m.user_id = ?
			ORDER BY c.name, c.id',
			[$userId]);
		$byStatus = [
			ClassroomMemberStatus::INVITED => [],
			ClassroomMemberStatus::REQUESTED => [],
			ClassroomMemberStatus::ACCEPTED => [],
		];
		foreach ($rows as $row)
			$byStatus[(int) $row['classroom_member_status_id']][] = $row;
		return $byStatus;
	}

	//The teacher of a class, as a user row.
	/**
	 * @param array<string, mixed> $classroom
	 * @return array<string, mixed>|null
	 */
	public static function teacher(array $classroom): ?array
	{
		$teacher = ClassRegistry::init('User')->findById($classroom['teacher_user_id']);
		return $teacher ? $teacher['User'] : null;
	}
}
