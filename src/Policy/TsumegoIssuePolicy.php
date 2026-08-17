<?php

App::uses('BasePolicy', 'Policy');

/**
 * Issue lifecycle: authors close their own issues; reopening and moving
 * comments are admin-only.
 */
class TsumegoIssuePolicy extends BasePolicy
{
	public static function canClose($user, $issue): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $issue['user_id'] === $user['id'];
	}

	public static function canReopen($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canMoveComment($user): bool
	{
		return static::isAdmin($user);
	}
}
