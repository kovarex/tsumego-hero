<?php

namespace App\Policy;

/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class SchedulePolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canIndex(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canAdd(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canPreview(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canCancel(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
