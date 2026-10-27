<?php

use App\Utility\SchedulePublisher;
use App\Utility\SiteGrowth;
use App\Utility\Trash;
use App\Utility\Util;

class SiteGrowthTest extends IntegrationTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		// the cache is a file, it would carry the graph of the previous test over
		SiteGrowth::forget();
	}

	//The problem count of the last point of the cached graph, the one the home page shows now.
	private static function currentProblems(): ?int
	{
		$data = SiteGrowth::chartData();
		return end($data)['problems'];
	}

	private static function setCreated(array $tsumego, string $created): void
	{
		Util::execute('UPDATE tsumego SET created = ? WHERE id = ?', [$created, $tsumego['id']]);
	}

	//The problem line of the graph as month => count.
	/** @return array<string, int|null> */
	private static function problems(): array
	{
		return array_column(SiteGrowth::build(), 'problems', 'date');
	}

	public function testPublicProblemCountsFromItsCreation(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'public set', 'num' => 2]]],
		]]);
		self::setCreated($context->tsumegos[0], '2020-01-15 10:00:00');
		self::setCreated($context->tsumegos[1], '2020-03-02 10:00:00');

		$problems = self::problems();
		$this->assertSame(1, $problems['2020-01-01']);
		$this->assertSame(2, $problems['2020-03-01']);
		$this->assertArrayNotHasKey('2020-02-01', $problems);
	}

	public function testProblemInTwoPublicSetsCountsOnce(): void
	{
		$context = new ContextPreparator(['tsumego' => ['sets' => [
			['name' => 'public set', 'num' => 1],
			['name' => 'other public set', 'num' => 1],
		]]]);
		self::setCreated($context->tsumegos[0], '2020-01-15 10:00:00');

		$this->assertSame(1, self::problems()['2020-01-01']);
	}

	public function testSandboxAndPlayerCollectionProblemsDontCount(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
			['sets' => [['name' => 'my collection', 'num' => 1, 'user_id' => 'self']]],
		]]);
		foreach ($context->tsumegos as $tsumego)
			self::setCreated($tsumego, '2020-01-15 10:00:00');

		$this->assertSame(1, self::problems()['2020-01-01']);
	}

	//A problem made in the sandbox in January and published in May counts from May.
	public function testPublishedProblemCountsFromItsPublication(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => 1]]],
				['sets' => [['name' => 'public set', 'num' => 2]]],
			],
			'schedule' => [['tsumego' => 1, 'set' => 'public set', 'date' => '2020-05-20', 'published' => 1]]]);
		self::setCreated($context->tsumegos[0], '2020-01-15 10:00:00');
		self::setCreated($context->tsumegos[1], '2020-01-16 10:00:00');

		$problems = self::problems();
		$this->assertSame(1, $problems['2020-01-01']);
		$this->assertSame(2, $problems['2020-05-01']);
	}

	//What is still waiting in the schedule is in the sandbox, so it doesn't count yet.
	public function testScheduledProblemCountsOnceItIsPublished(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => 1]]],
				['sets' => [['name' => 'sandbox set', 'num' => 1, 'public' => 0]]],
			],
			'schedule' => [['tsumego' => 1, 'set' => 'public set', 'date' => date('Y-m-d'), 'published' => 0]]]);
		self::setCreated($context->tsumegos[0], '2020-01-15 10:00:00');
		self::setCreated($context->tsumegos[1], '2020-01-16 10:00:00');
		$this->assertSame(1, SiteGrowth::chartData()[0]['problems']);

		SchedulePublisher::publishDue();

		$data = SiteGrowth::chartData();
		$this->assertSame(1, $data[0]['problems']);
		$this->assertSame(2, end($data)['problems'], 'the cached graph is dropped on publishing');
		$this->assertSame(date('Y-m-01'), end($data)['date']);
	}

	public function testDeletedProblemCountsUntilItsDeletion(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'public set', 'num' => 2]], 'deleted' => '2020-04-03 10:00:00'],
		]]);
		self::setCreated($context->tsumegos[0], '2020-01-15 10:00:00');
		self::setCreated($context->tsumegos[1], '2020-01-16 10:00:00');

		$problems = self::problems();
		$this->assertSame(2, $problems['2020-01-01']);
		$this->assertSame(1, $problems['2020-04-01']);
	}

	//The problems of a deleted set stop counting, except those that are in another public set too.
	public function testDeletedSetTakesItsProblemsOffTheGraph(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'deleted set', 'num' => 1, 'deleted' => '2020-06-07 10:00:00']]],
			['sets' => [['name' => 'deleted set', 'num' => 2], ['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'public set', 'num' => 2]]],
		]]);
		foreach ($context->tsumegos as $tsumego)
			self::setCreated($tsumego, '2020-01-15 10:00:00');

		$problems = self::problems();
		$this->assertSame(3, $problems['2020-01-01']);
		$this->assertSame(2, $problems['2020-06-01']);
	}

	public function testDeletingSetDropsTheCachedGraph(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'other public set', 'num' => 1]]],
		]]);
		foreach ($context->tsumegos as $tsumego)
			self::setCreated($tsumego, '2020-01-15 10:00:00');
		$this->assertSame(2, self::currentProblems());

		Trash::deleteSet((int) $context->tsumegos[0]['sets'][0]['id']);
		$data = SiteGrowth::chartData();
		$this->assertSame(1, end($data)['problems']);
		$this->assertSame(date('Y-m-01'), end($data)['date']);

		Trash::restoreSet((int) $context->tsumegos[0]['sets'][0]['id']);
		$this->assertSame(2, self::currentProblems());
	}

	public function testDeletingProblemDropsTheCachedGraph(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'public set', 'num' => 1]]],
			['sets' => [['name' => 'public set', 'num' => 2]]],
		]]);
		foreach ($context->tsumegos as $tsumego)
			self::setCreated($tsumego, '2020-01-15 10:00:00');
		$this->assertSame(2, self::currentProblems());

		Trash::deleteProblem((int) $context->tsumegos[0]['id']);
		$this->assertSame(1, self::currentProblems());

		Trash::restoreProblem((int) $context->tsumegos[0]['id']);
		$this->assertSame(2, self::currentProblems());
	}

	public function testUsersCountByRegistration(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'first'], 'other-users' => [['name' => 'second']]]);
		Util::execute('UPDATE user SET created = ? WHERE id = ?', ['2020-02-10 10:00:00', $context->user['id']]);
		Util::execute('UPDATE user SET created = ? WHERE id = ?', ['2020-07-10 10:00:00', $context->otherUsers[0]['id']]);

		$users = array_column(SiteGrowth::build(), 'users', 'date');
		$this->assertSame(['2020-02-01' => 1, '2020-07-01' => 2], $users);
	}
}
