<?php

/**
 * Tag connections: proposing a tag requires the canPropose capability;
 * removing one is allowed for admins or the proposer (unapproved proposals only).
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type TagConnectionRow from \App\Utility\RowTypes
 */
class TagConnectionPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canAdd(?array $user): bool
	{
		return static::canPropose($user);
	}

	/**
	 * Removing a proposal: admins or the proposer (unapproved only).
	 *
	 * @param UserRow|null $user
	 * @param TagConnectionRow $tagConnection
	 */
	public static function canRemove(?array $user, array $tagConnection): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $tagConnection['user_id'] === $user['id']
			&& !$tagConnection['approved'];
	}
}
