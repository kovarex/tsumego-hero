<?php

namespace App\Policy;

/**
 * Set-level authorization. Sandbox access is allowed for admins and premium
 * users (premium can no longer be purchased, but existing premium users keep
 * the sandbox benefit).
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type SetRow from \App\Utility\RowTypes
 */
class SetPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canSandbox(?array $user): bool
	{
		return static::hasSandbox($user);
	}

	/**
	 * Viewing a set: private sets require login (sandbox is an admin/premium
	 * workspace; user-owned private sets are also login-only for now).
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canView(?array $user, array $set): bool
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
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canPlay(?array $user, array $set): bool
	{
		if (!($set['public'] == 0 && $set['user_id'] === null))
			return true;
		return static::hasSandbox($user);
	}

	/**
	 * Editing a set (add/remove/reorder tsumegos): admin or set owner.
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canEdit(?array $user, array $set): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return (int) $set['user_id'] === $user['id'];
	}

	/**
	 * Deleting a set: owner can delete their own; admin can delete sandbox sets.
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canDelete(?array $user, array $set): bool
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
	 * Creating a set: any logged-in user.
	 *
	 * @param UserRow|null $user
	 */
	public static function canCreate(?array $user): bool
	{
		return $user !== null;
	}

	/**
	 * Creating a sandbox set: admin only.
	 *
	 * @param UserRow|null $user
	 */
	public static function canCreateSandbox(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Editing set-level settings (re-rate, alternative response, pass mode): admin only.
	 *
	 * @param UserRow|null $user
	 */
	public static function canEditSettings(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Creating and adding a tsumego to a set: admin only.
	 *
	 * @param UserRow|null $user
	 */
	public static function canCreateAndAddTsumego(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Adding a tsumego to a set: admin or set owner.
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canAddTsumego(?array $user, array $set): bool
	{
		return static::canEdit($user, $set);
	}

	/**
	 * Removing a tsumego from a set: admin or set owner.
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canRemoveTsumego(?array $user, array $set): bool
	{
		return static::canEdit($user, $set);
	}

	/**
	 * Reordering tsumegos in a set: admin or set owner.
	 *
	 * @param UserRow|null $user
	 * @param SetRow $set
	 */
	public static function canReorderTsumego(?array $user, array $set): bool
	{
		return static::canEdit($user, $set);
	}
}
