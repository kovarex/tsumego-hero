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
		usleep(200 * 1000);

		// Fill in login form
		$browser->driver->findElement(WebDriverBy::id('UserName'))->sendKeys('testuser');
		$browser->driver->findElement(WebDriverBy::id('password'))->sendKeys('test');
		$browser->driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'))->click();

		// Should be redirected back to highscore page
		usleep(200 * 1000);
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
		usleep(100 * 1000);

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
			'other-tsumegos' => [['rating' => 2600, 'sets' => [['name' => 'set 1', 'num' => 1]]]]]);
		$browser = Browser::instance();
		$browser->get('users/leaderboard');

		// Ivan detkov is alone in the leaderboard, kovarex has no daily_xp
		$table = $browser->driver->findElement(WebDriverBy::cssSelector(".dailyHighscoreTable"));
		$rows = $table->findElements(WebDriverBy::tagName("tr"));
		$this->assertCount(1, $rows);
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov 6k');
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[2]->getText(), '2 solved');

		// Kovarex solves a problem
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('S'); // solve the problem

		// now kovarex is there
		$browser->get('users/leaderboard');
		$table = $browser->driver->findElement(WebDriverBy::cssSelector(".dailyHighscoreTable"));
		$rows = $table->findElements(WebDriverBy::tagName("tr"));
		$this->assertCount(2, $rows);
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'kovarex 6k');
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[2]->getText(), '1 solved');
		$this->assertSame($rows[1]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov 6k');
		$this->assertSame($rows[1]->findElements(WebDriverBy::tagName("td"))[2]->getText(), '2 solved');
	}

	public function testLevelHighscore()
	{
		foreach ([true, false] as $loggedIn)
		{
			$contextParameters = [];
			$contextParameters['user'] = $loggedIn ? ['name' => 'kovarex'] : null;
			$contextParameters['other-users'] = [['name' => 'Ivan Detkov', 'level' => 10]];
			$contextParameters['other-tsumegos'] = [['rating' => 2600, 'sets' => [['name' => 'set 1', 'num' => 1]]]];
			$context = new ContextPreparator($contextParameters);
			$browser = Browser::instance();
			$browser->get('users/highscore');

			// Ivan detkov is alone in the leaderboard
			$table = $browser->driver->findElement(WebDriverBy::cssSelector(".highscoreTable"));
			$rows = $table->findElements(WebDriverBy::tagName("tr"));
			$this->assertCount(3 + ($loggedIn ? 1 : 0), $rows);
			$this->assertSame($rows[2]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov 6k');
			if ($loggedIn)
				$this->assertSame($rows[3]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'kovarex 6k');

		}
	}

	public function testUserContributionsShowsTagAdded()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'atari', 'user' => 'kovarex']]]]]);
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

	public function testUsersRatingLadder()
	{
		new ContextPreparator([
			'other-users' => [
				['name' => 'Ivan Detkov', 'rating' => 2887],
				['name' => 'player3d', 'rating' => 2251],
				['name' => 'player2d', 'rating' => 2249]]]);
		$browser = Browser::instance();
		$browser->get('users/rating');
		$browser->checkTable('.highscoreTable', $this, [
			['Place', 'Name', 'Rank', 'Rating'],
			['#1', 'Ivan Detkov', '', '12d', '2887'],
			['#2', 'player3d', '', '3d', '2251'],
			['#3', 'player2d', '', '2d', '2249']]);
	}

	public function testAchievementsLadder()
	{
		new ContextPreparator([
			'other-users' => [[
				'name' => 'Ivan Detkov',
				'rating' => 2887,
				'achievement-statuses' => [
					['id' => Achievement::PROBLEMS_1000],
					['id' => Achievement::SUPERIOR_ACCURACY, 'value' => 5]]],
				[
					'name' => 'player3d',
					'rating' => 2251,
					'achievement-statuses' => [
						['id' => Achievement::PROBLEMS_1000],
						['id' => Achievement::SUPERIOR_ACCURACY, 'value' => 7]]]]]);
		$browser = Browser::instance();
		$browser->get('users/achievements');
		$browser->checkTable('.highscoreTable', $this, [
			['Place', 'Name', 'Completed'],
			['#1', 'player3d 3d', '8/115'],
			['#2', 'Ivan Detkov 12d', '6/115']]);
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
		$context = new ContextPreparator([
			'user' => [
				'email' => 'current@example.com',
				'level' => 66,
				'xp' => 57,
				'rating' => 2065,
				'solved' => 2],
			'other-tsumegos' => [
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

		$browser = Browser::instance();
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
		$this->assertNotEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->otherTsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 2);

		$browser->driver->executeScript("window.confirm = function(msg) {return true;};");
		$browser->clickId('reset-statuses-button');

		$this->assertEmpty(ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $context->otherTsumegos[1]['id']]]));
		$this->assertSame($context->reloadUser()['solved'], 1);
	}

	public function testTsumegoRatingGraph()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'rating' => '2200',
				'attempt' => ['user_rating' => 2165],
				'status' => 'S']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('showx8');
		$this->assertTextContains('Rating history', $browser->driver->getPageSource());
		$this->assertTextContains('set-1', $browser->driver->getPageSource());
	}

	public function testShowPublishSchedule()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 268, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 673]]]]]);

		$tsumegoToPublish = $context->otherTsumegos[0];
		$publicSetID = $context->otherTsumegos[1]['set-connections'][0]['set_id'];

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
}
