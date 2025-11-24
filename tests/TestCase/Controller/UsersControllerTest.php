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

	public function testSignUp(): void
	{
		// Test that the signup form works correctly with matching passwords
		new ContextPreparator(['user' => null]);
		$userWithBiggestID = ClassRegistry::init('User')->find('first', ['order' => 'id DESC'])['User']['id'];
		$newUsername = 'testuser' . strval($userWithBiggestID + 1);
		$userCountBefore = count(ClassRegistry::init('User')->find('all'));

		$browser = Browser::instance();

        try {
            $browser->get('users/add');
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Unsecured login_uri provided')) {
                // Ignore this exception, CI is running without HTTPS
            } else {
                throw $e; // rethrow other exceptions
            }
        }

		// Fill in the signup form
		$browser->driver->findElement(WebDriverBy::name('data[User][name]'))->sendKeys($newUsername);
		$browser->driver->findElement(WebDriverBy::name('data[User][email]'))->sendKeys($newUsername . '@email.com');
		$browser->driver->findElement(WebDriverBy::name('data[User][password1]'))->sendKeys('hello123');
		$browser->driver->findElement(WebDriverBy::name('data[User][password2]'))->sendKeys('hello123');

		// Submit the form
		$browser->driver->findElement(WebDriverBy::cssSelector('.signin input[type="submit"]'))->click();
		usleep(1000 * 100);

		// Check if user was created successfully
		$userCountAfter = count(ClassRegistry::init('User')->find('all'));
		$newUser = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $newUsername]]);

		// User should be created after signup
		$this->assertNotNull($newUser, 'User should be created after signup');
		$this->assertSame($userCountBefore + 1, $userCountAfter, 'User count should increase by 1');
		$this->assertSame($newUser['User']['name'], $newUsername);
		$this->assertSame($newUser['User']['email'], $newUsername . '@email.com');
	}
}
