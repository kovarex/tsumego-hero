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
}
