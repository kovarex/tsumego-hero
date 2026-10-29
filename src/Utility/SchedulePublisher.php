<?php

namespace App\Utility;

use App\Model\Entity\Schedule;
use Cake\ORM\TableRegistry;
use Cake\Log\Log as CakeLog;
use App\Model\Table\SetTable;

//Publishes the schedule entries that are due, which the daily cron runs.
//This lives outside the controllers on purpose: a public method of a controller is an
//action, so any url could run it, and publishing takes the ids of the entry it works on.
class SchedulePublisher
{
	//Publish every entry that is due, oldest first. Entries of a day the cron did not
	//run on are published by the next run, so nothing stays pending forever. An entry
	//that cannot be published stays pending, so the schedule page shows it, and is logged.
	public static function publishDue(): void
	{
		$date = date('Y-m-d', strtotime('today'));
		$scheduleTable = TableRegistry::getTableLocator()->get('Schedule');
		$dueSchedule = $scheduleTable->find()
			->where(['date <=' => $date, 'published' => false])
			->orderBy(['date' => 'ASC'])
			->all();
		foreach ($dueSchedule as $item)
		{
			$failure = self::publishSingle($item->tsumego_id, $item->set_id);
			if ($failure === null)
			{
				$item->published = true;
				$scheduleTable->saveOrFail($item);
				SiteGrowth::forget();
			}
			else
				CakeLog::warning(sprintf(
					'Schedule publish failed: entry %d (tsumego %d -> set %d): %s',
					$item->id,
					$item->tsumego_id,
					$item->set_id,
					$failure
				));
		}
	}

	//Publish a single tsumego from sandbox to the target public set.
	//Always moves the tsumego to the target set. Only wipes tsumego_status
	//if the tsumego is not already in any other public set (to preserve
	//solve history for existing public problems).
	//Returns why it could not be published, null when it was.
	private static function publishSingle(int|string|null $tsumegoID = null, int|string|null $to = null): ?string
	{
		$tsumego = TableRegistry::getTableLocator()->get('Tsumego')->find()->select(['id', 'deleted'])->where(['id' => (int) $tsumegoID])->first();
		if (!$tsumego)
			return 'the problem does not exist';
		if ($tsumego->deleted !== null)
			return 'the problem is in the trash';
		if (!TableRegistry::getTableLocator()->get('Set')->exists(['Set.id' => $to, SetTable::officialCondition('Set')]))
			return 'the target is not a public collection of the site';

		// A tsumego lives in at most one official sandbox set, but be robust to
		// several: publishing moves it out of every sandbox set into the target.
		$setConnectionTable = TableRegistry::getTableLocator()->get('SetConnection');
		$sandboxConnections = $setConnectionTable->find()
			->innerJoin(['S' => 'set'], ['S.id = SetConnection.set_id', 'S.public' => 0, 'S.user_id IS' => null])
			->where(['SetConnection.tsumego_id' => $tsumegoID])
			->all()->toList();

		$targetConnection = $setConnectionTable->exists(['tsumego_id' => $tsumegoID, 'set_id' => $to]);

		// Check before the move: is this tsumego already in any public set?
		// If so, users have solve history we must preserve.
		$alreadyPublic = $setConnectionTable->find()
			->innerJoin(['S' => 'set'], ['S.id = SetConnection.set_id', SetTable::officialCondition('S')])
			->select(['SetConnection.id'])
			->where(['SetConnection.tsumego_id' => $tsumegoID])
			->first() !== null;

		if ($targetConnection)
		{
			// Already in the target set: drop every sandbox copy, keep the target one.
			foreach ($sandboxConnections as $sandboxConnection)
				$setConnectionTable->deleteOrFail($sandboxConnection);
		}
		elseif ($sandboxConnections)
		{
			// Move the canonical sandbox copy (lowest num, then lowest id) to the
			// target, preserving its number, and drop the remaining copies.
			usort($sandboxConnections, function ($a, $b) {
				return [$a->num, $a->id] <=> [$b->num, $b->id];
			});
			$keep = array_shift($sandboxConnections);
			$keep->set_id = (int) $to;
			// Renumber to the next free slot if the sandbox num is already taken in the target
			$existingNums = $setConnectionTable->find('list', keyField: 'num', valueField: 'num')->where(['set_id' => $to])->toArray();
			$num = $keep->num;
			while (isset($existingNums[$num]))
				$num++;
			$keep->num = $num;
			$setConnectionTable->saveOrFail($keep);
			foreach ($sandboxConnections as $sandboxConnection)
				$setConnectionTable->deleteOrFail($sandboxConnection);
		}
		else
			return 'the problem is in no sandbox set to move from';

		if (!$alreadyPublic)
			TableRegistry::getTableLocator()->get('TsumegoStatus')->deleteAll(['tsumego_id' => $tsumego->id]);

		return null;
	}
}
