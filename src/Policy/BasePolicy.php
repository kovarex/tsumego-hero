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
	 * Whether the identity holds the given permission (false for anonymous).
	 */
	protected static function hasPermission($user, string $permission): bool
	{
		return $user !== null && in_array($permission, $user['permissions'], true);
	}

	/**
	 * Whether the identity is an admin (false for anonymous).
	 */
	protected static function isAdmin($user): bool
	{
		return static::hasPermission($user, 'admin');
	}

	/**
	 * Whether the identity may propose SGF edits or tag connections.
	 * Matches CakePHP 5 pattern: policy methods own the business logic,
	 * permission strings are not exposed to callers.
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
