<?php

namespace App\Policy;

/**
 * Admin-only actions. "Only admin" is one method per action, like CakePHP 5.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class AdminPolicy extends BasePolicy
{
	/**
	 * The admin page and the pages of the proposals and of the admin activity, see AdminController
	 *
	 * @param UserRow|null $user
	 */
	public static function canViewAdminArea(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * The tick of an SGF change on the SGF changes page, see AdminController::reviewSgfChange
	 *
	 * @param UserRow|null $user
	 */
	public static function canReviewSgfChange(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canUserstats(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canUserstats3(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canAcceptSGFProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRejectSGFProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canReopenSGFProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canAcceptTagConnectionProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 */
	public static function canRejectTagConnectionProposal(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Asking the author of a set for a solution to one of its problems
	 *
	 * @param UserRow|null $user
	 */
	public static function canRequestSolution(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Restoring a deleted account
	 *
	 * @param UserRow|null $user
	 */
	public static function canRestoreAccount(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Seeing the problems and collections in the trash
	 *
	 * @param UserRow|null $user
	 */
	public static function canViewTrash(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * Taking a problem or a collection back from the trash
	 *
	 * @param UserRow|null $user
	 */
	public static function canRestoreFromTrash(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
