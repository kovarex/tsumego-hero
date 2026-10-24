<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\SchedulePublisher;
use App\Utility\TsumegoButton;
use App\Utility\Util;

class ScheduleController extends AppController
{
	/**
	 * A sandbox set: an unpublished collection of the site that is not in the trash.
	 */
	private const SANDBOX_CONDITION = '`Set`.public = 0 AND `Set`.user_id IS NULL AND `Set`.deleted IS NULL';

	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Schedule');
		$this->loadModel('Set');

		$p = Util::query("
SELECT
	schedule.id AS id,
	schedule.date AS date,
	schedule.tsumego_id AS tsumego_id,
	schedule.set_id AS set_id,
	COALESCE(sandbox_sc.num, target_sc.num) AS num,
	sandbox_source.set_id AS sandbox_set_id,
	sandbox_set.title AS sandbox_set_title,
	sandbox_set.title2 AS sandbox_set_title2,
	target_set.title AS target_set_title,
	COALESCE(sandbox_sc.id, target_sc.id) AS sc_id,
	sgf.sgf AS sgf,
	tsumego.deleted AS tsumego_deleted,
	creator.name AS created_by_name,
	creator.id AS created_by_id
FROM schedule
LEFT JOIN tsumego ON tsumego.id = schedule.tsumego_id
LEFT JOIN set_connection target_sc
	ON target_sc.tsumego_id = schedule.tsumego_id
	AND target_sc.set_id = schedule.set_id
LEFT JOIN `set` target_set ON target_set.id = schedule.set_id
LEFT JOIN (
	SELECT sc.tsumego_id, MIN(sc.set_id) AS set_id
	FROM set_connection sc
	JOIN `set` st ON st.id = sc.set_id
	WHERE st.public = 0 AND st.user_id IS NULL
	GROUP BY sc.tsumego_id
) sandbox_source ON sandbox_source.tsumego_id = schedule.tsumego_id
LEFT JOIN set_connection sandbox_sc
	ON sandbox_sc.tsumego_id = schedule.tsumego_id
	AND sandbox_sc.set_id = sandbox_source.set_id
LEFT JOIN `set` sandbox_set ON sandbox_set.id = sandbox_sc.set_id
LEFT JOIN sgf ON sgf.id = (
	SELECT s1.id FROM sgf s1
	WHERE s1.tsumego_id = schedule.tsumego_id
	ORDER BY s1.id DESC LIMIT 1
)
LEFT JOIN `user` creator ON creator.id = schedule.created_by_user_id
WHERE schedule.published = 0
ORDER BY schedule.date ASC");

		$this->set('p', $p);

		$recentlyPublished = Util::query("
SELECT
	schedule.id AS id,
	schedule.date AS date,
	schedule.tsumego_id AS tsumego_id,
	schedule.set_id AS set_id,
	target_sc.num AS num,
	target_set.title AS target_set_title
FROM schedule
LEFT JOIN set_connection target_sc
	ON target_sc.tsumego_id = schedule.tsumego_id
	AND target_sc.set_id = schedule.set_id
LEFT JOIN `set` target_set ON target_set.id = schedule.set_id
WHERE schedule.published = 1
	AND schedule.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY schedule.date DESC, schedule.id DESC");
		$this->set('recentlyPublished', $recentlyPublished);

		$this->set('sandboxSets', $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => [self::SANDBOX_CONDITION],
		]) ?: []);
		$this->set('publicSets', $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => [Set::officialCondition('Set')],
		]) ?: []);
	}

	/**
	 * Schedule sandbox problems for publishing. Admin only.
	 * Schedules the next `count` unscheduled problems of the source set (all of them
	 * when `count` is empty) that are not already in the target set, optionally starting from position `num`,
	 * `per_day` of them on each day. A schedule starting today is published right
	 * away, because the daily cron only publishes the day it runs on.
	 */
	#[HttpPost]
	public function add(): void
	{
		$this->Authorization->authorize('Schedule');

		$setIdFrom = (int) ($this->request->data['set_id_from'] ?? 0);
		$targetSetId = (int) ($this->request->data['set_id_to'] ?? 0);
		$num = (int) ($this->request->data['num'] ?? 0);
		$count = self::count($this->request->data['count'] ?? null);
		$perDay = (int) ($this->request->data['per_day'] ?? 5);
		$startDate = (string) ($this->request->data['start_date'] ?? '');

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

		$sourceSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['Set.id' => $setIdFrom, self::SANDBOX_CONDITION],
		]);
		if (!$sourceSet)
			throw new BadRequestException('Source set must be a sandbox set.');

		$targetSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['Set.id' => $targetSetId, Set::officialCondition('Set')],
		]);
		if (!$targetSet)
			throw new BadRequestException('Target set must be a public collection of the site.');

		$candidates = self::candidates($setIdFrom, $targetSetId, $num, $count);

		$scheduleModel = ClassRegistry::init('Schedule');
		$scheduled = 0;
		$userId = Auth::getUserID();
		foreach ($candidates as $candidate)
		{
			$day = intdiv($scheduled, $perDay);
			$date = date('Y-m-d', strtotime($startDate . ' +' . $day . ' days'));
			$scheduleModel->create();
			$scheduleModel->save([
				'Schedule' => [
					'tsumego_id' => $candidate['tsumego_id'],
					'set_id' => $targetSetId,
					'date' => $date,
					'published' => 0,
					'created_by_user_id' => $userId,
				],
			]);
			AdminActivityLogger::log(AdminActivityType::ADD_TO_SCHEDULE, $candidate['tsumego_id'], $targetSetId);
			$scheduled++;
		}

		$startsToday = date('Y-m-d', strtotime($startDate)) === date('Y-m-d');
		if ($startsToday)
			SchedulePublisher::publishDue();

		if ($scheduled === 0)
			CookieFlash::set('Nothing scheduled: everything from that position on is already published or already scheduled.', 'info');
		else
			CookieFlash::set($scheduled . ($startsToday ? ' problems published.' : ' problems scheduled.'), 'success');
		$this->redirect('/schedule');
	}

	/**
	 * Preview which problems would be scheduled, and on which day each of them lands.
	 * Admin only. Returns JSON.
	 */
	#[HttpGet]
	public function preview(): void
	{
		$this->Authorization->authorize('Schedule');
		$this->autoRender = false;

		$setIdFrom = (int) ($this->request->query['set_id_from'] ?? 0);
		$targetSetId = (int) ($this->request->query['set_id_to'] ?? 0);
		$num = (int) ($this->request->query['num'] ?? 0);
		$count = self::count($this->request->query['count'] ?? null);
		$perDay = max(1, (int) ($this->request->query['per_day'] ?? 5));
		$startDate = strtotime((string) ($this->request->query['start_date'] ?? '')) ?: strtotime('today');

		if ($setIdFrom <= 0 || $targetSetId <= 0 || ($count !== null && $count < 1))
		{
			$this->response->type('json');
			$this->response->body(json_encode([]));
			return;
		}

		$candidates = self::candidates($setIdFrom, $targetSetId, $num, $count);

		// Pre-parse SGF to preview data so the client doesn't need to
		$existingNums = ClassRegistry::init('SetConnection')->find('list', [
			'fields' => ['num', 'num'],
			'conditions' => ['set_id' => $targetSetId],
		]);
		foreach ($candidates as $index => &$c)
		{
			$c['preview'] = TsumegoButton::sgfToPreviewData($c['sgf'] ?? '');
			$c['num_collision'] = isset($existingNums[(int) $c['num']]);
			$c['date'] = date('Y-m-d', strtotime('+' . intdiv($index, $perDay) . ' days', $startDate));
			unset($c['sgf']);
		}
		unset($c);

		$this->response->type('json');
		$this->response->body(json_encode($candidates ?: []));
	}

	/**
	 * How many problems to schedule, null for all of them, which an empty field asks for.
	 */
	private static function count(mixed $value): ?int
	{
		return $value === null || $value === '' ? null : (int) $value;
	}

	/**
	 * The problems of the source set that the next schedule takes, in the order of the set: the
	 * ones not waiting in the schedule already, not in the target set already and not in the
	 * trash, from position `num` on when it is given, at most `count` of them when it is given.
	 *
	 * @return array<array{tsumego_id: int, sc_id: int, num: int, sgf: ?string}>
	 */
	private static function candidates(int $setIdFrom, int $targetSetId, int $num, ?int $count): array
	{
		return Util::query("
SELECT sc.tsumego_id, sc.id AS sc_id, sc.num,
	(SELECT s1.sgf FROM sgf s1 WHERE s1.tsumego_id = sc.tsumego_id ORDER BY s1.id DESC LIMIT 1) AS sgf
FROM set_connection sc
JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL
WHERE sc.set_id = ?
	AND sc.num >= ?
	AND NOT EXISTS (SELECT 1 FROM schedule WHERE schedule.tsumego_id = sc.tsumego_id AND schedule.published = 0)
	AND NOT EXISTS (SELECT 1 FROM set_connection target WHERE target.tsumego_id = sc.tsumego_id AND target.set_id = ?)
ORDER BY sc.num ASC" . ($count === null ? '' : "
LIMIT " . $count), [$setIdFrom, $num, $targetSetId]);
	}

	/**
	 * Cancel a pending schedule entry. Admin only.
	 */
	#[HttpPost]
	public function cancel(?string $id = null): void
	{
		$this->Authorization->authorize('Schedule');

		$id = (int) $id;
		$schedule = ClassRegistry::init('Schedule')->findById($id);
		if (!$schedule)
			throw new NotFoundException('Schedule entry not found.');
		if ($schedule['Schedule']['published'])
			throw new BadRequestException('Schedule entry is already published.');

		ClassRegistry::init('Schedule')->delete($id);
		AdminActivityLogger::log(AdminActivityType::CANCEL_SCHEDULE, $schedule['Schedule']['tsumego_id'], $schedule['Schedule']['set_id']);

		CookieFlash::set('Schedule entry cancelled.', 'success');
		$this->redirect('/schedule');
	}
}
