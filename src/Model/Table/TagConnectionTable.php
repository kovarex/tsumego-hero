<?php

namespace App\Model\Table;

use App\Utility\Auth;
use App\Utility\Constants;
use Cake\ORM\TableRegistry;

//A tag on a problem. A player's tag is a proposal until an admin approves it, an admin's is approved at once.
class TagConnectionTable extends AppTable
{
	//How many tag connections the user has added today (UTC calendar day).
	public static function countTodaysTags(int $userId): int
	{
		return TableRegistry::getTableLocator()->get('TagConnection')->find()
			->where(['user_id' => $userId, 'created >=' => gmdate('Y-m-d') . ' 00:00:00'])
			->count();
	}

	//Whether the current user may add more tags today. Admins are never rate-limited.
	public static function canCurrentUserAddTag(): bool
	{
		if (Auth::isAdmin())
			return true;
		return self::countTodaysTags(Auth::getUserID()) < Constants::$DAILY_TAG_LIMIT;
	}
}
