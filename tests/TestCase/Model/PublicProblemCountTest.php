<?php

use App\Model\Table\TsumegoTable;
use App\Utility\Trash;
use Cake\Cache\Cache;

//The count of the problems of the official collections is kept between requests (the profile
//shows it to every player), and every change of the site that changes it drops what was kept.
class PublicProblemCountTest extends IntegrationTestCase
{
	//the tests run without the cache (core.local.php), this one is about it
	public function setUp(): void
	{
		parent::setUp();
		Cache::enable();
	}

	public function tearDown(): void
	{
		TsumegoTable::forgetPublicProblemCount();
		Cache::disable();
		parent::tearDown();
	}

	private function total(int $userID): int
	{
		return TsumegoTable::publicProblemProgress($userID)['total'];
	}

	public function testTheKeptCountFollowsTheProblemsOfTheOfficialCollections(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'Site collection', 'num' => 1]]],
				['sets' => [['name' => 'Site collection', 'num' => 2]]]]]);
		$userID = $context->user['id'];
		$setID = $context->tsumegos[0]['set-connections'][0]['set_id'];
		$tsumegoTable = $this->fetchTable('Tsumego');
		$newProblemID = $tsumegoTable->saveOrFail($tsumegoTable->newEntity(['author' => 'a']))->id;
		$this->assertSame(2, $this->total($userID));

		$this->post('/sets/addTsumego/' . $setID, ['tsumego_id' => $newProblemID]);
		$this->assertSame(3, $this->total($userID), 'added to an official collection');

		$this->post('/sets/removeTsumego/' . $setID, ['tsumego_id' => $newProblemID]);
		$this->assertSame(2, $this->total($userID), 'removed from it');

		Trash::deleteProblem($context->tsumegos[1]['id']);
		$this->assertSame(1, $this->total($userID), 'in the trash');

		Trash::restoreProblem($context->tsumegos[1]['id']);
		$this->assertSame(2, $this->total($userID), 'restored');
	}

	public function testAMergeOfTwoProblemsLeavesOne(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'Site collection', 'num' => 1]]],
				['sets' => [['name' => 'Other site collection', 'num' => 1]]]]]);
		$this->assertSame(2, $this->total($context->user['id']));

		$this->post('/tsumegos/performMerge', ['master-tsumego-id' => $context->tsumegos[0]['id'], 'slave-tsumego-id' => $context->tsumegos[1]['id']]);

		$this->assertSame(1, $this->total($context->user['id']));
	}
}
