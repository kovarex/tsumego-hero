<?php

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

		$this->testAction('/users/addToSchedule', [
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
		$this->assertSame($sandboxSetId, (int) $first['set_id_from']);
	}

	public function testAddToScheduleSkipsAlreadyScheduledProblems(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2]);

		$this->testAction('/users/addToSchedule', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);

		$this->testAction('/users/addToSchedule', [
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

		$this->testAction('/users/addToSchedule', [
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

		$this->testAction('/users/addToSchedule', [
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

		$this->testAction('/users/addToSchedule', [
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

		$this->testAction('/users/addToSchedule', [
			'data' => ['set_id_from' => 1, 'set_id_to' => 2, 'count' => 1, 'start_date' => '2026-09-01'],
			'method' => 'POST',
		]);
	}

	public function testAddToScheduleRejectsPastStartDate(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->expectException(BadRequestException::class);

		$this->testAction('/users/addToSchedule', [
			'data' => ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2020-01-01'],
			'method' => 'POST',
		]);
	}

	public function testShowPublishScheduleUsesSourceSet(): void
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
		]);
		$this->login('admin');

		$tsumego = $context->tsumegos[0];
		$sandboxSetId = $tsumego['set-connections'][1]['set_id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		ClassRegistry::init('Schedule')->create();
		ClassRegistry::init('Schedule')->save([
			'tsumego_id' => $tsumego['id'],
			'set_id' => $targetSetId,
			'set_id_from' => $sandboxSetId,
			'date' => '2026-09-01',
			'published' => 0,
		]);

		$this->testAction('/users/showPublishSchedule');

		$p = $this->vars['p'];
		$this->assertCount(1, $p);
		$this->assertSame(1, (int) $p[0]['num']);
		$this->assertStringContainsString('sandbox set', $p[0]['set_title']);
		$this->assertStringNotContainsString('other set', $p[0]['set_title']);
	}

	public function testCancelScheduleRemovesPendingRow(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$schedule = [];
		$schedule['tsumego_id'] = $context->tsumegos[0]['id'];
		$schedule['set_id'] = $targetSetId;
		$schedule['set_id_from'] = $sandboxSetId;
		$schedule['date'] = '2026-09-01';
		$schedule['published'] = 0;
		ClassRegistry::init('Schedule')->create();
		ClassRegistry::init('Schedule')->save($schedule);
		$scheduleId = ClassRegistry::init('Schedule')->id;

		$this->testAction('/users/cancelSchedule/' . $scheduleId, ['method' => 'POST']);

		$this->assertSame(0, ClassRegistry::init('Schedule')->find('count', ['conditions' => ['id' => $scheduleId]]));
	}

	public function testCancelScheduleRejectsPublishedRow(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$schedule = [];
		$schedule['tsumego_id'] = $context->tsumegos[0]['id'];
		$schedule['set_id'] = $targetSetId;
		$schedule['set_id_from'] = $sandboxSetId;
		$schedule['date'] = '2026-09-01';
		$schedule['published'] = 1;
		ClassRegistry::init('Schedule')->create();
		ClassRegistry::init('Schedule')->save($schedule);
		$scheduleId = ClassRegistry::init('Schedule')->id;

		$this->expectException(BadRequestException::class);

		$this->testAction('/users/cancelSchedule/' . $scheduleId, ['method' => 'POST']);
	}
}
