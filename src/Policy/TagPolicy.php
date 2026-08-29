<?php

App::uses('BasePolicy', 'Policy');

/**
 * Tag management is admin-only; creating a new tag name requires the
 * contribution capability (same as proposing a tag connection).
 */
class TagPolicy extends BasePolicy
{
	public static function canAdd($user): bool
	{
		return static::canPropose($user);
	}

	public static function canEdit($user): bool
	{
		return static::isAdmin($user);
	}

	public static function canEditAction($user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Deleting a tag name is reserved for (user id 72 = d4rkm4tter),
	 * not for regular admins.
	 *
	 * TODO: replace the hardcoded user id 72 with a dedicated role above admin
	 * (e.g. "superadmin" / "site owner")
	 */
	public static function canDelete($user): bool
	{
		return static::isAdmin($user) && (int) $user['id'] === 72;
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
