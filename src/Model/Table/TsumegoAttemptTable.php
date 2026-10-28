<?php

namespace App\Model\Table;

use App\Model\Entity\TsumegoAttempt;
use Cake\ORM\TableRegistry;

//The tries of the players on the problems. The table is large: whatever reads many of its rows stays
//plain SQL, the ORM reads single attempts only.
class TsumegoAttemptTable extends AppTable
{
	//The latest attempt of the player on the problem. An unsolved one collects the fails until
	//the problem is solved, a solve starts a new one.
	public static function findLast(int $userID, int $tsumegoID): ?TsumegoAttempt
	{
		return TableRegistry::getTableLocator()->get('TsumegoAttempt')->find()
			->where(['user_id' => $userID, 'tsumego_id' => $tsumegoID])
			->orderBy(['id' => 'DESC'])
			->first();
	}

	//The fails of the player on the problem since it was last solved.
	public static function failsInARow(?TsumegoAttempt $lastAttempt): int
	{
		if (!$lastAttempt || $lastAttempt->solved)
			return 0;
		return $lastAttempt->misplays;
	}
}
