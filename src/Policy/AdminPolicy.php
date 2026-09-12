<?php

/**
 * Admin-only actions. "Only admin" is one method per action, like CakePHP 5.
 */
class AdminPolicy extends BasePolicy
{
	public static function canAdminstats(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUploads(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUserstats(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUserstats3(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAcceptSGFProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canRejectSGFProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAcceptTagConnectionProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canRejectTagConnectionProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canShowPublishSchedule(?array $user): bool
	{
		return static::isAdmin($user);
	}

	public static function canData(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
