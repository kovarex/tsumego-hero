<?php

use Facebook\WebDriver\WebDriverBy;

class UsersControllerTest extends ControllerTestCase
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
		$browser->driver->findElement(WebDriverBy::id('signInMenu'))->click();
		// Wait for login form to appear
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(\Facebook\WebDriver\WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('UserName')));

		// Fill in login form
		$browser->driver->findElement(WebDriverBy::id('UserName'))->sendKeys('testuser');
		$browser->driver->findElement(WebDriverBy::id('password'))->sendKeys('test');
		$browser->driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'))->click();

		// Should be redirected back to highscore page
		$wait->until(function ($driver) {
			return str_contains($driver->getCurrentURL(), 'highscore');
		});
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
		$this->assertTextContains('kovarex', $browser->driver->getPageSource());
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
		$browser->checkTable('.dailyHighscoreTable', $this,
			[
				['Place', 'Name', 'Premium', 'Solved', 'XP'],
				['#1', 'Ivan Detkov ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '2 solved', '10 XP'],
				['#2', 'kovarex ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '0 solved', '0 XP'],
			]);

		// Kovarex solves a problem
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('S'); // solve the problem

		// now kovarex is there (first, since solving gives more xp than Ivan's 10)
		$browser->get('users/leaderboard');
		$browser->checkTable('.dailyHighscoreTable', $this,
			[
				['Place', 'Name', 'Premium', 'Solved', 'XP'],
				['#1', 'kovarex ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '1 solved'],
				['#2', 'Ivan Detkov ' . Rating::getReadableRankFromRating(ContextPreparator::$DEFAULT_USER_RATING), '', '2 solved', '10 XP'],
			]);
	}

	public function testUserContributionsShowsTagAdded()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'tags' => [['name' => 'atari', 'user' => 'kovarex']]]]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$browser->clickId("navigate-to-contributions");
		$this->assertTextContains('kovarex added the tag <i>atari</i>', $browser->driver->getPageSource());
	}

	public function testOpenUserPageWhenNotLoggedIn()
	{
		$context = new ContextPreparator(['other-users' => [['name' => 'Ivan Detkov']]]);
		$browser = Browser::instance();
		$browser->get('users/view/' . $context->otherUsers[0]['id']);
		$this->assertTextContains('Ivan Detkov', $browser->driver->getPageSource());
	}

	public function testUserProfilePageEmailOnlyVisibleToCurrentUser()
	{
		$context = new ContextPreparator([
			'user' => ['email' => 'current@example.com'],
			'other-users' => [['name' => 'Ivan Detkov', 'rating' => 2600, 'email' => 'detkov@example.com']]]);

		$browser = Browser::instance();
		$browser->get('users/view/' . $context->user['id']);
		$this->assertTextContains('current@example.com', $browser->getTableCell('#name-and-email-table', 1, 0)->getText());
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
			['Level up:', '57/4050'],
			['XP earned:', '90957 XP'],
			['Health:', Util::getHealthBasedOnLevel(66) . ' HP'],
			['Hero powers:', '3']]);

		$browser->checkTable('#rank-info-table', $this, [
			['Rank:', '1d'],
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
			['Overall solved:', '2 of 2'], // one problem in two sets still counted as one
			['Overall %:', '100%']]);

		$this->assertSame('RESET (1)', $browser->find('#reset-statuses-button')->getText());

		// clicking reset removes the status
		$this->assertNotEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 2);

		$browser->driver->executeScript("window.confirm = function(msg) {return true;};");
		$browser->clickId('reset-statuses-button');

		$this->assertEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 1);
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

	public function testShowPublishSchedule()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 268, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 673]]]]]);

		$tsumegoToPublish = $context->tsumegos[0];
		$publicSetID = $context->tsumegos[1]['set-connections'][0]['set_id'];

		ClassRegistry::init('Schedule')->create();
		$scheduleItem = [];
		$scheduleItem['tsumego_id'] = $tsumegoToPublish['id'];
		$scheduleItem['set_id'] = $publicSetID;
		$scheduleItem['date'] = date('Y-m-d');
		$scheduleItem['published'] = 0;
		ClassRegistry::init('Schedule')->save($scheduleItem);

		$browser = Browser::instance();
		$browser->get('/users/showPublishSchedule');
		$this->assertTextContains('sandbox set', $browser->getTableCell('.highscoreTable', 1, 1)->getText());
		$this->assertTextContains('268', $browser->getTableCell('.highscoreTable', 1, 1)->getText());
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
		$this->testAction('users/highscore3?category=2&rank=15k', ['return' => 'view']);
		$this->assertTextContains('900.00', $this->view);
		$this->assertTextNotContains('600.00', $this->view);
		$this->assertTextNotContains('750.00', $this->view);
		$this->assertTextContains('new-button-time-inactive">Slow</a>', $this->view);
		$this->assertTextContains('category=1', $this->view); // Fast link preserves rank

		// Fast/15k: shows 600, not 900 or 750
		$this->testAction('users/highscore3?category=1&rank=15k', ['return' => 'view']);
		$this->assertTextContains('600.00', $this->view);
		$this->assertTextNotContains('900.00', $this->view);
		$this->assertTextContains('new-button-time-inactive">Fast</a>', $this->view);

		// Slow/10k: shows 750, not 900
		$this->testAction('users/highscore3?category=2&rank=10k', ['return' => 'view']);
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
			$this->testAction("users/highscore3?category={$catId}&rank=15k", ['return' => 'view']);
			// Rank button links should contain the current category
			$this->assertTextContains("category={$catId}&rank=", $this->view);
		}
	}
}
