<?php

namespace App\Policy;

/**
 * Tsumego-level authorization. All tsumego management actions are admin-only.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class TsumegoPolicy extends BasePolicy
{
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
	public static function canEditSettings(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canMergeForm(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canMergeFinalForm(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canSetupSgf(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canSetupSgfStep2(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canPerformMerge(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
