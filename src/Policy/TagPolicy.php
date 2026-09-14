<?php

namespace App\Policy;

/**
 * Tag management is admin-only; creating a new tag name requires the
 * contribution capability (same as proposing a tag connection).
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class TagPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canAdd(?array $user): bool
	{
		return static::canPropose($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canEdit(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canEditAction(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Deleting a tag name is reserved for (user id 72 = d4rkm4tter),
	 * not for regular admins.
	 *
	 * TODO: replace the hardcoded user id 72 with a dedicated role above admin
	 * (e.g. "superadmin" / "site owner")
	 *
	 * @param UserRow|null $user
	 */
	public static function canDelete(?array $user): bool
	{
		return static::isAdmin($user) && (int) $user['id'] === 72;
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canAcceptTagProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRejectTagProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
