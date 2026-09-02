<?php

App::uses('BasePolicy', 'Policy');

/**
 * User-level authorization.
 *
 * Self-service actions that operate on the caller's own account (e.g. setting
 * personal preferences) only require an authenticated identity.
 */
class UserPolicy extends BasePolicy
{
	public static function canEditPreferences($user): bool
	{
		return $user !== null;
	}
}
