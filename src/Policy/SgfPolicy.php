<?php

App::uses('BasePolicy', 'Policy');

/**
 * SGF viewing (the SGF admin review page) is admin-only.
 */
class SgfPolicy extends BasePolicy
{
	public static function canView($user): bool
	{
		return static::isAdmin($user);
	}
}
