<?php

App::uses('BasePolicy', 'Policy');

/**
 * Tsumego-level authorization. All tsumego management actions are admin-only.
 */
class TsumegoPolicy extends BasePolicy
{
	public static function canEdit($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canEditSettings($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canMergeForm($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canMergeFinalForm($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canSetupSgf($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canSetupSgfStep2($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canPerformMerge($user): bool
	{
		return static::isAdmin($user);
	}
}
