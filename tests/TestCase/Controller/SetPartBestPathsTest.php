<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//The typical ways a player's best accuracy and best time of a part of a collection come about, played through the
//site: the results the play page reports, the reset of a part, the set page and the collections page. The parts are of
//ten problems here (ProgressDeletionTable::$PARTITION_SIZE, 200 on the site), the smallest collection size, which the
//player picks, so that a page is a part.
class SetPartBestPathsTest extends IntegrationTestCase
{
	private const PART = 10;

	public function setUp(): void
	{
		parent::setUp();
		ProgressDeletionTable::$PARTITION_SIZE = self::PART;
	}

	protected function tearDown(): void
	{
		ProgressDeletionTable::$PARTITION_SIZE = 200;
		parent::tearDown();
	}

	//A collection of $count problems not played yet, in the order of their index; $more adds to a problem
	private static function collection(int $count, array $more = [], string $name = 'paths set', array $options = []): ContextPreparator
	{
		$tsumegos = [];
		for ($i = 0; $i < $count; $i++)
			$tsumegos[] = array_replace_recursive(['sets' => [['name' => $name, 'num' => $i + 1]]], $more[$i] ?? []);
		return new ContextPreparator($options + ['user' => ['name' => 'player', 'collection_size' => self::PART], 'tsumegos' => $tsumegos]);
	}

