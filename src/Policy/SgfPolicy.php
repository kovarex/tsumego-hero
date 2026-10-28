<?php

namespace App\Policy;

use Cake\ORM\TableRegistry;
use App\Model\Entity\Sgf;
use App\Model\Table\TsumegoStatusTable;
use App\Utility\TsumegoUtil;

//SGF viewing. The SGF admin review page (canView) is admin-only. A version can be fetched by admins,
//by players who recently solved the problem and by players who proposed a version of it.
/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
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
	 */
	public static function canFetch(?array $user, Sgf $sgf): bool
	{
		if ($user === null)
			return false;
		if (static::isAdmin($user))
			return true;
		// the proposer sees their versions and the ones they were compared with, the decision message links the diff
		if (TableRegistry::getTableLocator()->get('Sgf')->exists(['tsumego_id' => $sgf->tsumego_id, 'user_id' => $user['id']]))
			return true;
		$status = TsumegoStatusTable::statusOf((int) $user['id'], $sgf->tsumego_id);
		if ($status === null)
			return false;
		return TsumegoUtil::isRecentlySolved($status);
	}
}
