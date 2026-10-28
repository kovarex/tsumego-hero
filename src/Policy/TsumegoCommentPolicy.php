<?php

namespace App\Policy;

use App\Model\Entity\TsumegoComment;

//Comment deletion: the author or an admin may delete a comment.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class TsumegoCommentPolicy extends BasePolicy
{
	/** @param UserRow|null $user */
	public static function canAdd(?array $user): bool
	{
		return $user !== null;
	}

	/** @param UserRow|null $user */
	public static function canDelete(?array $user, TsumegoComment $comment): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return $comment->user_id === (int) $user['id'];
	}
}
