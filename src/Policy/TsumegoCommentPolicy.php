<?php

namespace App\Policy;

/**
 * Comment deletion: the author or an admin may delete a comment.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type TsumegoCommentRow from \App\Utility\RowTypes
 */
class TsumegoCommentPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canAdd(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * @param UserRow|null $user
	 * @param TsumegoCommentRow $comment
	 */
	public static function canDelete(?array $user, array $comment): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $comment['user_id'] === $user['id'];
	}
}
