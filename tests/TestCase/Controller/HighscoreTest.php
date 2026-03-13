<?php

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
		$rows = $dom->querySelectorAll('.highscoreTable tr');

		// Row 0 = header, rows 1-3 = users
		$this->assertGreaterThanOrEqual(4, count($rows));
		$this->assertRowContains($rows[1], '#1', 'TopPlayer', '2800');
		$this->assertRowContains($rows[2], '#2', 'MidPlayer', '2200');
		$this->assertRowContains($rows[3], '#3', 'LowPlayer', '1500');
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
		$rows = $dom->querySelectorAll('.highscoreTable tr');

		// Header + 4 user rows
		$this->assertGreaterThanOrEqual(5, count($rows));
		$this->assertRowContains($rows[1], '#1', 'HighLevel');
		$this->assertRowContains($rows[1], '#1', 'Level 50');
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
		$rows = $dom->querySelectorAll('.highscoreTable tr');

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
		$rows = $dom->querySelectorAll('.dailyHighscoreTable tr');

		// Header row + 2 user rows
		$this->assertGreaterThanOrEqual(3, count($rows));
		$this->assertRowContains($rows[1], '1', 'TagMaster', '3');
		$this->assertRowContains($rows[2], '2', 'TagNovice', '1');
	}

	/**
	 * Achievements highscore orders by total achievement score descending.
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
		$rows = $dom->querySelectorAll('.highscoreTable tr');

		// AchieverB has higher total (1+8=9) vs AchieverA (1+5=6)
		$this->assertGreaterThanOrEqual(3, count($rows));
		$this->assertRowContains($rows[1], '#1', 'AchieverB');
		$this->assertRowContains($rows[2], '#2', 'AchieverA');
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

		$this->testAction('users/highscore3', ['return' => 'view']);
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

		$this->testAction('users/highscore3', ['return' => 'view']);
		// Should only appear once with best score
		$this->assertSame(1, substr_count($this->view, 'MultiPlayer'));
		$this->assertTextContains('950', $this->view);
	}

	/**
	 * Self-view with gap works for multiple highscore pages (rating, level).
	 * User outside top 100 should appear with gap separator and color-self class.
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
			$this->assertTextContains('color-self', $this->view);
			$this->assertTextContains('#111', $this->view);
		}
	}

	/**
	 * When logged-in user IS in top 100, no gap separator appears for self.
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
		$this->assertTextNotContains('color-self', $this->view);
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
		$this->assertTextContains('0/115', $this->view);
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
