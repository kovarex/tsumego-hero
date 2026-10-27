<?php

namespace App\Policy;

use App\Utility\TsumegoUtil;
use ClassRegistry;

//SGF viewing. The SGF admin review page (canView) is admin-only. A version can be fetched by admins,
//by players who recently solved the problem and by players who proposed a version of it.
/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 * @phpstan-import-type SgfRow from \App\Utility\RowTypes
 */
class SgfPolicy extends BasePolicy
{
	/** @param UserRow|null $user */
	public static function canView(?array $user): bool
	{
		return static::isAdmin($user);
	}

	/**
	 * @param UserRow|null $user
	 * @param SgfRow $sgf
	 */
	public static function canFetch(?array $user, array $sgf): bool
	{
		if ($user === null)
			return false;
		if (static::isAdmin($user))
			return true;
		// the proposer sees their versions and the ones they were compared with, the decision message links the diff
		$ownVersionCount = ClassRegistry::init('Sgf')->find('count', ['conditions' => [
			'tsumego_id' => $sgf['tsumego_id'],
			'user_id' => $user['id']]]);
		if ($ownVersionCount > 0)
			return true;
		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $sgf['tsumego_id'],
			'user_id' => $user['id']]]);
		if (!$status)
			return false;
		return TsumegoUtil::isRecentlySolved($status['TsumegoStatus']['status']);
	}
}
