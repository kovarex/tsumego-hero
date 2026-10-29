<?php

use App\Model\Table\UserTable;
use App\Utility\UploadedAvatar;
use App\Utility\Util;
use Facebook\WebDriver\WebDriverKeys;

//The settings page holds everything a player can change about their own account,
//while the profile stays the public page.
/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class UserSettingsTest extends IntegrationTestCase
{
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

	//The field is type=email and stops at the width of the column, so a bad address only
	//arrives in a hand made request.
	public function testABadAddressIsRejected(): void
	{
		$context = new ContextPreparator(['user' => ['email' => 'old@example.com']]);
		$browser = Browser::instance();

		$tooLong = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 58) . '.com';
		foreach (['', 'not-an-address', 'two@@example.com', $tooLong] as $invalid)
		{
			$browser->get('users/settings');
			$browser->post('users/emailPreference', ['email' => $invalid, 'password' => 'test']);

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
		$this->assertTrue($browser->find('input[name="email"]')->isDisplayed(), 'Enter on the change link must open the form');
	}

	public function testEmailFieldIsLabelledForScreenReaders(): void
	{
		new ContextPreparator(['user' => ['name' => 'labelled']]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->clickId('change-email');

		$label = $browser->driver->executeScript(
			'var field = document.querySelector("#email-form input[name=\'email\']");'
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
		$context = new ContextPreparator(['user' => ['avatar_setting_id' => UserTable::AVATAR_BOARD]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="avatar"][value="' . UserTable::AVATAR_GRAVATAR . '"]')->click();
		$this->waitForUserField($context, 'avatar_setting_id', (string) UserTable::AVATAR_GRAVATAR);

		$browser->get('users/settings');
		$this->assertTrue($browser->find('input[name="avatar"][value="' . UserTable::AVATAR_GRAVATAR . '"]')->isSelected());
	}

	public function testGravatarHintIsShownOnlyForTheGravatarAvatar(): void
	{
		$context = new ContextPreparator(['user' => ['avatar_setting_id' => UserTable::AVATAR_BOARD]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$this->assertTextNotContains('gravatar.com', $browser->driver->getPageSource());

		$browser->find('input[name="avatar"][value="' . UserTable::AVATAR_GRAVATAR . '"]')->click();
		$this->waitForUserField($context, 'avatar_setting_id', (string) UserTable::AVATAR_GRAVATAR);

		$browser->get('users/settings');
		$this->assertTextContains('gravatar.com', $browser->driver->getPageSource());
	}

	//A picture picked in the form is the avatar of the page the browser comes back to.
	public function testAnUploadedPictureIsTheAvatarOfThePage(): void
	{
		$context = new ContextPreparator(['user' => ['avatar_setting_id' => UserTable::AVATAR_BOARD]]);
		$browser = Browser::instance();
		$image = imagecreatetruecolor(300, 200);
		imagefill($image, 0, 0, imagecolorallocate($image, 30, 140, 90));
		ob_start();
		imagepng($image);
		$png = (string) ob_get_clean();

		$browser->get('users/settings');
		// The file is handed to the input by script: the browser runs where the file of the test is not.
		$browser->driver->executeScript("
			var bytes = Uint8Array.from(atob(arguments[0]), function(c) { return c.charCodeAt(0); });
			var input = document.querySelector('#avatar-upload input[type=file]');
			var transfer = new DataTransfer();
			transfer.items.add(new File([bytes], 'me.png', {type: 'image/png'}));
			input.files = transfer.files;
		", [base64_encode($png)]);
		$browser->find('#avatar-upload button[type=submit]')->click();
		$this->waitForUserField($context, 'avatar_setting_id', (string) UserTable::AVATAR_UPLOADED);
		$url = UploadedAvatar::url($context->user['id'], $context->reloadUser()['uploaded_avatar_hash']);

		$browser->waitUntilJs('document.querySelector("#avatar-remove") !== null');
		$this->assertTrue($browser->find('input[name="avatar"][value="' . UserTable::AVATAR_UPLOADED . '"]')->isSelected());
		$this->assertStringContainsString($url, $browser->driver->getPageSource());
		// The browser shows the picture the server sends, not a broken image.
		$browser->waitUntilJs('Array.from(document.images).some(function(image) { return image.getAttribute("src") === ' . json_encode($url) . ' && image.complete && image.naturalWidth === ' . UploadedAvatar::SIZE . '; })');
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
			'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_RANDOM,
			'board_orientation_setting' => UserTable::BOARD_ORIENTATION_SETTING_RANDOM]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="playerColor"][value="' . UserTable::PLAYER_COLOR_SETTING_ORIGINAL . '"]')->click();
		$this->waitForUserField($context, 'player_color_setting', (string) UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$browser->find('input[name="boardOrientation"][value="' . UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL . '"]')->click();
		$this->waitForUserField($context, 'board_orientation_setting', (string) UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL);
	}

	public function testDateFormatIsStored(): void
	{
		$context = new ContextPreparator(['user' => ['date_format_id' => UserTable::DATE_FORMAT_ISO]]);
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->find('input[name="dateFormat"][value="' . UserTable::DATE_FORMAT_DAY_MONTH_YEAR . '"]')->click();
		$this->waitForUserField($context, 'date_format_id', (string) UserTable::DATE_FORMAT_DAY_MONTH_YEAR);
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
		$this->assertFalse($context->reloadUser()['sound']);
		$this->assertTrue($browser->find('input[name="sound"][value="off"]')->isSelected());
		$this->assertStringContainsString('sound-icon2.png', $browser->driver->executeScript('return document.getElementById("soundButtonImage").src;'));
	}

	//A visitor who is not signed in has only the cookie, so it has to reach every
	//page and not just the few that used to be listed by hand.
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
		$this->assertSame(UserTable::THEME_DARK, $context->reloadUser()['theme_id']);
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

		$this->assertSame(UserTable::ROLE_PLAYER, (int) $context->reloadUser()['user_role_id']);
	}

	//Open the change form and send it. The answer lands on the same address, so the
	//wait is for the new document, not for the URL to change.
	private function submitEmailChange(Browser $browser, string $email): void
	{
		$browser->clickId('change-email');
		$field = $browser->find('input[name="email"]');
		$field->clear();
		if ($email !== '')
			$field->sendKeys($email);

		$password = $browser->find('input[name="password"]');
		$password->clear();
		$password->sendKeys('test');

		$browser->driver->executeScript('window.__submitPending = true;');
		$browser->find('#name-and-email-table input[type="submit"]')->click();
		$browser->waitUntilJs('window.__submitPending === undefined && document.readyState === "complete"');
	}

	//The tabs under the player header, as addresses. The header menu of the site is
	//not included, so a link to the settings page in the menu does not count.
	/** @return array<int, string> */
	private function profileTabs(Browser $browser): array
	{
		$hrefs = $browser->driver->executeScript(
			'return Array.from(document.querySelectorAll(".subnav__link")).map(function (a) { return a.getAttribute("href"); });');
		return array_values((array) $hrefs);
	}

	//The settings are fetched one by one, so a value is only stored once the request it started has come back.
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
