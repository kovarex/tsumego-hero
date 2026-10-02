<?php

use App\Utility\Level;
use App\Utility\Rating;

class HighscoreTest extends TestCaseWithAuth
{
	/**
	 * Rating highscore shows users sorted by rating descending with correct positions.
	 */
	public function testRatingHighscoreOrdering()
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'TopPlayer', 'rating' => 2800],
				['name' => 'MidPlayer', 'rating' => 2200],
				['name' => 'LowPlayer', 'rating' => 1500],
			],
		]);

		$this->testAction('users/rating', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		// Row 0 = header, rows 1-3 = users
		$this->assertGreaterThanOrEqual(4, count($rows));
		$this->assertRowContains($rows[1], '#1', 'TopPlayer', '2,800');
		$this->assertRowContains($rows[2], '#2', 'MidPlayer', '2,200');
		$this->assertRowContains($rows[3], '#3', 'LowPlayer', '1,500');
	}

	/**
	 * Rating highscore shows correct rank labels per rating.
	 */
	public function testRatingHighscoreRanks()
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'Dan9', 'rating' => 2887],
				['name' => 'Dan1', 'rating' => 2065],
				['name' => 'Kyu1', 'rating' => 1901],
			],
		]);

		$this->testAction('users/rating', ['return' => 'view']);
		$this->assertTextContains('12d', $this->view);
		$this->assertTextContains('1d', $this->view);
		$this->assertTextContains('2k', $this->view);
	}

	/**
	 * Level highscore orders by level DESC, xp DESC.
	 */
	public function testLevelHighscoreOrdering()
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'HighLevel', 'level' => 50, 'xp' => 100],
				['name' => 'SameLevelMoreXP', 'level' => 30, 'xp' => 500],
				['name' => 'SameLevelLessXP', 'level' => 30, 'xp' => 200],
				['name' => 'LowLevel', 'level' => 5, 'xp' => 999],
			],
		]);

		$this->testAction('users/highscore', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		// Header + 4 user rows
		$this->assertGreaterThanOrEqual(5, count($rows));
		$this->assertRowContains($rows[1], '#1', 'HighLevel');
		$this->assertRowContains($rows[1], '#1', '50');
		$this->assertRowContains($rows[2], '#2', 'SameLevelMoreXP');
		$this->assertRowContains($rows[3], '#3', 'SameLevelLessXP');
		$this->assertRowContains($rows[4], '#4', 'LowLevel');
	}

	/**
	 * Level highscore works for logged in users too.
	 */
	public function testLevelHighscoreLoggedIn()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex', 'level' => 10],
			'other-users' => [
				['name' => 'HighLevel', 'level' => 50],
			],
		]);

		$this->testAction('users/highscore', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		$this->assertRowContains($rows[1], '#1', 'HighLevel');
		$this->assertRowContains($rows[2], '#2', 'kovarex');
	}

	/**
	 * Tags highscore orders by tag count descending.
	 */
	public function testTagHighscoreOrdering()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [
				['name' => 'TagMaster'],
				['name' => 'TagNovice'],
			],
			'tsumegos' => [
				[
					'set_order' => 1,
					'tags' => [
						['name' => 'atari', 'user' => 'TagMaster'],
						['name' => 'life-and-death', 'user' => 'TagMaster'],
						['name' => 'tesuji', 'user' => 'TagMaster'],
					],
				],
				[
					'set_order' => 2,
					'tags' => [
						['name' => 'ladder', 'user' => 'TagNovice'],
					],
				],
			],
		]);

		$this->testAction('users/added_tags', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		// Header row + 2 user rows
		$this->assertGreaterThanOrEqual(3, count($rows));
		$this->assertRowContains($rows[1], '1', 'TagMaster', '3');
		$this->assertRowContains($rows[2], '2', 'TagNovice', '1');
	}

	/**
	 * Achievements highscore orders by the number of unlocked achievements. Players
	 * with the same number are separated by how often they earned the repeatable
	 * achievement, which does not add to the completed count itself.
	 */
	public function testAchievementsHighscoreOrdering()
	{
		new ContextPreparator([
			'other-users' => [
				[
					'name' => 'AchieverA',
					'rating' => 2200,
					'achievement-statuses' => [
						['id' => Achievement::PROBLEMS_1000],
						['id' => Achievement::SUPERIOR_ACCURACY, 'value' => 5],
					],
				],
				[
					'name' => 'AchieverB',
					'rating' => 1800,
					'achievement-statuses' => [
						['id' => Achievement::PROBLEMS_1000],
						['id' => Achievement::SUPERIOR_ACCURACY, 'value' => 8],
					],
				],
			],
		]);

		$this->testAction('users/achievements', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		// Both unlocked 2 achievements; the repetitions of Superior Accuracy only
		// separate them, they don't count as further completed achievements.
		$this->assertGreaterThanOrEqual(3, count($rows));
		// both unlocked 2 achievements; the repeats of Superior Accuracy only break
		// the tie, they are not counted as further completed achievements
		$this->assertRowContains($rows[1], '#1', 'AchieverB', '2/' . Achievement::COUNT . ' +7');
		$this->assertRowContains($rows[2], '#2', 'AchieverA', '2/' . Achievement::COUNT . ' +4');
		// the tiebreaker rides along in the score cell, it gets no column of its own
		$this->assertRowContains($rows[0], 'Place', 'Name', 'Premium', 'Completed');
		$this->assertStringNotContainsString('Repeats', $rows[0]->textContent);
		$this->assertStringContainsString('Extra completions of repeatable achievements', $this->view);
	}

	/**
	 * Time mode highscore shows users with highest points per category/rank combo.
	 */
	public function testTimeModeHighscoreOrdering()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [
				['name' => 'FastPlayer', 'rating' => 2200],
				['name' => 'SlowPlayer', 'rating' => 1800],
			],
		]);

		$rankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '15k']]);
		$this->assertNotEmpty($rankRow);
		$rankId = $rankRow['TimeModeRank']['id'];
		$sessionModel = ClassRegistry::init('TimeModeSession');

		// FastPlayer: 900 points
		$sessionModel->create();
		$sessionModel->save(['TimeModeSession' => [
			'user_id' => $context->otherUsers[0]['id'],
			'time_mode_category_id' => TimeModeCategory::SLOW,
			'time_mode_rank_id' => $rankId,
			'time_mode_session_status_id' => TimeModeSessionStatus::SOLVED,
			'points' => 900,
		]]);

		// SlowPlayer: 600 points
		$sessionModel->create();
		$sessionModel->save(['TimeModeSession' => [
			'user_id' => $context->otherUsers[1]['id'],
			'time_mode_category_id' => TimeModeCategory::SLOW,
			'time_mode_rank_id' => $rankId,
			'time_mode_session_status_id' => TimeModeSessionStatus::SOLVED,
			'points' => 600,
		]]);

		$this->testAction('users/time_mode', ['return' => 'view']);
		$source = $this->view;
		// FastPlayer should be before SlowPlayer
		$fastPos = strpos($source, 'FastPlayer');
		$slowPos = strpos($source, 'SlowPlayer');
		$this->assertNotFalse($fastPos);
		$this->assertNotFalse($slowPos);
		$this->assertLessThan($slowPos, $fastPos, 'FastPlayer (900pts) should appear before SlowPlayer (600pts)');
	}

	/**
	 * Time mode highscore deduplicates: only best score per user shown.
	 */
	public function testTimeModeHighscoreDedup()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [
				['name' => 'MultiPlayer', 'rating' => 2000],
			],
		]);

		$rankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '15k']]);
		$rankId = $rankRow['TimeModeRank']['id'];
		$sessionModel = ClassRegistry::init('TimeModeSession');

		// MultiPlayer plays 3 times, best score = 950
		foreach ([700, 950, 800] as $points)
		{
			$sessionModel->create();
			$sessionModel->save(['TimeModeSession' => [
				'user_id' => $context->otherUsers[0]['id'],
				'time_mode_category_id' => TimeModeCategory::SLOW,
				'time_mode_rank_id' => $rankId,
				'time_mode_session_status_id' => TimeModeSessionStatus::SOLVED,
				'points' => $points,
			]]);
		}

		$this->testAction('users/time_mode', ['return' => 'view']);
		// Should only appear once with best score
		$this->assertSame(1, substr_count($this->view, 'MultiPlayer'));
		$this->assertTextContains('950', $this->view);
	}

	/**
	 * Self-view with gap works for multiple highscore pages (rating, level).
	 * User outside top 30 should appear with gap separator and data-table__row--self class.
	 */
	public function testSelfViewWithGap()
	{
		$otherUsers = [];
		for ($i = 0; $i < 110; $i++)
			$otherUsers[] = ['name' => 'player' . $i, 'rating' => 2000 + $i, 'level' => 50 + $i];

		new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => 500, 'level' => 1],
			'other-users' => $otherUsers,
		]);

		foreach (['users/rating', 'users/highscore'] as $url)
		{
			$this->testAction($url, ['return' => 'view']);
			$this->assertTextContains('kovarex', $this->view);
			$this->assertTextContains('⋮', $this->view);
			$this->assertTextContains('data-table__row--self', $this->view);
			$this->assertTextContains('#111', $this->view);
		}
	}

	/**
	 * When logged-in user IS in top 30, no gap separator appears for self.
	 */
	public function testRatingHighscoreNoGapWhenInTop100()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => 2500],
			'other-users' => [
				['name' => 'TopPlayer', 'rating' => 2800],
			],
		]);

		$this->testAction('users/rating', ['return' => 'view']);
		$this->assertTextContains('kovarex', $this->view);
		// No gap separator when both users are within top 100
		$this->assertTextNotContains('⋮', $this->view);
	}

	/**
	 * When not logged in, no self-view section appears.
	 */
	public function testRatingHighscoreNotLoggedIn()
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'TopPlayer', 'rating' => 2800],
				['name' => 'SecondPlayer', 'rating' => 2200],
			],
		]);

		$this->testAction('users/rating', ['return' => 'view']);
		$this->assertTextContains('TopPlayer', $this->view);
		$this->assertTextContains('SecondPlayer', $this->view);
		$this->assertTextNotContains('data-table__row--self', $this->view);
		$this->assertTextNotContains('⋮', $this->view);
	}

	/**
	 * Achievements highscore self-view: user with no achievements appears with 0.
	 */
	public function testAchievementsHighscoreUserWithNoAchievements()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [
				[
					'name' => 'AchPlayer',
					'achievement-statuses' => [['id' => Achievement::PROBLEMS_1000]],
				],
			],
		]);

		$this->testAction('users/achievements', ['return' => 'view']);
		$this->assertTextContains('AchPlayer', $this->view);
		// kovarex has no achievements but still appears via self-view with 0
		$this->assertTextContains('kovarex', $this->view);
		$this->assertTextContains('0/' . Achievement::COUNT, $this->view);
	}

	/**
	 * Achievement highscore counts each unlocked achievement once: earning the
	 * repeatable achievement again does not raise the score.
	 */
	public function testAchievementsHighscoreCountsEachAchievementOnce()
	{
		new ContextPreparator([
			'other-users' => [
				[
					'name' => 'Repeater',
					'achievement-statuses' => [
						['id' => Achievement::PROBLEMS_1000],
						['id' => Achievement::PROBLEMS_2000],
						['id' => Achievement::SUPERIOR_ACCURACY, 'value' => 5],
					],
				],
				[
					'name' => 'Collector',
					'achievement-statuses' => [
						['id' => Achievement::PROBLEMS_3000],
						['id' => Achievement::PROBLEMS_4000],
						['id' => Achievement::PROBLEMS_5000],
					],
				],
			],
		]);

		$this->testAction('users/achievements', ['return' => 'view']);
		$dom = $this->getStringDom();
		$rows = $dom->querySelectorAll('.data-table tr');

		// both unlocked three achievements, the repetitions of Superior Accuracy excluded
		$this->assertRowContains($rows[1], 'Repeater', '3/' . Achievement::COUNT . ' +4');
		$this->assertRowContains($rows[2], 'Collector', '3/' . Achievement::COUNT);
		// the collector earned both exactly once, so nothing is added to his score
		$this->assertStringNotContainsString('+', $rows[2]->textContent);
	}

	/**
	 * Tags highscore: user with no approved tags appears with 0 via self-view.
	 */
	public function testTagHighscoreUserWithNoTags()
	{
		new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Tagger']],
			'tsumegos' => [
				[
					'set_order' => 1,
					'tags' => [['name' => 'atari', 'user' => 'Tagger']],
				],
			],
		]);

		$this->testAction('users/added_tags', ['return' => 'view']);
		$this->assertTextContains('Tagger', $this->view);
		// kovarex has no tags but appears via self-view
		$this->assertTextContains('kovarex', $this->view);
	}

	/**
	 * Helper to assert that a table row's text content contains all given strings.
	 */
	private function assertRowContains($row, string ...$expectedTexts): void
	{
		$rowText = $row->textContent;
		foreach ($expectedTexts as $text)
			$this->assertStringContainsString($text, $rowText, "Expected row to contain '{$text}', got: '{$rowText}'");
	}
}
