<?php

App::uses('ScheduleController', 'Controller');

class ScheduleTest extends TestCaseWithAuth
{
	private function _sandboxAndTarget(array $sandboxNums, int $publicCount = 1): array
	{
		$tsumegos = [];
		foreach ($sandboxNums as $num)
			$tsumegos[] = ['sets' => [['name' => 'sandbox set', 'num' => $num, 'public' => 0]]];
		for ($i = 0; $i < $publicCount; $i++)
			$tsumegos[] = ['sets' => [['name' => 'public set', 'num' => $i + 1]]];

		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => $tsumegos,
		]);
		$this->login('admin');

		$sandboxSetId = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$targetSetId = $context->tsumegos[count($sandboxNums)]['set-connections'][0]['set_id'];

		return [$context, $sandboxSetId, $targetSetId];
	}

	public function testAddToScheduleCreatesRows(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3]);

		$this->testAction('/schedule/add', [
			'data' => [
				'set_id_from' => $sandboxSetId,
				'set_id_to' => $targetSetId,
				'count' => 2,
				'start_date' => '2026-09-01',
			],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all', ['order' => 'date ASC']);
		$this->assertCount(2, $schedules);

		$first = $schedules[0]['Schedule'];
		$second = $schedules[1]['Schedule'];
		$this->assertSame($context->tsumegos[0]['id'], (int) $first['tsumego_id']);
		$this->assertSame($context->tsumegos[1]['id'], (int) $second['tsumego_id']);
		$this->assertSame($targetSetId, (int) $first['set_id']);
		$this->assertSame($targetSetId, (int) $second['set_id']);
		$this->assertSame('2026-09-01', $first['date']);
		$this->assertSame('2026-09-02', $second['date']);
		$this->assertSame(0, (int) $first['published']);
	}

	public function testAddToScheduleSkipsAlreadyScheduledProblems(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2]);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'start_date' => '2026-09-02'],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all', ['order' => 'date ASC']);
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[0]['id'], (int) $schedules[0]['Schedule']['tsumego_id']);
		$this->assertSame($context->tsumegos[1]['id'], (int) $schedules[1]['Schedule']['tsumego_id']);
	}

	public function testAddToScheduleByNum(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3]);

		$this->testAction('/schedule/add', [
			'data' => [
				'set_id_from' => $sandboxSetId,
				'set_id_to' => $targetSetId,
				'num' => 3,
				'start_date' => '2026-09-01',
			],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all');
		$this->assertCount(1, $schedules);
		$this->assertSame($context->tsumegos[2]['id'], (int) $schedules[0]['Schedule']['tsumego_id']);
		$this->assertSame('2026-09-01', $schedules[0]['Schedule']['date']);
	}

	public function testAddToScheduleStartsFromNum(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3, 4]);

		$this->testAction('/schedule/add', [
			'data' => [
				'set_id_from' => $sandboxSetId,
				'set_id_to' => $targetSetId,
				'num' => 2,
				'count' => 2,
				'start_date' => '2026-09-01',
			],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all', ['order' => 'date ASC']);
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[1]['id'], (int) $schedules[0]['Schedule']['tsumego_id']);
		$this->assertSame($context->tsumegos[2]['id'], (int) $schedules[1]['Schedule']['tsumego_id']);
	}

	public function testAddToScheduleSkipsProblemsAlreadyInTargetSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [
					['name' => 'sandbox set', 'num' => 2, 'public' => 0],
					['name' => 'public set', 'num' => 1],
				]],
				['sets' => [['name' => 'sandbox set', 'num' => 3, 'public' => 0]]],
			],
		]);
		$this->login('admin');

		$sandboxSetId = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][1]['set_id'];

		$this->testAction('/schedule/add', [
			'data' => [
				'set_id_from' => $sandboxSetId,
				'set_id_to' => $targetSetId,
				'count' => 3,
				'start_date' => '2026-09-01',
			],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all', ['order' => 'date ASC']);
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[0]['id'], (int) $schedules[0]['Schedule']['tsumego_id']);
		$this->assertSame($context->tsumegos[2]['id'], (int) $schedules[1]['Schedule']['tsumego_id']);
	}

	public function testAddToScheduleRequiresAdmin(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'public set', 'num' => 1]]]],
		]);
		$this->login('alice');

		$this->expectException(ForbiddenException::class);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => 1, 'set_id_to' => 2, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);
	}

	public function testAddToScheduleRejectsPastStartDate(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->expectException(BadRequestException::class);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2020-01-01'],
			'method' => 'POST',
		]);
	}

	public function testShowPublishScheduleShowsSandboxSourceWhenNotYetInTarget(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [
					['name' => 'other set', 'num' => 99],
					['name' => 'sandbox set', 'num' => 1, 'public' => 0],
				]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => '2026-09-01'],
			],
		]);
		$this->login('admin');

		$this->testAction('/schedule');

		$p = $this->vars['p'];
		$this->assertCount(1, $p);
		$this->assertSame(1, (int) $p[0]['num']);
		$this->assertStringContainsString('sandbox set', $p[0]['sandbox_set_title']);
		$this->assertStringNotContainsString('other set', $p[0]['sandbox_set_title']);
	}

	public function testShowPublishScheduleShowsTargetSetWhenAlreadyThere(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'target set', 'num' => 436]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => '2026-09-01'],
			],
		]);
		$this->login('admin');

		$this->testAction('/schedule');

		$p = $this->vars['p'];
		$this->assertCount(1, $p);
		$this->assertSame(436, (int) $p[0]['num']);
		$this->assertStringContainsString('target set', $p[0]['target_set_title']);
	}

	public function testAddToScheduleLogsAdminActivity(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
		$this->assertSame((int) $context->user['id'], (int) $activity['AdminActivity']['user_id']);
		$this->assertSame(AdminActivityType::ADD_TO_SCHEDULE, (int) $activity['AdminActivity']['type']);
		$this->assertSame($targetSetId, (int) $activity['AdminActivity']['set_id']);
	}

	public function testCancelScheduleDeletesPendingRow(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => '2026-09-01'],
			],
		]);
		$this->login('admin');

		$scheduleId = ClassRegistry::init('Schedule')->find('first', ['order' => 'id DESC'])['Schedule']['id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		$this->testAction('/schedule/cancel/' . $scheduleId, ['method' => 'POST']);

		$this->assertSame([], ClassRegistry::init('Schedule')->findById($scheduleId));

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
		$this->assertSame((int) $context->user['id'], (int) $activity['AdminActivity']['user_id']);
		$this->assertSame(AdminActivityType::CANCEL_SCHEDULE, (int) $activity['AdminActivity']['type']);
		$this->assertSame($targetSetId, (int) $activity['AdminActivity']['set_id']);
	}

	public function testCanceledRowCanBeRescheduled(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);
		$scheduleId = ClassRegistry::init('Schedule')->id;

		$this->testAction('/schedule/cancel/' . $scheduleId, ['method' => 'POST']);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);

		$this->assertSame(1, ClassRegistry::init('Schedule')->find('count'));
	}

	public function testCanceledRowIsRemovedFromUpcoming(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2]);

		$this->testAction('/schedule/add', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);

		$schedules = ClassRegistry::init('Schedule')->find('all');
		$this->testAction('/schedule/cancel/' . $schedules[0]['Schedule']['id'], ['method' => 'POST']);

		$this->testAction('/schedule');

		$this->assertCount(1, $this->vars['p']);
	}

	public function testCancelScheduleRejectsPublishedRow(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => '2026-09-01', 'published' => 1],
			],
		]);
		$this->login('admin');

		$scheduleId = ClassRegistry::init('Schedule')->find('first', ['order' => 'id DESC'])['Schedule']['id'];

		$this->expectException(BadRequestException::class);

		$this->testAction('/schedule/cancel/' . $scheduleId, ['method' => 'POST']);
	}

	public function testPublishSkipsAlreadyPublishedTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]], 'solved' => 42, 'failed' => 7],
				['sets' => [['name' => 'set 1', 'num' => 3]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'set 1', 'date' => date('Y-m-d'), 'published' => 1],
			],
		]);
		$this->login('admin');

		ScheduleController::publish();

		$tsumego = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);
		$this->assertSame(42, (int) $tsumego['Tsumego']['solved']);
		$this->assertSame(7, (int) $tsumego['Tsumego']['failed']);
	}

	public function testPublishWhenAlreadyInTargetSetKeepsOneConnection(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [
					['name' => 'sandbox set', 'num' => 5, 'public' => 0],
					['name' => 'set 1', 'num' => 3],
				]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'set 1', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		$targetSetId = $context->tsumegos[0]['set-connections'][1]['set_id'];

		ScheduleController::publish();

		$count = ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame(1, $count);
	}

	public function testPublishFromMultipleSandboxesMovesOneDeletesRest(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [
					['name' => 'sandbox A', 'num' => 1, 'public' => 0],
					['name' => 'sandbox B', 'num' => 1, 'public' => 0],
				]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		$tsumego = $context->tsumegos[0];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		$sandboxBefore = ClassRegistry::init('SetConnection')->find('all', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id', 'S.public' => 0, 'S.user_id IS NULL'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumego['id']],
		]);
		$this->assertCount(2, $sandboxBefore, 'Tsumego should be in 2 sandbox sets before publish');

		ScheduleController::publish();

		$targetCount = ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumego['id']],
		]);
		$this->assertSame(1, $targetCount, 'Should be in target set exactly once');

		$sandboxAfter = ClassRegistry::init('SetConnection')->find('all', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id', 'S.public' => 0, 'S.user_id IS NULL'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumego['id']],
		]);
		$this->assertCount(0, $sandboxAfter, 'Should have no sandbox connections after publish');

		$schedule = ClassRegistry::init('Schedule')->find('first', [
			'conditions' => ['tsumego_id' => $tsumego['id']],
		]);
		$this->assertSame(1, (int) $schedule['Schedule']['published']);
	}

	public function testPublishPreservesStatsWhenAlreadyPublic(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [
					['name' => 'sandbox set', 'num' => 1, 'public' => 0],
					['name' => 'existing public', 'num' => 1],
				], 'solved' => 42, 'failed' => 7, 'userWin' => 85.5, 'userLoss' => 100],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		ScheduleController::publish();

		// Stats should be preserved because tsumego is already in another public set
		$tsumego = ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id']);
		$this->assertSame(42, (int) $tsumego['Tsumego']['solved']);
		$this->assertSame(7, (int) $tsumego['Tsumego']['failed']);
		$this->assertSame(85.5, (float) $tsumego['Tsumego']['userWin']);
		$this->assertSame(100, (int) $tsumego['Tsumego']['userLoss']);
	}

	public function testPublishFailedEntryStaysPending(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'existing public', 'num' => 1]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		ScheduleController::publish();

		// The tsumego has no sandbox source to move from, so the entry stays pending
		$schedule = ClassRegistry::init('Schedule')->find('first', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertSame(0, (int) $schedule['Schedule']['published']);
	}

	public function testPublishRenumbersOnNumCollision(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 5]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		ScheduleController::publish();

		$nums = ClassRegistry::init('SetConnection')->find('list', [
			'fields' => ['num', 'num'],
			'conditions' => ['set_id' => $targetSetId],
		]);
		$this->assertCount(2, $nums, 'Both tsumegos should be in the target set');
		$this->assertSame(2, count(array_unique($nums)), 'No duplicate nums in target set');

		$moved = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $context->tsumegos[0]['id']],
		]);
		$this->assertNotSame(5, (int) $moved['SetConnection']['num'], 'Moved problem should be renumbered away from the taken slot');
	}

	public function testPreviewFlagsNumCollision(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'sandbox set', 'num' => 6, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 5]]],
			],
		]);
		$this->login('admin');

		$sandboxSetId = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$targetSetId = $context->tsumegos[2]['set-connections'][0]['set_id'];

		$this->testAction('/schedule/preview', [
			'method' => 'GET',
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'num' => 0],
		]);

		$body = json_decode($this->controller->response->body(), true);
		$this->assertCount(2, $body);
		$this->assertTrue($body[0]['num_collision'], 'num 5 collides with the target set');
		$this->assertFalse($body[1]['num_collision'], 'num 6 is free in the target set');
	}
}
