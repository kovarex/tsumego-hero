<?php

use Facebook\WebDriver\WebDriverBy;

require_once(__DIR__ . '/../../ContextPreparator.php');
require_once(__DIR__ . '/../../Browser.php');

class UsersControllerTest extends ControllerTestCase
{
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
}
