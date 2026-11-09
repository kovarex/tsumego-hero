<?php

use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\PlayOutcome;
use App\Utility\Util;

//The achievements of the bests of the parts of a collection (ProgressDeletionTable::$PARTITION_SIZE, 200 problems),
//earned through the site: the last problem of a part solved on the play page. An achievement of the bests is earned by
//any part of at least SetConnectionTable::ACHIEVEMENT_MINIMUM_PROBLEMS (100) problems, with that part's best and the
//average rating of its problems; Superior Accuracy counts those parts at 100%.
class SetPartAchievementPathsTest extends IntegrationTestCase
{
	private const BAND_ACHIEVEMENTS = [AchievementId::ACCURACY_I, AchievementId::ACCURACY_II, AchievementId::ACCURACY_III,
		AchievementId::SPEED_I, AchievementId::SPEED_II, AchievementId::SPEED_III];

	//A collection of the site of $count problems, each part of it solved cleanly in 3 seconds a problem but its last
	//problem, which the test plays; the problems from $ratedFrom on are rated $otherRating
	/** @return array{set: int, tsumegos: int[]} */
	private static function collection(ContextPreparator $context, int $count, string $title = 'big set', int $ratedFrom = PHP_INT_MAX, int $otherRating = 1600): array
	{
		Util::execute('INSERT INTO `set` (title, public) VALUES (?, 1)', [$title]);
		$setID = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
		$tsumegos = [];
		for ($i = 0; $i < $count; $i++)
		{
			Util::execute('INSERT INTO tsumego (rating, author) VALUES (?, ?)', [$i >= $ratedFrom ? $otherRating : 1200, '']);
			$tsumegos[] = $tsumegoID = (int) Util::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
			Util::execute('INSERT INTO set_connection (set_id, tsumego_id, num) VALUES (?, ?, ?)', [$setID, $tsumegoID, $i + 1]);
			if (($i + 1) % 200 == 0 || $i == $count - 1)
				continue;
			Util::execute('INSERT INTO tsumego_status (user_id, tsumego_id, status) VALUES (?, ?, ?)', [$context->user['id'], $tsumegoID, 'S']);
			Util::execute('INSERT INTO tsumego_attempt (user_id, tsumego_id, solved, seconds, misplays, gain, user_rating, tsumego_rating, created) VALUES (?, ?, 1, 3, 0, 0, 1000, 1200, NOW())',
				[$context->user['id'], $tsumegoID]);
		}
		return ['set' => $setID, 'tsumegos' => $tsumegos];
	}

	//Solves the last problem of the part of the collection on the play page; the response
	private function finish(ContextPreparator $context, array $collection, int $part): array
	{
		$last = min(count($collection['tsumegos']), ($part + 1) * 200) - 1;
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		$this->post('/tsumegos/result', ['tsumego_id' => $collection['tsumegos'][$last], 'seconds' => 3, 'outcome' => PlayOutcome::SolvedClean->value]);
		$this->assertResponseOk();
		Auth::init();
		return json_decode($this->view, true);
	}

	private static function unlocked(ContextPreparator $context): array
	{
		return array_map(fn($row) => AchievementId::from((int) $row['achievement_id']),
			Util::query('SELECT achievement_id FROM achievement_status WHERE user_id = ? ORDER BY achievement_id', [$context->user['id']]));
	}

	private static function superiorAccuracyCount(ContextPreparator $context): ?int
	{
		$rows = Util::query('SELECT value FROM achievement_status WHERE user_id = ? AND achievement_id = ?', [$context->user['id'], AchievementId::SUPERIOR_ACCURACY->value]);
		return $rows ? (int) $rows[0]['value'] : null;
	}

	//The XP the achievements of the response paid
	private static function achievementXp(array $response): int
	{
		return array_sum(array_column($response['achievement_updates'] ?? [], 'xp'));
	}

