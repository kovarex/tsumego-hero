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
	 * The tag overview: every tag with the problems that carry it.
	 *
	 * @param UserRow|null $user
	 */
	public static function canIndex(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canProblems(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Deleting a tag takes it off every problem, so it is reserved for owners, not
	 * regular admins. The admin activity log keeps what was deleted.
	 *
	 * @param UserRow|null $user
	 */
	public static function canDelete(?array $user): bool
	{
		return static::isOwner($user);
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
