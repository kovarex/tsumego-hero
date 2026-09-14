<?php

namespace App\Policy;

/**
 * User-level authorization.
 *
 * Self-service actions that operate on the caller's own account (e.g. setting
 * personal preferences) only require an authenticated identity.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class UserPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canEditPreferences(?array $user): bool
	{
		return $user !== null;
	}
}
