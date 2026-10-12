<?php

use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\PlayOutcome;
use App\Utility\TsumegoUtil;
use App\Utility\TsumegoXPAndRating;

class ProgressDeletionTest extends TestCaseWithAuth
{
	private int $originalPartitionSize;

	public function setUp(): void
	{
		parent::setUp();
		// small parts, so the tests don't need hundreds of problems (10 is the smallest collection size)
		$this->originalPartitionSize = ProgressDeletion::$PARTITION_SIZE;
		ProgressDeletion::$PARTITION_SIZE = 10;
	}

	public function tearDown(): void
	{
		ProgressDeletion::$PARTITION_SIZE = $this->originalPartitionSize;
		parent::tearDown();
	}

	// problems numbered 1 to $count in the set, solved
	private static function setProblems(string $setName, int $count): array
	{
		$problems = [];
		for ($num = 1; $num <= $count; $num++)
			$problems[] = ['sets' => [['name' => $setName, 'num' => $num]], 'status' => 'S'];
		return $problems;
	}

	private static function now(): string
	{
		return date('Y-m-d H:i:s');
	}

	private function solve(int $tsumegoID, int $setConnectionID): array
	{
		return $this->postOutcome($tsumegoID, $setConnectionID, PlayOutcome::SolvedClean);
	}

	private function resetPart(int $setID, int $part): void
	{
		$this->testAction('/sets/resetProgress/' . $setID . '/' . $part, ['method' => 'POST', 'data' => ['reset-check' => 'reset']]);
	}

