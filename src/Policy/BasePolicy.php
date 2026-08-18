<?php

/**
 * Shared helpers for policies. Policies are stateless decision objects;
 * all state comes from the identity ($user) and the resource.
 *
 * Mirrors CakePHP 5's policy shape: methods receive ($user, $resource = null),
 * $user is null for anonymous users.
 */
abstract class BasePolicy
{
	/**
	 * Whether the identity is an admin (false for anonymous).
	 */
	protected static function isAdmin($user): bool
	{
		return $user !== null && (bool) $user['isAdmin'];
	}

	/**
	 * Whether the identity has sandbox access: admin or premium.
	 */
	protected static function hasSandbox($user): bool
	{
		if ($user === null)
			return false;
		return (bool) $user['isAdmin'] || (bool) $user['premium'];
	}

	/**
	 * Whether the identity may propose SGF edits or tag connections.
	 * Cross-cutting: used by both SgfPolicy and TagConnectionPolicy.
	 */
	public static function canPropose($user): bool
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
