<?php

App::uses('BasePolicy', 'Policy');

/**
 * Admin-only actions. "Only admin" is one method per action, like CakePHP 5.
 */
class AdminPolicy extends BasePolicy
{
	public static function canAdminstats($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUploads($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUserstats($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canUserstats3($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAcceptSGFProposal($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canRejectSGFProposal($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAcceptTagConnectionProposal($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canRejectTagConnectionProposal($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canData($user): bool
	{
		return static::isAdmin($user);
	}
}
