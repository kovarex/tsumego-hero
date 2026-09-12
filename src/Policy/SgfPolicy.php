<?php

/**
 * SGF viewing (the SGF admin review page) is admin-only.
 */
class SgfPolicy extends BasePolicy
{
	public static function canView(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