	public function testPartitionOfLeavesOutDeletedProblems(): void
	{
		$problems = self::setProblems('big set', 12);
		$problems[2]['deleted'] = self::now();
		$context = new ContextPreparator(['tsumegos' => $problems]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		// without the deleted #3, #11 is the 10th problem
		$this->assertSame(['set_id' => $setID, 'partition' => 0], ProgressDeletion::partitionOf($context->tsumegos[10]['set-connections'][0]['id']));
		$this->assertSame(['set_id' => $setID, 'partition' => 1], ProgressDeletion::partitionOf($context->tsumegos[11]['set-connections'][0]['id']));
		// not the set connection of the given problem
		$this->assertNull(ProgressDeletion::partitionOf($context->tsumegos[11]['set-connections'][0]['id'], $context->tsumegos[0]['id']));
	}

	public function testCountsTheResetsOfThePartInTheLastMonth(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'other']],
			'tsumegos' => self::setProblems('big set', 1),
			'progress-deletions' => [
				['set' => 'big set', 'partition' => 0, 'created' => self::now()],
				['set' => 'big set', 'partition' => 1, 'created' => self::now()],
				['set' => 'big set', 'partition' => 1, 'created' => self::now()],
				['set' => 'big set', 'partition' => 0, 'created' => date('Y-m-d H:i:s', strtotime('-2 months'))],
				['set' => 'big set', 'partition' => 0, 'created' => self::now(), 'user' => 'other']]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->assertSame(1, ProgressDeletion::countRecent($context->user['id'], $setID, 0));
		$this->assertSame(2, ProgressDeletion::countRecent($context->user['id'], $setID, 1));
		$this->assertSame(0, ProgressDeletion::countRecent($context->user['id'], $setID, 2));
	}

	public function testOldResetsWithoutPartCountForTheWholeSet(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => self::setProblems('big set', 1),
			'progress-deletions' => [['set' => 'big set', 'created' => self::now()]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->assertSame(1, ProgressDeletion::countRecent($context->user['id'], $setID, 0));
		$this->assertSame(1, ProgressDeletion::countRecent($context->user['id'], $setID, 1));
	}

	// Gokyo Shumyo IV #2 got 1/100: it is also in Tsumego Master, and both sets have two parts.
	public function testXpCountsOnlyTheResetsOfThePartItIsPlayedFrom(): void
	{
		$progressDeletions = [
			['set' => 'set A', 'partition' => 0, 'created' => self::now()],
			['set' => 'set A', 'partition' => 1, 'created' => self::now()],
			['set' => 'set B', 'partition' => 0, 'created' => self::now()],
			['set' => 'set B', 'partition' => 0, 'created' => self::now()],
			['set' => 'set B', 'partition' => 1, 'created' => self::now()]];
		$problem = ['rating' => 1000, 'sets' => [['name' => 'set A', 'num' => 1], ['name' => 'set B', 'num' => 1]]];

		// from set A: its part 0 was reset once
		$context = new ContextPreparator(['user' => ['rating' => 1000], 'tsumego' => $problem, 'progress-deletions' => $progressDeletions]);
		$tsumego = $context->tsumegos[0];
		$response = $this->solve($tsumego['id'], $tsumego['set-connections'][0]['id']);
		$this->assertSame(TsumegoUtil::getXpValue($tsumego, TsumegoXPAndRating::getProgressDeletionMultiplier(1)), (int) $response['xp_gained']);

		// from set B: its part 0 was reset twice
		$context = new ContextPreparator(['user' => ['rating' => 1000], 'tsumego' => $problem, 'progress-deletions' => $progressDeletions]);
		$tsumego = $context->tsumegos[0];
		$response = $this->solve($tsumego['id'], $tsumego['set-connections'][1]['id']);
		$this->assertSame(TsumegoUtil::getXpValue($tsumego, TsumegoXPAndRating::getProgressDeletionMultiplier(2)), (int) $response['xp_gained']);
	}

	private function postOutcome(int $tsumegoID, int $setConnectionID, PlayOutcome $outcome): array
	{
		$this->testAction('/tsumegos/result', ['method' => 'POST', 'data' => [
			'tsumego_id' => $tsumegoID,
			'seconds' => 1,
			'outcome' => $outcome->value,
			'set_connection_id' => $setConnectionID]]);
		return json_decode($this->controller->response->body(), true);
	}

	private function ratingsOf(ContextPreparator $context): array
	{
		return [
			'user' => (float) ClassRegistry::init('User')->findById($context->user['id'])['User']['rating'],
			'tsumego' => (float) ClassRegistry::init('Tsumego')->findById($context->tsumegos[0]['id'])['Tsumego']['rating']];
	}

	public function testRatingsDontChangeOnProblemOfRecentlyResetPart(): void
	{
		foreach ([PlayOutcome::SolvedClean, PlayOutcome::Failed] as $outcome)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set A', 'num' => 1]]],
				'progress-deletions' => [['set' => 'set A', 'partition' => 0, 'created' => self::now()]]]);
			$ratingsBefore = $this->ratingsOf($context);

			$response = $this->postOutcome($context->tsumegos[0]['id'], $context->tsumegos[0]['set-connections'][0]['id'], $outcome);
			$this->assertSame($ratingsBefore, $this->ratingsOf($context), $outcome->value);
			if ($outcome === PlayOutcome::SolvedClean)
				$this->assertSame(TsumegoUtil::getXpValue($context->tsumegos[0], TsumegoXPAndRating::getProgressDeletionMultiplier(1)), (int) $response['xp_gained'], 'XP is still gained, reduced');
		}
	}

	public function testRatingsChangeWhenPlayedFromPartThatWasntReset(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set A', 'num' => 1], ['name' => 'set B', 'num' => 1]]],
			'progress-deletions' => [
				['set' => 'set A', 'partition' => 0, 'created' => self::now()],
				['set' => 'set B', 'partition' => 1, 'created' => self::now()]]]);
		$ratingsBefore = $this->ratingsOf($context);

		$this->solve($context->tsumegos[0]['id'], $context->tsumegos[0]['set-connections'][1]['id']);
		$ratingsAfter = $this->ratingsOf($context);
		$this->assertGreaterThan($ratingsBefore['user'], $ratingsAfter['user']);
		$this->assertLessThan($ratingsBefore['tsumego'], $ratingsAfter['tsumego']);
	}

	public function testPlayPageShowsRatingUnaffectedAfterReset(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'set A', 'num' => 1]]],
			'progress-deletions' => [['set' => 'set A', 'partition' => 0, 'created' => self::now()]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertTextContains('unaffected (reset)', $browser->driver->findElement(Facebook\WebDriver\WebDriverBy::cssSelector('#xpDisplay'))->getText());
	}

	public function testXpShownOnThePlayPageCountsTheResetsOfThePart(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'set A', 'num' => 1], ['name' => 'set B', 'num' => 1]]],
			'progress-deletions' => [
				['set' => 'set A', 'partition' => 0, 'created' => self::now()],
				['set' => 'set B', 'partition' => 0, 'created' => self::now()],
				['set' => 'set B', 'partition' => 0, 'created' => self::now()]]]);
		$tsumego = $context->tsumegos[0];

		$fromSetA = new TsumegoXPAndRating($tsumego, 'V', $tsumego['set-connections'][0]['id']);
		$fromSetB = new TsumegoXPAndRating($tsumego, 'V', $tsumego['set-connections'][1]['id']);
		$this->assertEquals(1, $fromSetA->progressDeletionCount);
		$this->assertEquals(2, $fromSetB->progressDeletionCount);
	}

	public function testResetClearsAndRecordsOnlyItsPart(): void
	{
		$context = new ContextPreparator(['user' => ['collection_size' => 10], 'tsumegos' => self::setProblems('big set', 12)]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		$this->resetPart($setID, 2);

		$statuses = ClassRegistry::init('TsumegoStatus')->find('all', ['conditions' => ['user_id' => Auth::getUserID()]]);
		$this->assertCount(10, $statuses, 'Only #11 and #12 (the second part) are reset');
		$progressDeletion = ClassRegistry::init('ProgressDeletion')->find('first');
		$this->assertSame($setID, $progressDeletion['ProgressDeletion']['set_id']);
		$this->assertSame(1, $progressDeletion['ProgressDeletion']['set_partition']);
	}

	public function testResetIsRefusedWithRankOrTagFilters(): void
	{
		foreach ([['filtered_ranks' => ['15k']], ['filtered_tags' => ['ko']]] as $filter)
		{
			$context = new ContextPreparator([
				'user' => ['collection_size' => 10] + $filter,
				'tsumegos' => self::setProblems('big set', 12),
				'tags' => [['name' => 'ko']]]);
			$this->resetPart($context->tsumegos[0]['sets'][0]['id'], 1);

			$this->assertSame('Reset is only possible without rank and tag filters', CookieFlash::get(), json_encode($filter));
			$this->assertCount(12, ClassRegistry::init('TsumegoStatus')->find('all'), json_encode($filter));
			$this->assertEmpty(ClassRegistry::init('ProgressDeletion')->find('all'), json_encode($filter));
		}
	}

	public function testSetPageShowsTheResetsOfThePart(): void
	{
		$context = new ContextPreparator([
			'user' => ['collection_size' => 10],
			'tsumegos' => self::setProblems('big set', 12),
			'progress-deletions' => [['set' => 'big set', 'partition' => 1, 'created' => self::now()]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->assertStringContainsString('XP reduced by 50%', $this->testAction('/sets/view/' . $setID . '/2', ['return' => 'view']));
		$this->assertStringNotContainsString('XP reduced by', $this->testAction('/sets/view/' . $setID . '/1', ['return' => 'view']));
	}
}
