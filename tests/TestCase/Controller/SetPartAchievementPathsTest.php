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
	//The XP of a collection's achievements lifts the new player's level, and the level achievements it reaches unlock
	//with them; they are not what these tests are about, so the helpers leave them out
	private const LEVEL_ACHIEVEMENTS = [AchievementId::LEVEL_UP, AchievementId::FIRST_HERO_POWER, AchievementId::UPGRADED_INTUITION,
		AchievementId::MORE_POWER, AchievementId::HALF_WAY_TO_TOP, AchievementId::CONGRATS_MORE_PROBLEMS, AchievementId::NICE_LEVEL,
		AchievementId::DID_LOT_OF_TSUMEGO, AchievementId::STILL_DOING_TSUMEGO, AchievementId::THE_TOP];

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
			Util::execute('INSERT INTO tsumego (rating, author) VALUES (?, ?)', [$i >= $ratedFrom ? $otherRating : 1000, '']);
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
		return array_values(array_filter(
			array_map(fn($row) => AchievementId::from((int) $row['achievement_id']),
				Util::query('SELECT achievement_id FROM achievement_status WHERE user_id = ? ORDER BY achievement_id', [$context->user['id']])),
			fn(AchievementId $achievement) => !in_array($achievement, self::LEVEL_ACHIEVEMENTS, true)));
	}

	private static function superiorAccuracyCount(ContextPreparator $context): ?int
	{
		$rows = Util::query('SELECT value FROM achievement_status WHERE user_id = ? AND achievement_id = ?', [$context->user['id'], AchievementId::SUPERIOR_ACCURACY->value]);
		return $rows ? (int) $rows[0]['value'] : null;
	}

	//The XP the achievements of the response paid, without the level achievements, or with them
	private static function achievementXp(array $response, bool $withLevelAchievements = false): int
	{
		$xp = 0;
		foreach ($response['achievement_updates'] ?? [] as $achievement)
			if ($withLevelAchievements || !in_array(AchievementId::from((int) $achievement['id']), self::LEVEL_ACHIEVEMENTS, true))
				$xp += (int) $achievement['xp'];
		return $xp;
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

	//The achievements the migration took back are earned again as any achievement is, with their XP and their popups:
	//the player who had them keeps the XP they gave, and gets it again
	public function testAnAchievementTakenBackIsEarnedAgainNormally(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player']]);
		foreach ([...self::BAND_ACHIEVEMENTS, AchievementId::SUPERIOR_ACCURACY] as $achievement)
			Util::execute('INSERT INTO achievement_status (user_id, achievement_id, value) VALUES (?, ?, 3)', [$context->user['id'], $achievement->value]);
		self::migration()->up();
		$this->assertSame([], self::unlocked($context), 'taken back');
		$collection = self::collection($context, 100);
		$context->XPGained();

		$response = $this->finish($context, $collection, 0);

		$this->assertSame([...self::BAND_ACHIEVEMENTS, AchievementId::SUPERIOR_ACCURACY], self::unlocked($context));
		$this->assertSame(1, self::superiorAccuracyCount($context), 'counted from the parts at 100% now');
		$this->assertSame(31000, self::achievementXp($response));
		$this->assertSame(self::achievementXp($response, true) + (int) $response['xp_gained'], $context->XPGained());
		$this->assertGreaterThan(31000, self::achievementXp($response, true), 'with the level achievements its XP reaches');
	}

	//The migration of the test database, run again: back down, then up
	private static function migration(): SetBestsPerPart
	{
		require_once ROOT . '/db/migrations/20261109221207_set_bests_per_part.php';
		$config = require ROOT . '/phinx.php';
		$migration = new SetBestsPerPart('test', 20261109221207);
		$migration->setAdapter(Phinx\Db\Adapter\AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		$migration->down();
		return $migration;
	}
}
