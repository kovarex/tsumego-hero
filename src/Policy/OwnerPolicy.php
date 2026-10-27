<?php

namespace App\Policy;

//Owner-only actions. An owner can do everything an admin can (see AdminPolicy), these are the actions on top of that.
/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class OwnerPolicy extends BasePolicy
{
	//Seeing who is an admin or an owner, and making someone an admin, an owner or a player again.
	/** @param UserRow|null $user */
	public static function canManageRoles(?array $user): bool
	{
		return static::isOwner($user);
	}
}
