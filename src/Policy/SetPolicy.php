<?php

App::uses('BasePolicy', 'Policy');

/**
 * Set-level authorization. Sandbox access is allowed for admins and premium
 * users (premium can no longer be purchased, but existing premium users keep
 * the sandbox benefit).
 */
class SetPolicy extends BasePolicy
{
	public static function canSandbox($user): bool
	{
		return static::hasPermission($user, 'sandbox');
	}

	/**
	 * Viewing a set: private sets require login (sandbox is an admin/premium
	 * workspace; user-owned private sets are also login-only for now).
	 */
	public static function canView($user, $set): bool
	{
		if ($set['public'] != 0)
			return true;
		if ($user === null)
			return false;
		if ($set['user_id'] !== null)
			return static::isAdmin($user) || (int) $set['user_id'] === $user['id'];
		return true;
	}

	/**
	 * Solving a problem: sandbox sets require the sandbox permission.
	 */
	public static function canPlay($user, $set): bool
	{
		if (!($set['public'] == 0 && $set['user_id'] === null))
			return true;
		return static::hasPermission($user, 'sandbox');
	}

	/**
	 * Editing a set (add/remove/reorder tsumegos): admin or set owner.
	 */
	public static function canEdit($user, $set): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $set['user_id'] === $user['id'];
	}

	/**
	 * Deleting a set: owner can delete their own; admin can delete sandbox sets.
	 */
	public static function canDelete($user, $set): bool
	{
		if ($user === null)
			return false;
		$isOwner = (int) $set['user_id'] === $user['id'];
		if ($isOwner)
			return true;
		$isSandbox = $set['user_id'] === null && $set['public'] == 0;
		return static::isAdmin($user) && $isSandbox;
	}

	/**
	 * Creating and adding a tsumego to a set: admin only.
	 */
	public static function canCreateAndAddTsumego($user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Adding a tsumego to a set: admin or set owner.
	 */
	public static function canAddTsumego($user, $set): bool
	{
		return static::canEdit($user, $set);
	}

	/**
	 * Removing a tsumego from a set: admin or set owner.
	 */
	public static function canRemoveTsumego($user, $set): bool
	{
		return static::canEdit($user, $set);
	}

	/**
	 * Reordering tsumegos in a set: admin or set owner.
	 */
	public static function canReorderTsumego($user, $set): bool
	{
		return static::canEdit($user, $set);
	}
}
