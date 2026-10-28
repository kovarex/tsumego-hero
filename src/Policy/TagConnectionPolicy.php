<?php

namespace App\Policy;

use App\Model\Entity\TagConnection;
use App\Utility\TsumegoUtil;
use ClassRegistry;

//Tag connections: proposing a tag requires the canPropose capability and, as tags are hints,
//having solved the problem; removing one is allowed for admins or the proposer (unapproved
//proposals only).
/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type TsumegoRow from \App\Utility\RowTypes
 */
class TagConnectionPolicy extends BasePolicy
{
	//A player tags only a problem they solved, an admin any (forum#94).
	/**
	 * @param UserRow|null $user
	 * @param TsumegoRow $tsumego
	 */
	public static function canAdd(?array $user, array $tsumego): bool
	{
		if (!static::canPropose($user))
			return false;
		if (static::isAdmin($user))
			return true;
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $tsumego['id'],
			'user_id' => $user['id']]]);
		return $status && TsumegoUtil::isSolvedStatus($status['TsumegoStatus']['status']);
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
