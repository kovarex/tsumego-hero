<?php

App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('CookieFlash', 'Utility');

class ScheduleController extends AppController
{
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
	creator.name AS created_by_name,
	creator.id AS created_by_id
FROM schedule
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
LEFT JOIN `user` creator ON creator.id = schedule.created_by
WHERE schedule.published = 0
ORDER BY schedule.date ASC");

		$this->set('p', $p);
		$this->set('sandboxSets', $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 0, 'user_id IS NULL'],
		]) ?: []);
		$this->set('publicSets', $this->Set->find('all', [
			'order' => ['Set.order'],
			'conditions' => ['public' => 1],
		]) ?: []);
	}

	/**
	 * Schedule sandbox problems for publishing. Admin only.
	 * Schedules the next `count` unscheduled problems of the source set that are
	 * not already in the target set, optionally starting from position `num`.
	 */
	public function add(): void
	{
		$this->Authorization->authorize('Schedule');

		$setIdFrom = (int) ($this->data['set_id_from'] ?? 0);
		$targetSetId = (int) ($this->data['set_id_to'] ?? 0);
		$num = (int) ($this->data['num'] ?? 0);
		$count = (int) ($this->data['count'] ?? 1);
		$startDate = (string) ($this->data['start_date'] ?? '');

		if ($setIdFrom <= 0 || $targetSetId <= 0)
			throw new BadRequestException('Invalid set ids.');
		if ($count < 1 || $count > 100)
			throw new BadRequestException('Count must be between 1 and 100.');
		if (!strtotime($startDate))
			throw new BadRequestException('Invalid start date.');
		if (strtotime($startDate) < strtotime('tomorrow'))
			throw new BadRequestException('Start date must be tomorrow or later.');

		$sourceSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['id' => $setIdFrom, 'public' => 0, 'user_id IS NULL'],
		]);
		if (!$sourceSet)
			throw new BadRequestException('Source set must be a sandbox set.');

		$targetSet = ClassRegistry::init('Set')->find('first', [
			'conditions' => ['id' => $targetSetId, 'public' => 1],
		]);
		if (!$targetSet)
			throw new BadRequestException('Target set must be public.');

		$numCondition = $num > 0 ? " AND sc.num >= {$num}" : '';
		$candidates = Util::query("
SELECT sc.tsumego_id
FROM set_connection sc
WHERE sc.set_id = {$setIdFrom}
  AND sc.tsumego_id NOT IN (SELECT tsumego_id FROM schedule WHERE published = 0)
  AND sc.tsumego_id NOT IN (SELECT tsumego_id FROM set_connection WHERE set_id = {$targetSetId})
  {$numCondition}
ORDER BY sc.num ASC
LIMIT {$count}");

		$scheduleModel = ClassRegistry::init('Schedule');
		$scheduled = 0;
		$userId = Auth::getUserID();
		foreach ($candidates as $candidate)
		{
			$date = date('Y-m-d', strtotime($startDate . ' +' . $scheduled . ' days'));
			$scheduleModel->create();
			$scheduleModel->save([
				'Schedule' => [
					'tsumego_id' => $candidate['tsumego_id'],
					'set_id' => $targetSetId,
					'date' => $date,
					'published' => 0,
					'created_by' => $userId,
				],
			]);
			AdminActivityLogger::log(AdminActivityType::ADD_TO_SCHEDULE, $candidate['tsumego_id'], $targetSetId);
			$scheduled++;
		}

		CookieFlash::set($scheduled . ' problems scheduled.', 'success');
		$this->redirect('/schedule');
	}

	/**
	 * Preview which problems would be scheduled. Admin only. Returns JSON.
	 */
	public function preview(): void
	{
		$this->Authorization->authorize('Schedule');
		$this->autoRender = false;

		$setIdFrom = (int) ($this->params['url']['set_id_from'] ?? 0);
		$targetSetId = (int) ($this->params['url']['set_id_to'] ?? 0);
		$num = (int) ($this->params['url']['num'] ?? 0);
		$count = (int) ($this->params['url']['count'] ?? 1);

		if ($setIdFrom <= 0 || $targetSetId <= 0 || $count < 1 || $count > 100)
		{
			$this->response->type('json');
			$this->response->body(json_encode([]));
			return;
		}

		$numCondition = $num > 0 ? " AND sc.num >= {$num}" : '';
		$candidates = Util::query("
SELECT sc.tsumego_id, sc.id AS sc_id, sc.num, s.title AS set_title, s.title2 AS set_title2,
	(SELECT s1.sgf FROM sgf s1 WHERE s1.tsumego_id = sc.tsumego_id ORDER BY s1.id DESC LIMIT 1) AS sgf
FROM set_connection sc
JOIN `set` s ON s.id = sc.set_id
WHERE sc.set_id = {$setIdFrom}
  AND sc.tsumego_id NOT IN (SELECT tsumego_id FROM schedule WHERE published = 0)
  AND sc.tsumego_id NOT IN (SELECT tsumego_id FROM set_connection WHERE set_id = {$targetSetId})
  {$numCondition}
ORDER BY sc.num ASC
LIMIT {$count}");

		// Pre-parse SGF to preview data so the client doesn't need to
		App::uses('TsumegoButton', 'Utility');
		$existingNums = ClassRegistry::init('SetConnection')->find('list', [
			'fields' => ['num', 'num'],
			'conditions' => ['set_id' => $targetSetId],
		]);
		foreach ($candidates as &$c)
		{
			$c['preview'] = TsumegoButton::sgfToPreviewData($c['sgf'] ?? '');
			$c['num_collision'] = isset($existingNums[(int) $c['num']]);
			unset($c['sgf']);
		}
		unset($c);

		$this->response->type('json');
		$this->response->body(json_encode($candidates ?: []));
	}

	/**
	 * Cancel a pending schedule entry. Admin only.
	 */
	public function cancel($id): void
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

	/**
	 * Publish all due schedule entries. Called by the daily cron.
	 */
	public static function publish(): void
	{
		$date = date('Y-m-d', strtotime('today'));
		$todaysSchedule = ClassRegistry::init('Schedule')->find('all', ['conditions' => ['date' => $date, 'published' => 0]]) ?: [];
		foreach ($todaysSchedule as $item)
			if (self::publishSingle($item['Schedule']['tsumego_id'], $item['Schedule']['set_id']))
			{
				$item['Schedule']['published'] = 1;
				ClassRegistry::init('Schedule')->save($item);
			}
			else
				CakeLog::warning(sprintf(
					'Schedule publish failed: entry %d (tsumego %d -> set %d) has no sandbox source',
					$item['Schedule']['id'],
					$item['Schedule']['tsumego_id'],
					$item['Schedule']['set_id']
				));
	}

	/**
	 * Publish a single tsumego from sandbox to the target public set.
	 * Always moves the tsumego to the target set. Only resets stats and
	 * wipes tsumego_status if the tsumego is not already in any other
	 * public set (to preserve solve history for existing public problems).
	 * Returns false if the tsumego has no sandbox source to move from.
	 */
	public static function publishSingle($tsumegoID, $to): bool
	{
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			return false;

		// A tsumego lives in at most one official sandbox set, but be robust to
		// several: publishing moves it out of every sandbox set into the target.
		$sandboxConnections = ClassRegistry::init('SetConnection')->find('all', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id', 'S.public' => 0, 'S.user_id IS NULL'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumegoID],
		]) ?: [];

		$targetConnection = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['tsumego_id' => $tsumegoID, 'set_id' => $to],
		]);

		// Check before the move: is this tsumego already in any public set?
		// If so, users have solve history we must preserve.
		$alreadyPublic = ClassRegistry::init('SetConnection')->find('first', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id', 'S.public' => 1],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumegoID],
		]);

		if ($targetConnection)
		{
			// Already in the target set: drop every sandbox copy, keep the target one.
			foreach ($sandboxConnections as $sandboxConnection)
				ClassRegistry::init('SetConnection')->delete($sandboxConnection['SetConnection']['id']);
		}
		elseif ($sandboxConnections)
		{
			// Move the canonical sandbox copy (lowest num, then lowest id) to the
			// target, preserving its number, and drop the remaining copies.
			usort($sandboxConnections, function ($a, $b) {
				return [(int) $a['SetConnection']['num'], (int) $a['SetConnection']['id']] <=> [(int) $b['SetConnection']['num'], (int) $b['SetConnection']['id']];
			});
			$keep = array_shift($sandboxConnections);
			$keep['SetConnection']['set_id'] = $to;
			// Renumber to the next free slot if the sandbox num is already taken in the target
			$existingNums = ClassRegistry::init('SetConnection')->find('list', [
				'fields' => ['num', 'num'],
				'conditions' => ['set_id' => $to],
			]);
			$num = (int) $keep['SetConnection']['num'];
			while (isset($existingNums[$num]))
				$num++;
			$keep['SetConnection']['num'] = $num;
			ClassRegistry::init('SetConnection')->save($keep);
			foreach ($sandboxConnections as $sandboxConnection)
				ClassRegistry::init('SetConnection')->delete($sandboxConnection['SetConnection']['id']);
		}
		else
			return false;

		if (!$alreadyPublic)
		{
			$tsumego['Tsumego']['solved'] = 0;
			$tsumego['Tsumego']['failed'] = 0;
			$tsumego['Tsumego']['userWin'] = 0;
			$tsumego['Tsumego']['userLoss'] = 0;
			ClassRegistry::init('Tsumego')->save($tsumego);

			ClassRegistry::init('TsumegoStatus')->deleteAll(['tsumego_id' => $tsumego['Tsumego']['id']]);
		}

		return true;
	}
}
