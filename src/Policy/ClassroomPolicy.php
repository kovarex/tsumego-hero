<?php

namespace App\Policy;

/**
 * Classes of a teacher. Any signed-in player can start a class and so be a teacher; the class
 * belongs to them alone. The page of a class is open to anyone, since its link is how a
 * player finds the class to ask to join; what it shows depends on the membership, which the
 * controller loads anyway to show the player where they stand.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class ClassroomPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canCreate(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * Inviting, accepting and removing players, assigning problems, seeing the progress of every
	 * pupil and deleting the class: the teacher.
	 *
	 * @param UserRow|null $user
	 * @param array<string, mixed> $classroom
	 */
	public static function canManage(?array $user, array $classroom): bool
	{
		return $user !== null && (int) $classroom['teacher_id'] === (int) $user['id'];
	}
}
