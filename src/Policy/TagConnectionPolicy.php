<?php

namespace App\Policy;

use App\Model\Entity\Tsumego;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Entity\TagConnection;
use App\Utility\TsumegoUtil;

//Tag connections: proposing a tag requires the canPropose capability and, as tags are hints,
//having solved the problem; removing one is allowed for admins or the proposer (unapproved
//proposals only).
/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class TagConnectionPolicy extends BasePolicy
{
	//A player tags only a problem they solved, an admin any (forum#94).
	/** @param UserRow|null $user */
	public static function canAdd(?array $user, Tsumego $tsumego): bool
	{
		if (!static::canPropose($user))
			return false;
		if (static::isAdmin($user))
			return true;
		$status = TsumegoStatusTable::statusOf((int) $user['id'], $tsumego->id);
		return $status !== null && TsumegoUtil::isSolvedStatus($status);
	}

	//Removing a proposal: admins or the proposer (unapproved only).
	/** @param UserRow|null $user */
	public static function canRemove(?array $user, TagConnection $tagConnection): bool
	{
		if (static::isAdmin($user))
			return true;
		if ($user === null)
			return false;
		return $tagConnection->user_id === (int) $user['id']
			&& !$tagConnection->approved;
	}
}
