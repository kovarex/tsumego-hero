<?php

App::uses('BasePolicy', 'Policy');

/**
 * Tag deletion is admin-only.
 */
class TagPolicy extends BasePolicy
{
	public static function canDelete($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canAcceptTagProposal($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canRejectTagProposal($user): bool
	{
		return static::isAdmin($user);
	}
}
