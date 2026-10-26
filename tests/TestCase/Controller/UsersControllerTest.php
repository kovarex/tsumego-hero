<?php

use App\Utility\AchievementId;
use App\Utility\Rating;
use App\Utility\TimeModeUtil;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class UsersControllerTest extends IntegrationTestCase
{
	/**
	 * Test that login redirects back to the page where user came from.
	 */
	public function testLoginRedirectsToReferer()
	{
		// Create a user that can log in (password is "test" - see ContextPreparator)
		$context = new ContextPreparator([
			'user' => null, // Not logged in
			'other-users' => [['name' => 'testuser']],
		]);
		$browser = Browser::instance();

		// Go to highscore page first
		$browser->get('users/highscore');
		$this->assertStringContainsString('Highscore', $browser->driver->getPageSource());

		// Click login link from the page (this sets proper referer and stores in session)
		$browser->findById('signInMenu')->click();
		// Wait for login form to appear
		$browser->findById('UserName');

		// Fill in login form
		$browser->findById('UserName')->sendKeys('testuser');
		$browser->findById('password')->sendKeys('test');
		$browser->find('#UserLoginForm button[type="submit"]')->click();
		// Should be redirected back to highscore page
		$browser->waitUntilJs('location.href.includes("highscore")');
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString('highscore', $currentUrl, "Expected to redirect back to highscore page, but was at: $currentUrl");
	}

	/**
	 * Test that login page has Google Sign In option.
	 *
	 * The redirect for both regular and Google login uses session storage.
	 * We can't test actual Google OAuth due to token verification.
	 */
	public function testLoginPageHasGoogleSignIn()
	{
		$context = new ContextPreparator(['user' => null]);
		$browser = Browser::instance();

		// Go to login page
		$browser->get('users/login');

		// Verify Google Sign In button is present
		$this->assertTrue($browser->idExists('g_id_onload'), 'Google Sign In should be available');
	}

	public function testUserView()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$this->assertTextContains('DN_kovarex', $browser->driver->getPageSource());
	}

	public function testProfileRedirectsToOwnProfile()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('profile');
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString('/users/view/' . $context->user['id'], $currentUrl, "Expected /profile to redirect to own profile, but was at: $currentUrl");
	}

	public function testMeRedirectsToOwnProfile()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('me');
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString('/users/view/' . $context->user['id'], $currentUrl, "Expected /me to redirect to own profile, but was at: $currentUrl");
	}

	public function testMeSolveHistoryRedirects()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('me/solve-history');
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString('/users/solveHistory/' . $context->user['id'], $currentUrl, "Expected /me/solve-history to redirect, but was at: $currentUrl");
	}

	public function testMeContributionsRedirects()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$browser = Browser::instance();
		$browser->get('me/contributions');
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString('/tags/user/' . $context->user['id'], $currentUrl, "Expected /me/contributions to redirect, but was at: $currentUrl");
	}

	public function testDailyHighscore()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]],
			'tsumego' => ['rating' => 2600, 'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('users/leaderboard');

		// Ivan detkov is alone in the leaderboard, kovarex has no daily_xp but is shown at bottom
		$browser->checkTable('.data-table', $this,
			[
				['Place', 'Name', 'Premium', 'Solved', 'XP'],
				['#1', 'DN_Ivan Detkov ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '2', '10'],
				['#2', 'DN_kovarex ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '0', '0'],
			]);

		// Kovarex solves a problem
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('S'); // solve the problem

		// now kovarex is there (first, since solving gives more xp than Ivan's 10)
		$browser->get('users/leaderboard');
		$browser->checkTable('.data-table', $this,
			[
				['Place', 'Name', 'Premium', 'Solved', 'XP'],
				['#1', 'DN_kovarex ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '1'],
				['#2', 'DN_Ivan Detkov ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '2', '10'],
			]);
	}

	public function testOpenUserPageWhenNotLoggedIn()
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'Ivan Detkov']]]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->otherUsers[0]['id']);
		$this->assertTextContains('DN_Ivan Detkov', $browser->driver->getPageSource());
	}

	public function testEmailIsOnlyOnOwnSettingsPage()
	{
		$context = new ContextPreparator([
			'user' => ['email' => 'current@example.com'],
			'other-users' => [['name' => 'Ivan Detkov', 'rating' => 2600, 'email' => 'detkov@example.com']]]);

		$browser = Browser::instance();
		$browser->get('users/settings');
		$this->assertTextContains('current@example.com', $browser->getTableCell('#name-and-email-table', 0, 0)->getText());

		// The profile of another player never shows their address
		$browser->get('users/view/' . $context->otherUsers[0]['id']);
		$this->assertTextNotContains('detkov@example.com', $browser->driver->getPageSource());
	}

	public function testUserProfilePage()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => [
				'email' => 'current@example.com',
				'level' => 66,
				'xp' => 57,
				'rating' => 2065,
				'solved' => 2],
			'tsumegos' => [
				[
					'status' => 'S',
					'sets' => [['name' => 'set-1', 'num' => 1], ['name' => 'set-2', 'num' => 1]],
					'attempt' => ['user_rating' => 2165]],
				[
					'status' => ['name' => 'S', 'updated' => '2000-01-01 00:00:00'],  // old status
					'sets' => [['name' => 'set-3', 'num' => 1], ['name' => 'set-4', 'num' => 1]]]],
			'time-mode-ranks' => ['5k', '10k', '1d'],
			'time-mode-sessions' => [
				[
					'category' => TimeModeUtil::$CATEGORY_BLITZ,
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'rank' => '5k'
				],
				[
					'category' => TimeModeUtil::$CATEGORY_FAST_SPEED,
					'status' => TimeModeUtil::$SESSION_STATUS_SOLVED,
					'rank' => '10k'
				]]]);
		$context->unlockAchievementsWithoutEffect();
		$browser->get('users/view/' . $context->user['id']);
		$browser->checkTable('#level-info-table', $this, [
			['Level:', '66'],
			['Next level:', '57/4050 XP'],
			['1%'],
			['Health:', '23/23 HP']]);

		$browser->checkTable('#rank-info-table', $this, [
			['RANK'],
			['1d'],
			['Rating:', '2065'],
			['Highest rank:', '2d'],
			['Highest rating:', '2165']]);

		$browser->checkTable('#time-mode-info-table', $this, [
			['Blitz mode rank:', '5k'],
			['Blitz mode runs:', '1'],
			['Fast mode rank:', '10k'],
			['Fast mode runs:', '1'],
			['Slow mode rank:', 'N/A'],
			['Slow mode runs:', '0']]);

		$browser->checkTable('#final-info-table', $this, [
			['Member since:', date('F Y')],
			['Completed:', '2 of 2'], // one problem in two sets still counted as one
			['100%']]);

		$browser->get('users/settings');
		$this->assertSame('RESET OLD PROGRESS (1)', $browser->find('#reset-statuses-button')->getText());

		// clicking reset removes the status
		$this->assertNotEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 2);

		$browser->driver->executeScript("window.confirm = function(msg) {return true;};");
		$browser->clickId('reset-statuses-button');

		$this->assertEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 1);
	}

	/**
	 * A golden problem waits for its one attempt, which is not progress left behind, so
	 * the reset keeps it while it removes what nobody touched for a year.
	 */
	public function testResetKeepsAGoldenProblemInProgress(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'resetGolden'],
			'tsumegos' => [
				['status' => ['name' => 'S', 'updated' => '2000-01-01 00:00:00'], 'sets' => [['name' => 'reset-1', 'num' => 1]]],
				['status' => ['name' => 'G', 'updated' => '2000-01-01 00:00:00'], 'sets' => [['name' => 'reset-2', 'num' => 1]]],
				['status' => ['name' => 'S'], 'sets' => [['name' => 'reset-2', 'num' => 2]]],
				['status' => ['name' => 'S'], 'sets' => [['name' => 'reset-2', 'num' => 3]]],
				['status' => ['name' => 'S'], 'sets' => [['name' => 'reset-2', 'num' => 4]]]]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertSame('RESET OLD PROGRESS (1)', $browser->find('#reset-statuses-button')->getText());

		$browser->driver->executeScript("window.confirm = function(msg) {return true;};");
		$browser->clickId('reset-statuses-button');

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus');
		$this->assertEmpty($tsumegoStatus->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
		$this->assertNotEmpty($tsumegoStatus->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
	}

	public function testProfileShowsRank(): void
	{
		$context = new ContextPreparator(['user' => ['rating' => 2065]]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$pageSource = $browser->driver->getPageSource();

		$this->assertStringContainsString('<span class="rank-icon">1d</span>', $pageSource);
	}

	public function testProfileShowsRemainingHealthForSelfAndOthers(): void
	{
		$context = new ContextPreparator([
			'user' => ['level' => 66, 'health' => 20],
			'other-users' => [['name' => 'target', 'level' => 66, 'health' => 20]]]);
		$browser = Browser::instance();
		foreach ([$context->user['id'], $context->otherUsers[0]['id']] as $userId)
		{
			$browser->get('users/view/' . $userId);
			$browser->checkTable('#level-info-table', $this, [
				3 => ['Health:', '20/23 HP']]);
		}
	}

	public function testProfileShowsSolveHistoryLink(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'testuser']]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$pageSource = $browser->driver->getPageSource();

		$this->assertStringContainsString('/users/solveHistory/' . $context->user['id'], $pageSource,
			'Solve history link should be present on profile');
	}

	/**
	 * Test that profile achievements link to the correct achievement id.
	 *
	 * Achievement ids are not contiguous (id 100 is missing), so the profile
	 * must resolve each achievement status to the matching achievement.
	 */
	public function testProfileAchievementLinksToCorrectAchievement(): void
	{
		// This only breaks because the ids are not contiguous, so pin the
		// precondition that id 100 is absent from the achievement table.
		$this->assertEmpty(
			ClassRegistry::init('Achievement')->findById(100),
			'Test relies on achievement id 100 being absent'
		);

		$context = new ContextPreparator([
			'user' => [
				'name' => 'achievementuser',
				'achievement-statuses' => [
					['id' => 115, 'created' => date('Y-m-d H:i:s')],
				],
			],
		]);

		$this->get('users/view/' . $context->user['id']);

		$this->assertStringContainsString('/achievements/view/115', $this->view);
		$this->assertStringContainsString('/achievements/view/115/' . $context->user['id'], $this->view,
			'the achievement link keeps the profile it was opened from');
		$this->assertStringContainsString('1000 Weiqi problems 2nd half', $this->view);
	}

	/**
	 * The profile counts the problems the player solved over the problems the site holds, wherever the
	 * collections of those problems stand: a solve of a problem of a collection nobody published yet
	 * counts for the player and counts in the total. The solved number is the counter of the account,
	 * the one the highscore shows, so a solve that outlives its problem is still in it.
	 */
	public function testProfileCountsTheProblemsThePlayerSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'solver', 'solved' => 3],
			'tsumegos' => [
				['status' => 'S', 'sets' => [['name' => 'published set', 'num' => 1]]],
				['status' => 'S', 'sets' => [['name' => 'unpublished set', 'public' => 0, 'num' => 1]]],
				['sets' => [['name' => 'published set', 'num' => 2]]],
			],
		]);

		$this->get('users/view/' . $context->user['id']);

		$this->assertMatchesRegularExpression('#Completed:</td>\s*<td>3 of 3</td>#', $this->view);
	}

	/**
	 * The profile counts each unlocked achievement once, even when the repeatable
	 * one was earned several times.
	 */
	public function testProfileAchievementCountCountsEachAchievementOnce(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'repeater',
				'achievement-statuses' => [
					['id' => AchievementId::PROBLEMS_1000->value],
					['id' => AchievementId::PROBLEMS_2000->value],
					['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3],
				],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);

		// three achievements unlocked, one of them earned three times
		$browser->checkTable('#final-info-table', $this, [
			3 => ['Achievements:', '3 of ' . AchievementId::count() . ' +3'],
		]);
	}

	/**
	 * The profile achievement line shows no repetitions for a user who earned
	 * every achievement exactly once.
	 */
	public function testProfileWithoutRepeatedAchievementsHasPlainCount(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'collector',
				'achievement-statuses' => [
					['id' => AchievementId::PROBLEMS_1000->value],
					['id' => AchievementId::SUPERIOR_ACCURACY->value],
				],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);

		$browser->checkTable('#final-info-table', $this, [
			3 => ['Achievements:', '2 of ' . AchievementId::count()],
		]);
	}

	/**
	 * The profile achievement card shows how often a repeatable achievement was earned.
	 */
	public function testProfileAchievementCardShowsNumberOfEarnings(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'repeater',
				'achievement-statuses' => [
					['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3],
				],
			],
		]);

		$this->get('users/view/' . $context->user['id']);

		$this->assertStringContainsString('title="Earned 3 times"', $this->view);
	}

	/**
	 * The achievement count on the profile carries the number of times the repeatable
	 * achievement was earned, the same number the achievement itself shows.
	 */
	public function testProfileCountCarriesNumberOfEarnings(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'repeater',
				'achievement-statuses' => [
					['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3],
				],
			],
		]);

		$this->get('users/view/' . $context->user['id']);

		$this->assertStringContainsString(
			'1 of ' . AchievementId::count() . ' <span class="achievementEarnedTimes" title="Earned 3 times">+3</span>',
			$this->view,
			'the count of completed achievements carries how often the repeatable one was earned');
	}

	public function testSolveHistoryShowsOnlyViewableSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'alice']],
			'tsumego' => [
				'sets' => [['name' => 'public set', 'public' => 1, 'num' => 10]],
				'attempt' => ['solved' => 1],
			],
		]);

		$set = ClassRegistry::init('Set');
		$set->create();
		$set->save(['title' => 'alice favorites', 'public' => 0, 'user_id' => $context->otherUsers[0]['id'], 'order' => 1]);
		$connection = ClassRegistry::init('SetConnection');
		$connection->create();
		$connection->save(['tsumego_id' => $context->tsumegos[0]['id'], 'set_id' => $set->id, 'num' => 3]);

		$this->get('users/solveHistory/' . $context->user['id']);

		$this->assertTextContains('public set', $this->view);
		$this->assertStringNotContainsString('alice favorites', $this->view);
	}

	public function testSolveHistoryOfAnUnknownUserIsNotFound(): void
	{
		new ContextPreparator(['user' => ['name' => 'viewer']]);

		$this->get('users/solveHistory/99999999');

		$this->assertResponseCode(404);
	}

	public function testProgressBarPreferenceOnlyOnOwnSettingsPage(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'target']],
		]);
		$target = $context->otherUsers[0];

		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertStringContainsString('id="levelBarDisplay1"', $browser->driver->getPageSource());

		// A profile shows the numbers only, never the preferences behind them
		$browser->get('users/view/' . $target['id']);
		$this->assertStringNotContainsString('id="levelBarDisplay1"', $browser->driver->getPageSource());
	}

	public function testTsumegoRatingGraph()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [[
				'set_order' => 1,
				'rating' => '2200',
				'attempt' => ['user_rating' => 2165],
				'status' => 'S']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('showx8');
		$this->assertTextContains('Rating history', $browser->driver->getPageSource());
		$this->assertTextContains('test set', $browser->driver->getPageSource());
	}

	public function testResetOldProgressRemovesOnlyOldStatuses()
	{
		$twoYearsAgo = date('Y-m-d H:i:s', strtotime('-2 years'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'solved' => 4],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'updated' => $twoYearsAgo]],
				['set_order' => 2, 'status' => 'S'],
				['set_order' => 3, 'status' => 'S'],
				['set_order' => 4, 'status' => 'S'],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/settings');

		$browser->driver->executeScript("window.confirm = function() { return true; }");
		$browser->findById('reset-statuses-button')->click();

		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Deleted progress on 1 problems")');

		$remaining = ClassRegistry::init('TsumegoStatus')->find('count', [
			'conditions' => ['user_id' => $context->user['id']],
		]);
		$this->assertEquals(3, $remaining);
	}

	/**
	 * Progress that was only opened is not what the button offers to reset, so it stays
	 * and the flash repeats the number the button offered.
	 */
	public function testResetKeepsProblemsThatWereOnlyOpened(): void
	{
		$twoYearsAgo = date('Y-m-d H:i:s', strtotime('-2 years'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'solved' => 4],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'updated' => $twoYearsAgo]],
				['set_order' => 2, 'status' => ['name' => 'V', 'updated' => $twoYearsAgo]],
				['set_order' => 3, 'status' => 'S'],
				['set_order' => 4, 'status' => 'S'],
			],
		]);

		$browser = Browser::instance();
		$browser->get('users/settings');
		$this->assertSame('RESET OLD PROGRESS (1)', $browser->findById('reset-statuses-button')->getText());

		$browser->driver->executeScript("window.confirm = function() { return true; }");
		$browser->findById('reset-statuses-button')->click();

		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Deleted progress on 1 problems")');

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus');
		$this->assertEmpty($tsumegoStatus->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
		$this->assertNotEmpty($tsumegoStatus->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
	}

	public function testResetOldProgressDoesNotAffectOtherUsers()
	{
		$twoYearsAgo = date('Y-m-d H:i:s', strtotime('-2 years'));
		$context = new ContextPreparator([
			'user' => ['name' => 'alice', 'solved' => 2],
			'other-users' => [['name' => 'bob']],
			'tsumegos' => [
				['set_order' => 1, 'statuses' => [
					['name' => 'S', 'updated' => $twoYearsAgo],
					['name' => 'S', 'updated' => $twoYearsAgo, 'user' => 'bob'],
				]],
				['set_order' => 2, 'status' => 'S'],
			],
		]);

		$bobId = ContextPreparator::getUserIdFromName('bob');

		$browser = Browser::instance();
		$browser->get('users/settings');

		$browser->driver->executeScript("window.confirm = function() { return true; }");
		$browser->findById('reset-statuses-button')->click();

		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Deleted progress on 1 problems")');

		$aliceRemaining = ClassRegistry::init('TsumegoStatus')->find('count', [
			'conditions' => ['user_id' => $context->user['id']],
		]);
		$this->assertEquals(1, $aliceRemaining, "Alice's old status should have been deleted");

		$bobRemaining = ClassRegistry::init('TsumegoStatus')->find('count', [
			'conditions' => ['user_id' => $bobId],
		]);
		$this->assertEquals(1, $bobRemaining, "Bob's status should not have been affected");
	}

	public function testTimeModeFiltersBySpeedAndRank()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'timeplayer']],
		]);

		$rankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '15k']]);
		$rankId = $rankRow['TimeModeRank']['id'];
		$rank10kRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '10k']]);
		$rankId10k = $rank10kRow['TimeModeRank']['id'];
		$sessionModel = ClassRegistry::init('TimeModeSession');

		$sessions = [
			['category' => TimeModeCategory::SLOW, 'rank_id' => $rankId, 'points' => 900],
			['category' => TimeModeCategory::FAST, 'rank_id' => $rankId, 'points' => 600],
			['category' => TimeModeCategory::SLOW, 'rank_id' => $rankId10k, 'points' => 750],
		];
		foreach ($sessions as $s)
		{
			$sessionModel->create();
			$sessionModel->save(['TimeModeSession' => [
				'user_id' => $context->otherUsers[0]['id'],
				'time_mode_category_id' => $s['category'],
				'time_mode_rank_id' => $s['rank_id'],
				'time_mode_session_status_id' => TimeModeSessionStatus::SOLVED,
				'points' => $s['points'],
			]]);
		}

		// Slow/15k: shows 900, not 600 or 750
		$this->get('users/time_mode?category=2&rank=15k');
		$this->assertTextContains('900.00', $this->view);
		$this->assertTextNotContains('600.00', $this->view);
		$this->assertTextNotContains('750.00', $this->view);
		$this->assertTextContains('btn--inactive">Slow</a>', $this->view);
		$this->assertTextContains('category=1', $this->view); // Fast link preserves rank

		// Fast/15k: shows 600, not 900 or 750
		$this->get('users/time_mode?category=1&rank=15k');
		$this->assertTextContains('600.00', $this->view);
		$this->assertTextNotContains('900.00', $this->view);
		$this->assertTextContains('btn--inactive">Fast</a>', $this->view);

		// Slow/10k: shows 750, not 900
		$this->get('users/time_mode?category=2&rank=10k');
		$this->assertTextContains('750.00', $this->view);
		$this->assertTextNotContains('900.00', $this->view);
	}

	public function testTimeModeRankButtonsPreserveCategory()
	{
		$context = new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$rankRow = ClassRegistry::init('TimeModeRank')->find('first', ['conditions' => ['name' => '14k']]);
		$rankId = $rankRow['TimeModeRank']['id'];
		$sessionModel = ClassRegistry::init('TimeModeSession');

		foreach ([TimeModeCategory::SLOW, TimeModeCategory::FAST, TimeModeCategory::BLITZ] as $cat)
		{
			$sessionModel->create();
			$sessionModel->save(['TimeModeSession' => [
				'user_id' => $context->user['id'],
				'time_mode_category_id' => $cat,
				'time_mode_rank_id' => $rankId,
				'time_mode_session_status_id' => TimeModeSessionStatus::SOLVED,
				'points' => 100,
			]]);
		}

		// Each category's rank buttons should link back to the same category
		foreach (['2' => 'Slow', '1' => 'Fast', '0' => 'Blitz'] as $catId => $catName)
		{
			$this->get("users/time_mode?category={$catId}&rank=15k");
			// Rank button links should contain the current category
			$this->assertTextContains("category={$catId}&rank=", $this->view);
		}
	}

	public function testDemoteAdminRedirectsAnonymousToLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/users/demote_admin');

		$this->assertResponseCode(302);
	}

	public function testAdminCanDemoteSelf()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		$this->post('/users/demote_admin', ['User' => ['demote' => 'test']]);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::ROLE_PLAYER, (int) $user['User']['user_role_id']);
		// The view must redirect to the demoted user's own profile with the id filled in.
		$this->assertStringContainsString('/users/view/' . $context->user['id'], $this->view);
	}

	public function testAdminCanDemoteSelfInBrowser()
	{
		$context = new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);
		$browser = Browser::instance();

		$browser->get('users/demote_admin');

		$browser->findById('UserDemote')->sendKeys('test');
		$browser->find('input[type="submit"]')->click();

		$browser->waitUntilJs('location.href.includes("/users/view/' . $context->user['id'] . '")');

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::ROLE_PLAYER, (int) $user['User']['user_role_id']);
	}

	public function testLastOwnerCantDemoteSelf()
	{
		$context = new ContextPreparator(['user' => ['name' => 'owner', 'owner' => true]]);

		$this->post('/users/demote_admin', ['User' => ['demote' => 'test']]);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::ROLE_OWNER, (int) $user['User']['user_role_id']);
		$this->assertStringContainsString('is the last owner', $this->view);
	}

	public function testNonAdminDemoteIsNoop()
	{
		$context = new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->post('/users/demote_admin', ['User' => ['demote' => 'test']]);

		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::ROLE_PLAYER, (int) $user['User']['user_role_id']);
	}
}
