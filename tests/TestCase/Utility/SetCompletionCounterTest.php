<?php

use App\Utility\Auth;
use App\Utility\SetCompletionCounter;

class SetCompletionCounterTest extends IntegrationTestCase
{
	private int $originalPartitionSize;

	public function setUp(): void
	{
		parent::setUp();
		// small parts, so the tests don't need hundreds of problems
		$this->originalPartitionSize = SetCompletionCounter::$PARTITION_SIZE;
		SetCompletionCounter::$PARTITION_SIZE = 2;
	}

	public function tearDown(): void
	{
		SetCompletionCounter::$PARTITION_SIZE = $this->originalPartitionSize;
		parent::tearDown();
	}

	/**
	 * @param array<int, string|null> $statuses status of each problem of the set, by num
	 */
	private static function setProblems(array $statuses, array $set = []): array
	{
		$problems = [];
		foreach ($statuses as $num => $status)
		{
			$problem = ['sets' => [array_merge(['name' => 'big set', 'num' => $num], $set)]];
			if ($status)
				$problem['status'] = $status;
			$problems[] = $problem;
		}
		return $problems;
	}

	public function testEachPartOfASetCountsSeparately(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'S', 3 => 'S', 4 => 'S', 5 => null])]);
		$this->assertSame(2, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testFullySolvedSetCountsAllItsParts(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'S', 3 => 'S', 4 => 'S', 5 => 'S'])]);
		$this->assertSame(3, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testPartiallySolvedPartDoesNotCount(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'V'])]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testReviewAndMasteredCountAsSolved(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'W', 2 => 'C'])]);
		$this->assertSame(1, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testFailedAndGoldenDoNotCountAsSolved(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'X', 3 => 'S', 4 => 'G'])]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testPartsAreCountedInEachSet(): void
	{
		new ContextPreparator(['tsumegos' => array_merge(
			self::setProblems([1 => 'S', 2 => 'S']),
			self::setProblems([1 => 'S'], ['name' => 'small set']))]);
		$this->assertSame(2, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testPrivateSetsDoNotCount(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'S'], ['public' => 0])]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testUserOwnedSetsDoNotCount(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'S'], ['user_id' => 'self'])]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testFavoritesSetsDoNotCount(): void
	{
		new ContextPreparator(['tsumegos' => self::setProblems([1 => 'S', 2 => 'S'], ['name' => 'Favorites', 'public' => 0, 'user_id' => 'self'])]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testDeletedProblemsAreLeftOutOfTheParts(): void
	{
		$problems = self::setProblems([1 => 'S', 2 => null, 3 => 'S', 4 => null]);
		$problems[1]['deleted'] = date('Y-m-d H:i:s');
		new ContextPreparator(['tsumegos' => $problems]);
		// parts are [1, 3] and [4]
		$this->assertSame(1, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testOtherUsersSolvesDoNotCount(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Alice']],
			'tsumegos' => [
				['sets' => [['name' => 'big set', 'num' => 1]], 'status' => ['name' => 'S', 'user' => 'Alice', 'updated' => null]],
				['sets' => [['name' => 'big set', 'num' => 2]], 'status' => 'S'],
			],
		]);
		$this->assertSame(0, SetCompletionCounter::count(Auth::getUserID()));
	}

	public function testSaveKeepsOneConditionPerUser(): void
	{
		new ContextPreparator();
		SetCompletionCounter::save(Auth::getUserID(), 3);
		SetCompletionCounter::save(Auth::getUserID(), 5);
		$conditions = ClassRegistry::init('AchievementCondition')->find('all', [
			'conditions' => ['user_id' => Auth::getUserID(), 'achievement_condition_category_id' => AchievementCondition::CATEGORY_COMPLETED_SETS]]);
		$this->assertCount(1, $conditions);
		$this->assertSame(5, (int) $conditions[0]['AchievementCondition']['value']);
	}
}
