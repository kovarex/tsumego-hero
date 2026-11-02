<?php

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\ScheduleTable;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\SchedulePublisher;
use App\Utility\TsumegoButton;

class ScheduleController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Schedule');
		$this->set('upcoming', ScheduleTable::upcoming());
		$this->set('recentlyPublished', ScheduleTable::recentlyPublished());
		$this->set('sandboxSets', ScheduleTable::sandboxSets());
		$this->set('publicSets', ScheduleTable::targetSets());
	}

	//Schedule sandbox problems for publishing. Admin only.
	//Schedules the next `count` unscheduled problems of the source set (all of them
	//when `count` is empty) that are not already in the target set, optionally starting from position `num`,
	//`per_day` of them on each day. A schedule starting today is published right
	//away, because the daily cron only publishes the day it runs on.
	#[HttpPost]
	public function add(): Response
	{
		$this->Authorization->authorize('Schedule');

		$setIdFrom = (int) ($this->input('set_id_from') ?? 0);
		$targetSetId = (int) ($this->input('set_id_to') ?? 0);
		$num = (int) ($this->input('num') ?? 0);
		$count = self::count($this->input('count'));
		$perDay = (int) ($this->input('per_day') ?? 5);
		$startDate = (string) ($this->input('start_date') ?? '');

		if ($setIdFrom <= 0 || $targetSetId <= 0)
			throw new BadRequestException('Invalid set ids.');
		if ($count !== null && $count < 1)
			throw new BadRequestException('Count must be at least 1, or empty for all.');
		if ($perDay < 1 || $perDay > 50)
			throw new BadRequestException('Problems per day must be between 1 and 50.');
		if (!strtotime($startDate))
			throw new BadRequestException('Invalid start date.');
		if (strtotime($startDate) < strtotime('today'))
			throw new BadRequestException('Start date must be today or later.');

		if (!ScheduleTable::isSandboxSet($setIdFrom))
			throw new BadRequestException('Source set must be a sandbox set.');
		if (!ScheduleTable::isTargetSet($targetSetId))
			throw new BadRequestException('Target set must be a public collection of the site.');

		$candidates = ScheduleTable::candidates($setIdFrom, $targetSetId, $num, $count);

		$scheduleTable = $this->fetchTable('Schedule');
		$scheduled = 0;
		$userId = Auth::getUserID();
		foreach ($candidates as $candidate)
		{
			$day = intdiv($scheduled, $perDay);
			$date = date('Y-m-d', strtotime($startDate . ' +' . $day . ' days'));
			$scheduleTable->saveOrFail($scheduleTable->newEntity([
				'tsumego_id' => $candidate['tsumego_id'],
				'set_id' => $targetSetId,
				'date' => $date,
				'published' => false,
				'created_by_user_id' => $userId,
			]));
			AdminActivityLogger::log(AdminActivityTypeTable::ADD_TO_SCHEDULE, $candidate['tsumego_id'], $targetSetId);
			$scheduled++;
		}

		$startsToday = date('Y-m-d', strtotime($startDate)) === date('Y-m-d');
		if ($startsToday)
			SchedulePublisher::publishDue();

		if ($scheduled === 0)
			CookieFlash::set('Nothing scheduled: everything from that position on is already published or already scheduled.', 'info');
		else
			CookieFlash::set($scheduled . ($startsToday ? ' problems published.' : ' problems scheduled.'), 'success');
		return $this->redirect('/schedule');
	}

	//Preview which problems would be scheduled, and on which day each of them lands. Admin only. Returns JSON.
	#[HttpGet]
	public function preview(): Response
	{
		$this->Authorization->authorize('Schedule');

		$setIdFrom = (int) ($this->query('set_id_from') ?? 0);
		$targetSetId = (int) ($this->query('set_id_to') ?? 0);
		$num = (int) ($this->query('num') ?? 0);
		$count = self::count($this->query('count'));
		$perDay = max(1, (int) ($this->query('per_day') ?? 5));
		$startDate = strtotime((string) ($this->query('start_date') ?? '')) ?: strtotime('today');

		if ($setIdFrom <= 0 || $targetSetId <= 0 || ($count !== null && $count < 1))
			return $this->respondJson([]);

		$candidates = ScheduleTable::candidates($setIdFrom, $targetSetId, $num, $count);

		// Pre-parse SGF to preview data so the client doesn't need to
		$existingNums = $this->fetchTable('SetConnection')->find('list', keyField: 'num', valueField: 'num')->where(['set_id' => $targetSetId])->toArray();
		foreach ($candidates as $index => &$candidate)
		{
			$candidate['preview'] = TsumegoButton::sgfToPreviewData($candidate['sgf'] ?? '');
			$candidate['num_collision'] = isset($existingNums[(int) $candidate['num']]);
			$candidate['date'] = date('Y-m-d', strtotime('+' . intdiv($index, $perDay) . ' days', $startDate));
			unset($candidate['sgf']);
		}
		unset($candidate);

		return $this->respondJson($candidates ?: []);
	}

	//How many problems to schedule, null for all of them, which an empty field asks for.
	private static function count(mixed $value): ?int
	{
		return $value === null || $value === '' ? null : (int) $value;
	}

	//Cancel a pending schedule entry. Admin only.
	#[HttpPost]
	public function cancel(?string $id = null): Response
	{
		$this->Authorization->authorize('Schedule');

		$scheduleTable = $this->fetchTable('Schedule');
		$schedule = $scheduleTable->find()->where(['id' => (int) $id])->first();
		if (!$schedule)
			throw new NotFoundException('Schedule entry not found.');
		if ($schedule->published)
			throw new BadRequestException('Schedule entry is already published.');

		$scheduleTable->deleteAll(['id' => $schedule->id]);
		AdminActivityLogger::log(AdminActivityTypeTable::CANCEL_SCHEDULE, $schedule->tsumego_id, $schedule->set_id);

		CookieFlash::set('Schedule entry cancelled.', 'success');
		return $this->redirect('/schedule');
	}
}
