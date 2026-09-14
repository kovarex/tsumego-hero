<?php

namespace App\Policy;

/**
 * SGF viewing (the SGF admin review page) is admin-only.
 *
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class SgfPolicy extends BasePolicy
{
	/**
	 * @param UserRow|null $user
	 */
	public static function canView(?array $user): bool
	{
		return static::isAdmin($user);
	}
}
