<?php

namespace App\Policy;

use App\Utility\Constants;

/**
 * Shared helpers for policies. Policies are stateless decision objects;
 * all state comes from the identity ($user) and the resource.
 *
 * Mirrors CakePHP 5's policy shape: methods receive ($user, $resource = null),
 * $user is null for anonymous users.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
abstract class BasePolicy
{
	/**
	 * Whether the identity is an admin (false for anonymous).
	 *
	 * @param UserRow|null $user
	 */
	protected static function isAdmin(?array $user): bool
	{
		return $user !== null && (bool) $user['isAdmin'];
	}

	/**
	 * Whether the identity has sandbox access: admin or premium.
	 *
	 * @param UserRow|null $user
	 */
	protected static function hasSandbox(?array $user): bool
	{
		if ($user === null)
			return false;
		return (bool) $user['isAdmin'] || (bool) $user['premium'];
	}

	/**
	 * Whether the identity may propose SGF edits or tag connections.
	 * Cross-cutting: used by both SgfPolicy and TagConnectionPolicy.
	 *
	 * @param UserRow|null $user
	 */
	public static function canPropose(?array $user): bool
	{
		if ($user === null)
			return false;
		if (static::isAdmin($user))
			return true;
		if ((int) $user['level'] >= 40)
			return true;
		if ((float) $user['rating'] >= Constants::$MINIMUM_RATING_TO_CONTRIBUTE)
			return true;
		return false;
	}
}