	//Reports the result of the problem as the play page does
	private function play(ContextPreparator $context, int $index, PlayOutcome $outcome, int $seconds = 5, int $set = 0): void
	{
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[$index]['id'],
			'set_connection_id' => $context->tsumegos[$index]['set-connections'][$set]['id'],
			'seconds' => $seconds,
			'outcome' => $outcome->value]);
		$this->assertResponseOk();
		Auth::init();
	}

	//Solves the problems from $from to $to (indexes) cleanly
	private function solveCleanly(ContextPreparator $context, int $from, int $to, int $seconds = 5): void
	{
		for ($i = $from; $i <= $to; $i++)
			$this->play($context, $i, PlayOutcome::SolvedClean, $seconds);
	}

	//A miss reported, then the solve after it, the way the play page reports a problem solved at the second try
	private function solveAfterAMiss(ContextPreparator $context, int $index, int $seconds = 5): void
	{
		$this->play($context, $index, PlayOutcome::Failed, $seconds);
		$this->play($context, $index, PlayOutcome::SolvedAfterMistake, $seconds);
	}

	private function reset(ContextPreparator $context, int $page): void
	{
		$this->post('/sets/resetProgress/' . self::setID($context) . '/' . $page, ['reset-check' => 'reset']);
		$this->assertRedirect();
	}

	//An hour passes: what was played and reset before is older than what comes after, whatever the clock's second
	private static function anHourPasses(): void
	{
		Util::execute('UPDATE tsumego_attempt SET created = created - INTERVAL 1 HOUR');
		Util::execute('UPDATE progress_deletion SET created = created - INTERVAL 1 HOUR');
	}

	private static function setID(ContextPreparator $context, int $index = 0, int $set = 0): int
	{
		return (int) $context->tsumegos[$index]['sets'][$set]['id'];
	}

	private static function accuracy(ContextPreparator $context, int $part, int $set = 0): ?float
	{
		return AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, self::setID($context, 0, $set), $part);
	}

	private static function time(ContextPreparator $context, int $part): ?float
	{
		return AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_TIME, self::setID($context), $part);
	}

	private static function storedBests(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS c FROM achievement_condition WHERE achievement_condition_category_id IN (?, ?)',
			[AchievementConditionTable::CATEGORY_SET_ACCURACY, AchievementConditionTable::CATEGORY_SET_TIME])[0]['c'];
	}

	//Whether each card of the collections page has the mastered frame
	/** @return bool[] */
	private function frames(): array
	{
		$this->get('sets');
		$this->assertResponseOk();
		return array_map(fn($card) => str_contains($card->getAttribute('class'), 'set-card--perfect'),
			iterator_to_array($this->getStringDom()->querySelectorAll('.set-card')));
	}

	//Path 1
	public function testSolvingEveryProblemOfAPartCleanlyKeepsItsBestAndFramesItsCard(): void
	{
		$context = self::collection(20);

		$this->solveCleanly($context, 0, 9, 7);

		$this->assertEquals(100, self::accuracy($context, 0));
		$this->assertEquals(7, self::time($context, 0));
		$this->assertNull(self::accuracy($context, 1));
		$this->assertSame([true, false], $this->frames());
	}

	//Path 2
	public function testAMissThenTheSolveOfTheSameProblemIsBelow100(): void
	{
		$context = self::collection(10);

		$this->solveCleanly($context, 0, 8);
		$this->solveAfterAMiss($context, 9);

		$this->assertSame(1, (int) Util::query('SELECT COUNT(*) AS c FROM tsumego_attempt WHERE tsumego_id = ?', [$context->tsumegos[9]['id']])[0]['c'],
			'one attempt, with its misplay');
		$this->assertEquals(90.91, self::accuracy($context, 0), '10 solves against 1 misplay');
		$this->assertSame([false], $this->frames());
	}

	//Path 3: a failed attempt, the problem left, and solved later in an attempt of its own
	public function testAFailedAttemptThenALaterSolveIsBelow100(): void
	{
		$context = self::collection(10, [9 => ['status' => 'F', 'attempts' => [['solved' => false, 'misplays' => 1, 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]]]]);
		Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, solved, seconds, misplays, gain, user_rating, tsumego_rating, created) VALUES (?, ?, 1, 9, 0, 0, 1000, 1000, ?)',
			[$context->user['id'], $context->tsumegos[9]['id'], date('Y-m-d H:i:s', strtotime('-1 hour'))]);
		Util::execute('UPDATE tsumego_status SET status = ? WHERE tsumego_id = ?', ['S', $context->tsumegos[9]['id']]);

		$this->solveCleanly($context, 0, 8);

		$this->assertEquals(90.91, self::accuracy($context, 0), '10 solves against the failed attempt');
	}

	//Path 4
	public function testAPartNotSolvedWholeKeepsNothing(): void
	{
		$context = self::collection(10);

		$this->solveCleanly($context, 0, 8);
		$this->play($context, 9, PlayOutcome::Failed);
		$this->get('sets/' . self::setID($context));

		$this->assertSame(0, self::storedBests());
		$this->assertNull($this->viewVariable('bestAccuracy'));
		$this->assertSame([false], $this->frames());
	}

	//Path 5: the reset makes the attempts before it count no more; a worse run after another reset keeps the best
	public function testAResetStartsANewRunAndTheBestStays(): void
	{
		$context = self::collection(10);
		$this->solveCleanly($context, 0, 8, 20);
		$this->solveAfterAMiss($context, 9, 20);
		$this->assertEquals(90.91, self::accuracy($context, 0));
		self::anHourPasses();

		$this->reset($context, 1);
		$this->assertSame([false], $this->frames(), 'the part is not finished any more');
		$this->solveCleanly($context, 0, 9, 10);
		$this->assertEquals(100, self::accuracy($context, 0));
		$this->assertEquals(10, self::time($context, 0));
		$this->assertSame([true], $this->frames());
		self::anHourPasses();

		$this->reset($context, 1);
		$this->solveCleanly($context, 0, 8, 30);
		$this->solveAfterAMiss($context, 9, 30);
		$this->assertEquals(100, self::accuracy($context, 0), 'the higher accuracy stays');
		$this->assertEquals(10, self::time($context, 0), 'the lower time stays');
		$this->get('sets/' . self::setID($context));
		$this->assertEquals(100, $this->viewVariable('bestAccuracy'));
	}

	//Path 6: the reset of the second part leaves the first; a reset without a part (before the parts were recorded) is
	//a reset of every part
	public function testTheResetOfAPartLeavesTheOthers(): void
	{
		$context = self::collection(20);
		$this->solveCleanly($context, 0, 18);
		$this->solveAfterAMiss($context, 19);
		self::anHourPasses();

		$this->reset($context, 2);
		$this->solveCleanly($context, 10, 19);

		$this->assertEquals(100, self::accuracy($context, 0));
		$this->assertEquals(100, self::accuracy($context, 1), 'the miss was before the reset of the part');
		$this->assertSame([true, true], $this->frames());

	}

	//Path 6: a reset kept before the parts were recorded, without a part, is a reset of every part
	public function testAResetWithoutAPartIsOfEveryPart(): void
	{
		$old = date('Y-m-d H:i:s', strtotime('-2 days'));
		$context = self::collection(20, [
			0 => ['status' => 'S', 'attempts' => [['solved' => false, 'misplays' => 1, 'created' => $old]]],
			10 => ['status' => 'S', 'attempts' => [['solved' => false, 'misplays' => 1, 'created' => $old]]]],
			options: ['progress-deletions' => [['set' => 'paths set', 'created' => date('Y-m-d H:i:s', strtotime('-1 day'))]]]);

		$this->solveCleanly($context, 1, 9);
		$this->solveCleanly($context, 11, 19);

		$this->assertEquals(90, self::accuracy($context, 0), '9 solves, and the first problem solved without an attempt since the reset');
		$this->assertEquals(90, self::accuracy($context, 1), 'the same in the second part');
	}

	//Path 7: a collection of one part, and one whose last part is smaller (10, 10 and 3, like 200, 200 and 50)
	public function testTheLastSmallerPartAndACollectionOfOnePart(): void
	{
		$context = self::collection(23);
		$this->solveCleanly($context, 0, 22);
		$this->assertSame([100.0, 100.0, 100.0], [self::accuracy($context, 0), self::accuracy($context, 1), self::accuracy($context, 2)]);
		$this->assertSame([true, true, true], $this->frames());

		$small = self::collection(4, [], 'small set');
		$this->solveCleanly($small, 0, 3);
		$this->assertEquals(100, self::accuracy($small, 0));
		$this->assertSame([true], $this->frames());
	}

	//Path 8: the page at a collection size other than the parts' shows no best and offers no reset; at the parts' size
	//it shows the best of its own part and the resets of that part
	public function testThePageShowsTheBestsOfItsPartOnlyAtThePartSize(): void
	{
		$context = self::collection(20);
		$this->solveCleanly($context, 0, 9);
		$this->solveCleanly($context, 10, 18);
		$this->solveAfterAMiss($context, 19);
		self::anHourPasses();
		$this->reset($context, 2);

		$this->get('sets/' . self::setID($context) . '/2');
		$this->assertEquals(90.91, $this->viewVariable('bestAccuracy'), 'the best of the second part stays after its reset');
		$this->assertSame(1, $this->viewVariable('pdCounter'));
		$this->get('sets/' . self::setID($context));
		$this->assertEquals(100, $this->viewVariable('bestAccuracy'));
		$this->assertSame(0, $this->viewVariable('pdCounter'), 'the reset was of the other part');
		$this->assertTextContains('Best: 100%', $this->view);

		Util::execute('UPDATE user SET collection_size = 20 WHERE id = ?', [$context->user['id']]);
		$this->get('sets/' . self::setID($context));
		$this->assertNull($this->viewVariable('bestAccuracy'));
		$this->assertNull($this->viewVariable('bestTime'));
		$this->assertStringNotContainsString('Best:', $this->view);
		$this->assertTextContains('Reset is only possible when collection size is set to ' . self::PART, $this->view);
		$this->assertSame(1, $this->viewVariable('pdCounter'), 'the page covers the reset part');
	}

	//Path 9: the rank filter of the pages played from changes nothing of what is kept
	public function testFiltersChangeNothingKept(): void
	{
		$context = self::collection(10, [9 => ['rating' => 2400]]);
		$_COOKIE['filtered_ranks'] = '11k';

		$this->solveCleanly($context, 0, 8);
		$this->get('sets/' . self::setID($context));
		$this->assertSame(0, self::storedBests(), 'the filtered page is not the part');
		$this->solveAfterAMiss($context, 9);

		$this->assertEquals(90.91, self::accuracy($context, 0));
	}

	//Path 10: a deleted problem is not in the part; a problem in two collections is in a part of each
	public function testADeletedProblemIsLeftOutAndAProblemOfTwoSetsCountsInEach(): void
	{
		$context = self::collection(11, [
			3 => ['deleted' => date('Y-m-d H:i:s')],
			0 => ['sets' => [1 => ['name' => 'other set', 'num' => 1]]]]);
		$other = self::setID($context, 0, 1);

		for ($i = 1; $i <= 10; $i++)
			if ($i != 3)
				$this->solveCleanly($context, $i, $i);
		$this->assertNull(self::accuracy($context, 0), 'the first problem is not solved');
		$this->solveAfterAMiss($context, 0);

		$this->assertEquals(90.91, self::accuracy($context, 0), 'ten problems, the deleted one left out');
		$this->assertEquals(50, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $other, 0),
			'the part of the other collection, of that problem only');
	}

	//Path 11: a collection a player made keeps nothing, nor does a guest's look at the set page
	public function testAPlayersCollectionAndAGuestKeepNothing(): void
	{
		$more = array_fill(0, 10, ['sets' => [['user_id' => 'self', 'public' => 0]]]);
		// and an official collection the player solved whole, which the guest looks at
		for ($i = 10; $i < 20; $i++)
			$more[$i] = ['sets' => [['name' => 'official set', 'num' => $i - 9]], 'status' => 'S', 'attempts' => [['solved' => true]]];
		$context = self::collection(20, $more);
		$this->solveCleanly($context, 0, 9);
		$this->get('sets/' . self::setID($context));
		$this->assertSame(0, self::storedBests());

		unset($_COOKIE['hackedLoggedInUserID']);
		Auth::logout();
		$this->get('sets/' . self::setID($context, 10));
		$this->assertResponseOk();
		$this->assertSame(0, self::storedBests());
	}

	//Path 12: a problem solved without an attempt kept (the time mode, a challenge and the mistake training keep none)
	//counts as one fail
	public function testAProblemSolvedWithoutAnAttemptIsOneFail(): void
	{
		$context = self::collection(10, [9 => ['status' => 'S']]);

		$this->solveCleanly($context, 0, 8);

		$this->assertSame(0, (int) Util::query('SELECT COUNT(*) AS c FROM tsumego_attempt WHERE tsumego_id = ?', [$context->tsumegos[9]['id']])[0]['c']);
		$this->assertEquals(90, self::accuracy($context, 0), '9 solves against the problem solved without an attempt');
	}

	//Path 12, through the time mode: its solve marks the problem solved and keeps no attempt (the mistake training keeps
	//none either, but leaves the status as it was)
	public function testASolveInTheTimeModeIsOneFail(): void
	{
		$context = self::collection(10, options: [
			'user' => ['name' => 'player', 'collection_size' => self::PART, 'mode' => Constants::$TIME_MODE],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:9', 'started-seconds-ago' => 5]]]]]);
		for ($i = 0; $i < 9; $i++)
			Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, solved, seconds, misplays, gain, user_rating, tsumego_rating, created) VALUES (?, ?, 1, 5, 0, 0, 1000, 1000, NOW())',
				[$context->user['id'], $context->tsumegos[$i]['id']]);
		Util::execute('INSERT INTO tsumego_status (user_id, tsumego_id, status) SELECT ?, id, ? FROM tsumego WHERE id IN (' . implode(',', array_map(fn($t) => (int) $t['id'], array_slice($context->tsumegos, 0, 9))) . ')', [$context->user['id'], 'S']);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];

		$this->post('/tsumegos/result', ['tsumego_id' => $context->tsumegos[9]['id'], 'seconds' => 5, 'outcome' => PlayOutcome::SolvedClean->value, 'mode' => Constants::$TIME_MODE]);
		$this->assertResponseOk();

		$this->assertSame(0, (int) Util::query('SELECT COUNT(*) AS c FROM tsumego_attempt WHERE tsumego_id = ?', [$context->tsumegos[9]['id']])[0]['c']);
		$this->assertSame('S', Util::query('SELECT status FROM tsumego_status WHERE tsumego_id = ?', [$context->tsumegos[9]['id']])[0]['status']);
		$this->assertEquals(90, self::accuracy($context, 0), 'kept by the solve in the time mode, which counts as one fail');
	}
}
