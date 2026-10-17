<?php

use App\Utility\ReadingStones;
use App\Utility\Util;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnitRetry\RetryTrait;

/**
 * The settings page holds everything a player can change about their own account,
 * while the profile stays the public page.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class UserSettingsTest extends ControllerTestCase
{
	use RetryTrait;

	public function testEmailIsChangedFromTheSettingsPage(): void
	{
		$context = new ContextPreparator(['user' => ['email' => 'old@example.com']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertTextContains('old@example.com', $browser->getTableCell('#name-and-email-table', 0, 0)->getText());

		$this->submitEmailChange($browser, 'new@example.com');

		$this->assertSame('old@example.com', $context->reloadUser()['email'], 'The address changed without the confirmation link');
		$this->assertTextContains('new@example.com', $browser->findById('unconfirmed-email')->getText());
	}

	public function testPasswordFormOpensAndClosesOnTheSameLink(): void
	{
		new ContextPreparator(['user' => ['name' => 'passwordtoggle']]);
		$browser = Browser::instance();
		$browser->get('users/settings');

		$this->assertFalse($browser->find('input[name="current"]')->isDisplayed());
		$browser->clickId('change-password');
		$this->assertTrue($browser->find('input[name="current"]')->isDisplayed());
		$this->assertSame('cancel', $browser->findById('change-password')->getText());

		$browser->clickId('change-password');
		$this->assertFalse($browser->find('input[name="current"]')->isDisplayed());
		$this->assertSame('change', $browser->findById('change-password')->getText());
	}

	public function testGoogleAccountIsNotOfferedAnAddressOrPasswordChange(): void
	{
		new ContextPreparator(['user' => ['external_id' => 'google-424242']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertFalse($browser->idExists('change-email'));
		$this->assertFalse($browser->idExists('email-form'));
		$this->assertFalse($browser->idExists('change-password'));
		$this->assertFalse($browser->idExists('password-form'));
	}

	/**
	 * The field is type=email and stops at the width of the column, so a bad address only
	 * arrives in a hand made request.
	 */
	public function testABadAddressIsRejected(): void
	{
		$context = new ContextPreparator(['user' => ['email' => 'old@example.com']]);
		$browser = Browser::instance();

		foreach (['', 'not-an-address', 'two@@example.com', str_repeat('a', 45) . '@example.com'] as $invalid)
		{
			$browser->get('users/settings');
			$browser->post('users/emailPreference', ['data[User][email]' => $invalid, 'data[User][password]' => 'test']);

			$this->assertSame('old@example.com', $context->reloadUser()['email'], 'The address was replaced by "' . $invalid . '"');
			$this->assertTextContains('That is not a valid e-mail address.', $browser->driver->getPageSource());
		}
	}

	public function testGuestIsSentToLogin(): void
	{
		new ContextPreparator(['user' => null, 'other-users' => [['name' => 'someone']]]);
		$browser = Browser::instance();

		$browser->getAnonymous('users/settings');
		$this->assertStringContainsString('/users/login', $browser->driver->getCurrentURL());
	}

	public function testChangeLinkIsReachableByKeyboard(): void
	{
		new ContextPreparator(['user' => ['name' => 'keyboard']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->driver->executeScript('document.getElementById("change-email").focus();');
		$this->assertSame('change-email', $browser->driver->executeScript('return document.activeElement.id;'), 'The change link can not be focused');

		$browser->driver->switchTo()->activeElement()->sendKeys(WebDriverKeys::ENTER);
		$this->assertTrue($browser->find('input[name="data[User][email]"]')->isDisplayed(), 'Enter on the change link must open the form');
	}

	public function testEmailFieldIsLabelledForScreenReaders(): void
	{
		new ContextPreparator(['user' => ['name' => 'labelled']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->clickId('change-email');

		$label = $browser->driver->executeScript(
			'var field = document.querySelector("#email-form input[name=\'data[User][email]\']");'
			. 'if (field.getAttribute("aria-label")) return field.getAttribute("aria-label");'
			. 'return field.labels.length ? field.labels[0].textContent.trim() : "";');

		$this->assertSame('New e-mail', $label, 'The address field has no name a screen reader could read');
	}

	public function testSettingsTabIsOnlyOnOwnProfile(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'target']]]);
		$browser = Browser::instance();

		$browser->get('users/view/' . $context->user['id']);
		$this->assertContains('/users/settings', $this->profileTabs($browser));

		$browser->get('users/view/' . $context->otherUsers[0]['id']);
		$this->assertNotContains('/users/settings', $this->profileTabs($browser));
	}

	public function testSettingsTabIsOnEveryOwnProfileTab(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'target']]]);
		$browser = Browser::instance();

		foreach (['users/view/', 'users/solveHistory/', 'tags/user/', 'achievements/user/'] as $page)
		{
			$browser->get($page . $context->user['id']);
			$this->assertContains('/users/settings', $this->profileTabs($browser), 'No Settings tab on ' . $page);

			$browser->get($page . $context->otherUsers[0]['id']);
			$this->assertNotContains('/users/settings', $this->profileTabs($browser), 'Settings tab of another player on ' . $page);
		}
	}

	public function testAvatarIsStoredAndShownAsChosen(): void
	{
		$context = new ContextPreparator(['user' => ['pref_avatar' => User::AVATAR_BOARD]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="avatar"][value="' . User::AVATAR_GRAVATAR . '"]')->click();
		$this->waitForUserField($context, 'pref_avatar', (string) User::AVATAR_GRAVATAR);

		$browser->get('users/settings');
		$this->assertTrue($browser->find('input[name="avatar"][value="' . User::AVATAR_GRAVATAR . '"]')->isSelected());
	}

	public function testGravatarHintIsShownOnlyForTheGravatarAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['pref_avatar' => User::AVATAR_BOARD]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertTextNotContains('gravatar.com', $browser->driver->getPageSource());

		$browser->find('input[name="avatar"][value="' . User::AVATAR_GRAVATAR . '"]')->click();
		$this->waitForUserField($context, 'pref_avatar', (string) User::AVATAR_GRAVATAR);

		$browser->get('users/settings');
		$this->assertTextContains('gravatar.com', $browser->driver->getPageSource());
	}

	public function testGoogleAccountHasNoLoginName(): void
	{
		new ContextPreparator(['user' => ['name' => 'Google Player', 'external_id' => 'google-settings']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertTextNotContains('Username:', $browser->driver->getPageSource());
	}

	public function testPlayerColorAndBoardOrientationAreStored(): void
	{
		$context = new ContextPreparator(['user' => [
			'pref_player_color' => User::PREF_PLAYER_COLOR_RANDOM,
			'pref_board_orientation' => User::PREF_BOARD_ORIENTATION_RANDOM]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="playerColor"][value="' . User::PREF_PLAYER_COLOR_ORIGINAL . '"]')->click();
		$this->waitForUserField($context, 'pref_player_color', (string) User::PREF_PLAYER_COLOR_ORIGINAL);

		$browser->find('input[name="boardOrientation"][value="' . User::PREF_BOARD_ORIENTATION_ORIGINAL . '"]')->click();
		$this->waitForUserField($context, 'pref_board_orientation', (string) User::PREF_BOARD_ORIENTATION_ORIGINAL);
	}

	public function testDateFormatIsStored(): void
	{
		$context = new ContextPreparator(['user' => ['date_format_id' => User::DATE_FORMAT_ISO]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="dateFormat"][value="' . User::DATE_FORMAT_DAY_MONTH_YEAR . '"]')->click();
		$this->waitForUserField($context, 'date_format_id', (string) User::DATE_FORMAT_DAY_MONTH_YEAR);
	}

	public function testReadingTrainerDefaultsToYourStonesLeaving(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'readingDefault']]);
		$browser = Browser::instance();

		$browser->get('users/settings');

		$this->assertTrue($browser->find('input[name="readingPlayed"][value="' . ReadingStones::Hidden->value . '"]')->isSelected(),
			'The stones the player plays leave by default');
		$this->assertTrue($browser->find('input[name="readingSetup"][value="' . ReadingStones::Normal->value . '"]')->isSelected(),
			'The stones of the problem stay by default');

		$user = $context->reloadUser();
		$this->assertSame(ReadingStones::Hidden->value, (int) $user['pref_reading_played']);
		$this->assertSame(ReadingStones::Normal->value, (int) $user['pref_reading_setup']);
	}

	public function testBothSidesCannotStayVisible(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'readingBoth']]);
		$browser = Browser::instance();

		$browser->get('users/settings');

		// The default keeps the stones of the problem. Picking Normal for the other
		// side would show the whole board, so that side of the pair turns Hidden.
		$browser->find('input[name="readingPlayed"][value="' . ReadingStones::Normal->value . '"]')->click();

		$deadline = microtime(true) + 5;
		do
		{
			$user = $context->reloadUser();
			if ((int) $user['pref_reading_setup'] === ReadingStones::Hidden->value)
				break;
			usleep(100000);
		}
		while (microtime(true) < $deadline);

		$this->assertSame(ReadingStones::Normal->value, (int) $user['pref_reading_played'], 'The side the player picked keeps the value it got');
		$this->assertSame(ReadingStones::Hidden->value, (int) $user['pref_reading_setup'], 'The side that was left alone turns Hidden');
		$this->assertTrue($browser->find('input[name="readingSetup"][value="' . ReadingStones::Hidden->value . '"]')->isSelected(),
			'The page shows the side that was switched');
	}

	public function testOnlyAnAdminCanStepDown(): void
	{
		new ContextPreparator(['user' => ['name' => 'plain', 'admin' => false]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertStringNotContainsString('/users/demote_admin', $browser->driver->getPageSource());
	}

	public function testSoundChoiceIsKeptOnTheNextPage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'sound', 'sound' => 1]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="sound"][value="off"]')->click();

		// The choice is carried to the account by the next request
		$browser->get('users/settings');
		$this->assertSame(0, $context->reloadUser()['sound']);
		$this->assertTrue($browser->find('input[name="sound"][value="off"]')->isSelected());
		$this->assertStringContainsString('sound-icon2.png', $browser->driver->executeScript('return document.getElementById("soundButtonImage").src;'));
	}

	/**
	 * A visitor who is not signed in has only the cookie, so it has to reach every
	 * page and not just the few that used to be listed by hand.
	 */
	public function testTheSoundChoiceReachesPagesOutsideTheSettings(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();

		$browser->getAnonymous('sets');
		$browser->clickId('soundButton');

		$browser->getAnonymous('comments');
		$this->assertStringContainsString('sound=off', $browser->driver->executeScript('return document.cookie;'));
	}

	public function testThemeChoiceIsKeptOnTheNextPage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'theme']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="theme"][value="dark"]')->click();
		$browser->get('users/settings');

		$this->assertSame('dark', $browser->driver->executeScript('return document.documentElement.dataset.theme;'));
		$this->assertTrue($browser->find('input[name="theme"][value="dark"]')->isSelected());
		$this->assertSame(1, $context->reloadUser()['lastLight'], 'dark is stored as 1');
	}

	public function testResetIsNotOfferedBeforeEnoughProblemsAreSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'beginner'],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'updated' => date('Y-m-d H:i:s', strtotime('-2 years'))]],
				['set_order' => 2],
				['set_order' => 3],
				['set_order' => 4],
			],
		]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertStringContainsString('btn--disabled', $browser->findById('reset-statuses-button')->getAttribute('class'));
		$this->assertTextContains('Available after', $browser->driver->getPageSource());

		// The action itself refuses too, so the disabled button is not the only guard
		$browser->post('users/deleteOldTsumegoStatuses');
		$rows = Util::query('SELECT COUNT(*) AS total FROM tsumego_status WHERE user_id = ?', [$context->user['id']]);
		$this->assertSame(1, (int) $rows[0]['total']);
	}

	public function testDeleteAccountIsReachedFromTheSettingsPage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'leaving']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('a[href="/users/delete_account"]')->click();
		$browser->clickId('send-deletion-email-button');

		$browser->findById('deletion-email-sent');
		$this->assertNull($context->reloadUser()['deleted'], 'The account is deleted only after the emailed link is confirmed');
	}

	public function testAdminIsReachedFromTheSettingsPageToStepDown(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'formeradmin', 'admin' => true]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('a[href="/users/demote_admin"]')->click();

		$browser->findById('UserDemote')->sendKeys('test');
		$browser->find('input[type="submit"]')->click();
		$browser->waitUntilJs('location.href.includes("/users/view/' . $context->user['id'] . '")');

		$this->assertSame(0, (int) $context->reloadUser()['isAdmin']);
	}

	/**
	 * Open the change form and send it. The answer lands on the same address, so the
	 * wait is for the new document, not for the URL to change.
	 */
	private function submitEmailChange(Browser $browser, string $email): void
	{
		$browser->clickId('change-email');
		$field = $browser->find('input[name="data[User][email]"]');
		$field->clear();
		if ($email !== '')
			$field->sendKeys($email);

		$password = $browser->find('input[name="data[User][password]"]');
		$password->clear();
		$password->sendKeys('test');

		$browser->driver->executeScript('window.__submitPending = true;');
		$browser->find('#name-and-email-table input[type="submit"]')->click();
		$browser->waitUntilJs('window.__submitPending === undefined && document.readyState === "complete"');
	}

	/**
	 * The tabs under the player header, as addresses. The header menu of the site is
	 * not included, so a link to the settings page in the menu does not count.
	 *
	 * @return array<int, string>
	 */
	private function profileTabs(Browser $browser): array
	{
		$hrefs = $browser->driver->executeScript(
			'return Array.from(document.querySelectorAll(".subnav__link")).map(function (a) { return a.getAttribute("href"); });');
		return array_values((array) $hrefs);
	}

	/**
	 * The settings are fetched one by one, so a value is only stored once the
	 * request it started has come back.
	 */
	private function waitForUserField(ContextPreparator $context, string $field, string $expected): void
	{
		for ($i = 0; $i < 100; $i++)
		{
			if ((string) $context->reloadUser()[$field] === $expected)
				break;
			usleep(100000);
		}
		$this->assertSame($expected, (string) $context->reloadUser()[$field], 'The settings page did not store ' . $field);
	}
}
