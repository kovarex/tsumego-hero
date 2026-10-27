<?php

namespace App\Policy;

use App\Utility\Constants;
use User;

//Shared helpers for policies. Policies are stateless decision objects; they
//receive the identity ($user) and the resource, and when those two are not
//enough to decide (such as "did this player solve the related problem"), they
//read the missing fact through the model layer instead of making the caller
//prepare it.
//Mirrors CakePHP 5's policy shape: methods receive ($user, $resource = null), $user is null for anonymous users.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
abstract class BasePolicy
{
	//Whether the identity is an admin (false for anonymous).
	/** @param UserRow|null $user */
	protected static function isAdmin(?array $user): bool
	{
		return User::isAdmin($user);
	}

	//Whether the identity is an owner (false for anonymous).
	/** @param UserRow|null $user */
	protected static function isOwner(?array $user): bool
	{
		return User::isOwner($user);
	}

	//Whether the identity has sandbox access: admin or premium.
	/** @param UserRow|null $user */
	protected static function hasSandbox(?array $user): bool
	{
		if ($user === null)
			return false;
		return static::isAdmin($user) || $user['premium_id'] !== null;
	}

	//Whether the identity may propose SGF edits or tag connections.
	//Cross-cutting: used by both SgfPolicy and TagConnectionPolicy.
	/** @param UserRow|null $user */
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
