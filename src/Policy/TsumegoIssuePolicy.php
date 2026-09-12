<?php

/**
 * Issue lifecycle: authors close their own issues; reopening and moving
 * comments are admin-only.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type TsumegoIssueRow from \App\Utility\RowTypes
 */
class TsumegoIssuePolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canCreate(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * @param UserRow|null $user
	 * @param TsumegoIssueRow $issue
	 */
	public static function canClose(?array $user, array $issue): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $issue['user_id'] === $user['id'];
	}

	/**
	 * @param UserRow|null $user
	 * @param TsumegoIssueRow $issue
	 */
	public static function canReopen(?array $user, array $issue): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $issue['user_id'] === $user['id'];
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canMoveComment(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
