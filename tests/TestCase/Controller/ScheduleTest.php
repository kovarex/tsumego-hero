<?php

use App\Model\Entity\Schedule;
use App\Utility\CookieFlash;
use App\Utility\SchedulePublisher;

class ScheduleTest extends IntegrationTestCase
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

	//The schedule entries, ordered by id or by the given column and then id.
	/** @return Schedule[] */
	private function schedules(string $order = 'id'): array
	{
		return $this->fetchTable('Schedule')->find()->orderBy([$order, 'id'])->toArray();
	}

	private function lastSchedule(): Schedule
	{
		return $this->fetchTable('Schedule')->find()->orderByDesc('id')->firstOrFail();
	}

	private static function futureDate(int $offset = 2): string
	{
		return date('Y-m-d', strtotime("+{$offset} days"));
	}

	public function testAddToScheduleCreatesRows(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 2,
			'per_day' => 1,
			'start_date' => self::futureDate(),
		]);

		$schedules = $this->schedules('date');
		$this->assertCount(2, $schedules);

		[$first, $second] = $schedules;
		$this->assertSame($context->tsumegos[0]['id'], $first->tsumego_id);
		$this->assertSame($context->tsumegos[1]['id'], $second->tsumego_id);
		$this->assertSame($targetSetId, $first->set_id);
		$this->assertSame($targetSetId, $second->set_id);
		$this->assertSame(self::futureDate(), $first->date->format('Y-m-d'));
		$this->assertSame(self::futureDate(3), $second->date->format('Y-m-d'));
		$this->assertFalse($first->published);
	}

	//An empty count asks for every problem left in the source set, without a cap.
	public function testAddToScheduleWithoutCountTakesAllProblems(): void
	{
		[, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget(range(1, 120));

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => '',
			'per_day' => 50,
			'start_date' => self::futureDate(),
		]);

		$this->assertSame(120, $this->fetchTable('Schedule')->find()->count());
	}

	public function testAddToScheduleSkipsAlreadyScheduledProblems(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => self::futureDate()]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'start_date' => self::futureDate(3)]);

		$schedules = $this->schedules('date');
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[0]['id'], $schedules[0]->tsumego_id);
		$this->assertSame($context->tsumegos[1]['id'], $schedules[1]->tsumego_id);
	}

	public function testAddToScheduleByNum(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'num' => 3,
			'start_date' => self::futureDate(),
		]);

		$schedules = $this->schedules();
		$this->assertCount(1, $schedules);
		$this->assertSame($context->tsumegos[2]['id'], $schedules[0]->tsumego_id);
		$this->assertSame(self::futureDate(), $schedules[0]->date->format('Y-m-d'));
	}

	public function testAddToScheduleStartsFromNum(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3, 4]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'num' => 2,
			'count' => 2,
			'start_date' => self::futureDate(),
		]);

		$schedules = $this->schedules('date');
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[1]['id'], $schedules[0]->tsumego_id);
		$this->assertSame($context->tsumegos[2]['id'], $schedules[1]->tsumego_id);
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

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 3,
			'start_date' => self::futureDate(),
		]);

		$schedules = $this->schedules('date');
		$this->assertCount(2, $schedules);
		$this->assertSame($context->tsumegos[0]['id'], $schedules[0]->tsumego_id);
		$this->assertSame($context->tsumegos[2]['id'], $schedules[1]->tsumego_id);
	}

	public function testAddToScheduleRequiresAdmin(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'public set', 'num' => 1]]]],
		]);
		$this->login('alice');

		$this->post('/schedule/add', ['set_id_from' => 1, 'set_id_to' => 2, 'count' => 1, 'start_date' => self::futureDate()]);

		$this->assertResponseCode(403);
	}

	public function testAddToScheduleRejectsPastStartDate(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => '2020-01-01']);

		$this->assertResponseCode(400);
	}

	public function testScheduleIndexShowsSandboxSourceWhenNotYetInTarget(): void
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
				['tsumego' => 0, 'set' => 'target set', 'date' => self::futureDate()],
			],
		]);
		$this->login('admin');

		$this->get('/schedule');

		$p = $this->viewVariable('p');
		$this->assertCount(1, $p);
		$this->assertSame(1, (int) $p[0]['num']);
		$this->assertStringContainsString('sandbox set', $p[0]['sandbox_set_title']);
		$this->assertStringNotContainsString('other set', $p[0]['sandbox_set_title']);
	}

	public function testScheduleIndexShowsTargetSetWhenAlreadyThere(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'target set', 'num' => 436]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => self::futureDate()],
			],
		]);
		$this->login('admin');

		$this->get('/schedule');

		$p = $this->viewVariable('p');
		$this->assertCount(1, $p);
		$this->assertSame(436, (int) $p[0]['num']);
		$this->assertStringContainsString('target set', $p[0]['target_set_title']);
	}

	//An entry whose problem is no longer in a sandbox set and is not in the target set
	//cannot be published, so the page says so instead of leaving a bare number there.
	public function testScheduleIndexMarksAnEntryThatCannotBePublished(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'some public set', 'num' => 1]]],
				['sets' => [['name' => 'sandbox set', 'num' => 2, 'public' => 0]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => self::futureDate()],
				['tsumego' => 1, 'set' => 'target set', 'date' => self::futureDate()],
			],
		]);
		$this->login('admin');

		$this->get('/schedule');
		$result = $this->view;

		$this->assertSame(1, substr_count($result, 'cannot be published'));
	}

	//The preview answers the question the admin is asking: which problem goes live on which day.
	public function testPreviewShowsTheDayEachProblemPublishes(): void
	{
		[, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3]);

		$this->get('/schedule/preview?' . http_build_query([
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 3,
			'per_day' => 2,
			'start_date' => self::futureDate(),
		]));

		$body = json_decode($this->view, true);
		$this->assertSame(self::futureDate(), $body[0]['date']);
		$this->assertSame(self::futureDate(), $body[1]['date']);
		$this->assertSame(self::futureDate(3), $body[2]['date']);
	}

	//Asking for problems when the set has none left says so, instead of reporting that zero problems were scheduled.
	public function testAddToScheduleSaysWhenNothingIsLeft(): void
	{
		[, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$data = [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 1,
			'start_date' => self::futureDate(),
		];
		$this->post('/schedule/add', $data);
		$this->post('/schedule/add', $data);

		$this->assertStringContainsString('already published or already scheduled', (string) CookieFlash::get());
	}

	public function testAddToScheduleLogsAdminActivity(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => self::futureDate()]);

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
				['tsumego' => 0, 'set' => 'target set', 'date' => self::futureDate()],
			],
		]);
		$this->login('admin');

		$scheduleId = $this->lastSchedule()->id;
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		$this->post('/schedule/cancel/' . $scheduleId);

		$this->assertNull($this->fetchTable('Schedule')->find()->where(['id' => $scheduleId])->first());

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['order' => 'id DESC']);
		$this->assertSame((int) $context->user['id'], (int) $activity['AdminActivity']['user_id']);
		$this->assertSame(AdminActivityType::CANCEL_SCHEDULE, (int) $activity['AdminActivity']['type']);
		$this->assertSame($targetSetId, (int) $activity['AdminActivity']['set_id']);
	}

	public function testCanceledRowCanBeRescheduled(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => self::futureDate()]);
		$scheduleId = $this->lastSchedule()->id;

		$this->post('/schedule/cancel/' . $scheduleId);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 1, 'start_date' => self::futureDate()]);

		$this->assertSame(1, $this->fetchTable('Schedule')->find()->count());
	}

	public function testCanceledRowIsRemovedFromUpcoming(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2]);

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'start_date' => self::futureDate()]);

		$this->post('/schedule/cancel/' . $this->schedules()[0]->id);

		$this->get('/schedule');

		$this->assertCount(1, $this->viewVariable('p'));
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
				['tsumego' => 0, 'set' => 'target set', 'date' => self::futureDate(), 'published' => 1],
			],
		]);
		$this->login('admin');

		$scheduleId = $this->lastSchedule()->id;

		$this->post('/schedule/cancel/' . $scheduleId);

		$this->assertResponseCode(400);
	}

	public function testPublishSkipsAlreadyPublishedTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]], 'status' => 'S'],
				['sets' => [['name' => 'set 1', 'num' => 3]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'set 1', 'date' => date('Y-m-d'), 'published' => 1],
			],
		]);
		$this->login('admin');

		SchedulePublisher::publishDue();

		$this->assertSame(1, ClassRegistry::init('TsumegoStatus')->find('count', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
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

		SchedulePublisher::publishDue();

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

		SchedulePublisher::publishDue();

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

		$this->assertTrue($this->fetchTable('Schedule')->find()->where(['tsumego_id' => $tsumego['id']])->firstOrFail()->published);
	}

	public function testPublishPreservesStatsWhenAlreadyPublic(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [
					['name' => 'sandbox set', 'num' => 1, 'public' => 0],
					['name' => 'existing public', 'num' => 1],
				], 'status' => 'S'],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		SchedulePublisher::publishDue();

		// The statuses stay because the problem is already in another public set
		$this->assertSame(1, ClassRegistry::init('TsumegoStatus')->find('count', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
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

		SchedulePublisher::publishDue();

		// The tsumego has no sandbox source to move from, so the entry stays pending
		$this->assertFalse($this->fetchTable('Schedule')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->firstOrFail()->published);
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

		SchedulePublisher::publishDue();

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

		$this->get('/schedule/preview?' . http_build_query(['set_id_from' => $sandboxSetId, 'set_id_to' => $targetSetId, 'count' => 2, 'num' => 0]));

		$body = json_decode($this->view, true);
		$this->assertCount(2, $body);
		$this->assertTrue($body[0]['num_collision'], 'num 5 collides with the target set');
		$this->assertFalse($body[1]['num_collision'], 'num 6 is free in the target set');
	}

	public function testAddToScheduleGroupsProblemsPerDay(): void
	{
		[, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3, 4, 5, 6, 7]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 7,
			'per_day' => 5,
			'start_date' => self::futureDate(),
		]);

		$dates = array_map(fn(Schedule $schedule) => $schedule->date->format('Y-m-d'), $this->schedules());
		$perDate = array_count_values($dates);
		$this->assertSame(5, $perDate[self::futureDate()]);
		$this->assertSame(2, $perDate[self::futureDate(3)]);
	}

	public function testAddToSchedulePublishesFivePerDayByDefault(): void
	{
		[, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1, 2, 3, 4, 5, 6]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'count' => 6,
			'start_date' => self::futureDate(),
		]);

		$dates = array_map(fn(Schedule $schedule) => $schedule->date->format('Y-m-d'), $this->schedules());
		$perDate = array_count_values($dates);
		$this->assertSame(5, $perDate[self::futureDate()]);
		$this->assertSame(1, $perDate[self::futureDate(3)]);
	}

	public function testAddToScheduleStartingTodayPublishesRightAway(): void
	{
		[$context, $sandboxSetId, $targetSetId] = $this->_sandboxAndTarget([1]);

		$this->post('/schedule/add', [
			'set_id_from' => $sandboxSetId,
			'set_id_to' => $targetSetId,
			'start_date' => date('Y-m-d'),
		]);

		$this->assertTrue($this->lastSchedule()->published);
		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $context->tsumegos[0]['id']],
		]));
	}

	public function testPublishingTwiceKeepsThePublishResult(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		$tsumegoId = $context->tsumegos[0]['id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		SchedulePublisher::publishDue();

		$moved = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumegoId],
		]);
		ClassRegistry::init('TsumegoStatus')->create();
		ClassRegistry::init('TsumegoStatus')->save(['TsumegoStatus' => [
			'user_id' => $context->user['id'],
			'tsumego_id' => $tsumegoId,
			'status' => 'S',
		]]);

		SchedulePublisher::publishDue();

		$movedAgain = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumegoId],
		]);
		$this->assertSame((int) $moved['SetConnection']['num'], (int) $movedAgain['SetConnection']['num']);
		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumegoId],
		]));
		$this->assertSame(1, ClassRegistry::init('TsumegoStatus')->find('count', [
			'conditions' => ['tsumego_id' => $tsumegoId],
		]));
	}

	//Publishing moves the problem, so two entries for the same problem both end up
	//resolved with a single connection in the target set and none left in the sandbox.
	public function testPublishingTheSameProblemTwiceKeepsOneConnection(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d', strtotime('-1 day'))],
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		$tsumegoId = $context->tsumegos[0]['id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];
		$sandboxSetId = $context->tsumegos[0]['set-connections'][0]['set_id'];

		SchedulePublisher::publishDue();

		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumegoId],
		]));
		$this->assertSame(0, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $sandboxSetId, 'tsumego_id' => $tsumegoId],
		]));
	}

	public function testPublishCatchesUpAnEntryOfAMissedDay(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d', strtotime('-3 days'))],
			],
		]);
		$this->login('admin');

		$tsumegoId = $context->tsumegos[0]['id'];
		$targetSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		SchedulePublisher::publishDue();

		$this->assertTrue($this->lastSchedule()->published);
		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['set_id' => $targetSetId, 'tsumego_id' => $tsumegoId],
		]));
	}

	public function testScheduleIndexListsRecentlyPublished(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'target set', 'num' => 1]]],
				['sets' => [['name' => 'target set', 'num' => 2]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d'), 'published' => 1],
				['tsumego' => 1, 'set' => 'target set', 'date' => date('Y-m-d', strtotime('-30 days')), 'published' => 1],
			],
		]);
		$this->login('admin');

		$this->get('/schedule');

		$recentlyPublished = $this->viewVariable('recentlyPublished');
		$this->assertCount(1, $recentlyPublished);
		$this->assertSame($context->tsumegos[0]['id'], (int) $recentlyPublished[0]['tsumego_id']);
		$this->assertSame(1, (int) $recentlyPublished[0]['num']);
		$this->assertEmpty($this->viewVariable('p'));
	}

	//A collection a player shared is public too, but it is not the site's, so the schedule
	//neither offers it nor publishes into it.
	public function testTargetMustBeACollectionOfTheSite(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
				['sets' => [['name' => 'shared player collection', 'num' => 1, 'public' => 1, 'user_id' => 'self']]],
			],
		]);
		$this->login('admin');

		$sandboxSetId = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$playerSetId = $context->tsumegos[1]['set-connections'][0]['set_id'];

		$this->get('/schedule');
		$this->assertSame([], array_column(array_column($this->viewVariable('publicSets'), 'Set'), 'title'));

		$this->post('/schedule/add', ['set_id_from' => $sandboxSetId, 'set_id_to' => $playerSetId, 'count' => 1, 'start_date' => self::futureDate()]);

		$this->assertResponseCode(400);
	}

	public function testAddToScheduleSkipsProblemsInTheTrash(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]], 'deleted' => date('Y-m-d H:i:s')],
				['sets' => [['name' => 'sandbox set', 'num' => 2, 'public' => 0]]],
				['sets' => [['name' => 'public set', 'num' => 1]]],
			],
		]);
		$this->login('admin');

		$this->post('/schedule/add', [
			'set_id_from' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'set_id_to' => $context->tsumegos[2]['set-connections'][0]['set_id'],
			'count' => 2,
			'start_date' => self::futureDate(),
		]);

		$schedules = $this->schedules();
		$this->assertCount(1, $schedules);
		$this->assertSame($context->tsumegos[1]['id'], $schedules[0]->tsumego_id);
	}

	//A problem deleted after it was scheduled is not published from the trash: the entry
	//waits, and the schedule page says where the problem is.
	public function testPublishLeavesAProblemInTheTrashPending(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]], 'deleted' => date('Y-m-d H:i:s')],
				['sets' => [['name' => 'target set', 'num' => 1]]],
			],
			'schedule' => [
				['tsumego' => 0, 'set' => 'target set', 'date' => date('Y-m-d')],
			],
		]);
		$this->login('admin');

		SchedulePublisher::publishDue();

		$this->assertFalse($this->schedules()[0]->published);
		$this->assertSame(1, ClassRegistry::init('SetConnection')->find('count', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $context->tsumegos[0]['set-connections'][0]['set_id']],
		]));

		$this->get('/schedule');
		$result = $this->view;
		$this->assertStringContainsString('in the trash', $result);
	}
}