	//Path 13: a part of 100 problems or more finished cleanly and fast unlocks the accuracy and speed achievements of its
	//rating band and Superior Accuracy, and pays their XP
	public function testAPartFinishedCleanlyUnlocksItsAchievements(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		$collection = self::collection($context, 100);

		$response = $this->finish($context, $collection, 0);

		$this->assertSame([...self::BAND_ACHIEVEMENTS, AchievementId::SUPERIOR_ACCURACY], self::unlocked($context));
		$this->assertEquals(100, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $collection['set'], 0));
		$this->assertEquals(3, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_TIME, $collection['set'], 0));
		$this->assertSame(31000, self::achievementXp($response), '5000 for Superior Accuracy, 26000 for the band achievements');
	}

	//A part is banded by the rating of its own problems: the second part, of harder problems, unlocks the achievements of
	//its band only
	public function testAPartIsBandedByItsOwnRating(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		$collection = self::collection($context, 400, ratedFrom: 200, otherRating: 1600);

		$this->finish($context, $collection, 1);

		$this->assertSame([AchievementId::ACCURACY_VII, AchievementId::ACCURACY_VIII, AchievementId::ACCURACY_IX,
			AchievementId::SPEED_VII, AchievementId::SPEED_VIII, AchievementId::SPEED_IX, AchievementId::SUPERIOR_ACCURACY], self::unlocked($context));
	}

	//Superior Accuracy counts each part of 100 problems or more at 100%: 200, 200 and 50 give two, a collection of 150 one
	public function testSuperiorAccuracyCountsTheParts(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		$big = self::collection($context, 450);
		$smaller = self::collection($context, 150, 'smaller set');

		$this->finish($context, $big, 0);
		$this->assertSame(1, self::superiorAccuracyCount($context));
		$this->finish($context, $big, 2);
		$this->assertSame(1, self::superiorAccuracyCount($context), 'the part of 50 does not count');
		$this->assertEquals(100, AchievementConditionTable::value($context->user['id'], AchievementConditionTable::CATEGORY_SET_ACCURACY, $big['set'], 2), 'though its best is kept');
		$this->finish($context, $big, 1);
		$this->assertSame(2, self::superiorAccuracyCount($context));
		$this->finish($context, $smaller, 0);
		$this->assertSame(3, self::superiorAccuracyCount($context));

		// the achievement's page lists the parts that can earn it, numbered within a collection of several
		$this->get('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value);
		$this->assertResponseOk();
		$this->assertSame([['big set #1', true], ['big set #2', true], ['smaller set', true]],
			array_map(fn($collection) => [$collection['title'], $collection['earned']], $this->viewVariable('collections')));
		$this->assertTextContains('/sets/' . $big['set'] . '/2', $this->view);
	}

	//A part smaller than 100 problems unlocks nothing, however well it was solved
	public function testAPartOfFewerThan100ProblemsUnlocksNothing(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		$collection = self::collection($context, 260);

		$this->finish($context, $collection, 1);

		$this->assertSame([], self::unlocked($context));
	}

	//An achievement taken back from the player by the migration, who kept its XP, pays none when earned again; one never
	//had pays as always
	public function testAnAchievementTakenBackPaysNoXpAgain(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		foreach ([AchievementId::ACCURACY_I, AchievementId::ACCURACY_II, AchievementId::SPEED_I, AchievementId::SUPERIOR_ACCURACY] as $achievement)
			Util::execute('INSERT INTO achievement_reset (user_id, achievement_id, value) VALUES (?, ?, 1)', [$context->user['id'], $achievement->value]);
		$collection = self::collection($context, 100);
		$context->XPGained();

		$response = $this->finish($context, $collection, 0);

		$this->assertSame([...self::BAND_ACHIEVEMENTS, AchievementId::SUPERIOR_ACCURACY], self::unlocked($context), 'earned again');
		$xpByAchievement = array_column($response['achievement_updates'], 'xp', 'id');
		$this->assertSame(0, $xpByAchievement[AchievementId::ACCURACY_I->value]);
		$this->assertSame(0, $xpByAchievement[AchievementId::SUPERIOR_ACCURACY->value]);
		$this->assertGreaterThan(0, $xpByAchievement[AchievementId::ACCURACY_III->value], 'never had');
		$paid = self::achievementXp($response);
		$this->assertSame(16000, $paid, 'ACCURACY_III, SPEED_II and SPEED_III');
		$this->assertSame($paid + (int) $response['xp_gained'], $context->XPGained(), 'the XP added is the solve\'s and the new achievements\'');
	}

	//Superior Accuracy taken back at a count of 2 pays nothing up to it, and nothing beyond, as a further part never did
	public function testSuperiorAccuracyTakenBackPaysNothingAtAnyCount(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		Util::execute('INSERT INTO achievement_reset (user_id, achievement_id, value) VALUES (?, ?, 2)', [$context->user['id'], AchievementId::SUPERIOR_ACCURACY->value]);
		$collection = self::collection($context, 600);

		$xp = [];
		foreach ([0, 1, 2] as $part)
			$xp[] = array_column($this->finish($context, $collection, $part)['achievement_updates'] ?? [], 'xp', 'id')[AchievementId::SUPERIOR_ACCURACY->value] ?? null;

		$this->assertSame(3, self::superiorAccuracyCount($context));
		$this->assertSame([0, null, null], $xp, 'unlocked again without XP, then only counted');
	}
}
