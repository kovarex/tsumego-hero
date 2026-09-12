<?php

/**
 * Issue lifecycle: authors close their own issues; reopening and moving
 * comments are admin-only.
 */
class TsumegoIssuePolicy extends BasePolicy
{
	public static function canCreate(?array $user): bool
	{
		return $user !== null;
	}

	public static function canClose(?array $user, array $issue): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $issue['user_id'] === $user['id'];
	}

	public static function canReopen(?array $user, array $issue): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $issue['user_id'] === $user['id'];
	}

	public static function canMoveComment(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
