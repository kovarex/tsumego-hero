<?php

App::uses('BasePolicy', 'Policy');

/**
 * Comment deletion: the author or an admin may delete a comment.
 */
class TsumegoCommentPolicy extends BasePolicy
{
	public static function canAdd($user): bool
	{
		return $user !== null;
	}

	public static function canDelete($user, $comment): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $comment['user_id'] === $user['id'];
	}
}
