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
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov');
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[2]->getText(), '2 solved');

		// Kovarex solves a problem
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		usleep(1000 * 100);
		$browser->driver->executeScript("displayResult('S')"); // solve the problem

		// now kovarex is there
		$browser->get('users/leaderboard');
		$table = $browser->driver->findElement(WebDriverBy::cssSelector(".dailyHighscoreTable"));
		$rows = $table->findElements(WebDriverBy::tagName("tr"));
		$this->assertCount(2, $rows);
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'kovarex');
		$this->assertSame($rows[0]->findElements(WebDriverBy::tagName("td"))[2]->getText(), '1 solved');
		$this->assertSame($rows[1]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov');
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
			$this->assertSame($rows[2]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'Ivan Detkov');
			if ($loggedIn)
				$this->assertSame($rows[3]->findElements(WebDriverBy::tagName("td"))[1]->getText(), 'kovarex');

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
}
