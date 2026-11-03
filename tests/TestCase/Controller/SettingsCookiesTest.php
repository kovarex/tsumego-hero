<?php

use App\Model\Table\UserTable;
use App\Utility\BoardSelector;
use App\Utility\Util;

//The theme, sound and level bar cookies carry a change a signed in player made to the account
//(AppController::saveSettingsFromCookies()), which is written only when it differs from the account.
class SettingsCookiesTest extends IntegrationTestCase
{
	//The UPDATE statements the database ran for this connection during the request
	private function updatesDuring(callable $request): int
	{
		$count = fn() => (int) Util::query("SHOW SESSION STATUS LIKE 'Com_update'")[0]['Value'];
		$before = $count();
		$request();
		return $count() - $before;
	}

	public function testCookiesTheAccountAlreadyHasWriteNothing(): void
	{
		new ContextPreparator(['user' => ['name' => 'steadyPlayer', 'sound' => 1]]);
		$this->login('steadyPlayer');
		$_COOKIE['lightDark'] = 'light';
		$_COOKIE['levelBar'] = (string) UserTable::LEVEL_BAR_LEVEL;
		$_COOKIE['sound'] = 'on';

		$this->assertSame(0, $this->updatesDuring(fn() => $this->get('/sites/impressum')));
		$this->assertResponseOk();
	}

	public function testChangedCookiesAreWrittenInOneUpdate(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'changingPlayer', 'sound' => 1]]);
		$this->login('changingPlayer');
		$_COOKIE['lightDark'] = 'dark';
		$_COOKIE['levelBar'] = (string) UserTable::LEVEL_BAR_RATING;
		$_COOKIE['sound'] = 'off';

		$this->assertSame(1, $this->updatesDuring(fn() => $this->get('/sites/impressum')));
		$user = $context->reloadUser();
		$this->assertSame(UserTable::THEME_DARK, (int) $user['theme_id']);
		$this->assertSame(UserTable::LEVEL_BAR_RATING, (int) $user['level_bar_id']);
		$this->assertFalse((bool) $user['sound']);
	}

	//The account is what counts: a cookie that is left in a browser would set the setting back
	//after the player changed it in another browser
	public function testTheCookiesAreDroppedOnceSaved(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'switchingPlayer']]);
		$this->login('switchingPlayer');
		$_COOKIE['lightDark'] = 'dark';
		$_COOKIE['levelBar'] = (string) UserTable::LEVEL_BAR_RATING;
		$_COOKIE['sound'] = 'on';

		$this->get('/sites/impressum');

		foreach (['lightDark', 'levelBar', 'sound'] as $name)
		{
			$this->assertSame('', Util::sentCookie($name)['value'], $name);
			$this->assertLessThan(time(), Util::sentCookie($name)['options']['expires'], $name);
		}
		$this->assertSame(UserTable::THEME_DARK, (int) $context->reloadUser()['theme_id']);
	}

	//The page of a signed in player shows the account's settings and leaves the cookies to the changes
	public function testThePageOfASignedInPlayerDoesNotPutTheSettingsInCookies(): void
	{
		new ContextPreparator(['user' => ['name' => 'quietPlayer', 'sound' => 1]]);
		$this->login('quietPlayer');

		$this->get('/sites/impressum');

		$this->assertResponseOk();
		$this->assertStringNotContainsString('setCookie("lightDark"', $this->view);
		$this->assertStringNotContainsString('setCookie("sound"', $this->view);
	}

	//A browser the player left open in the dark theme does not set the theme back after they
	//switched to the light one elsewhere
	public function testAThemeChangedElsewhereIsKeptByAnotherBrowser(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'twoDevices']]);
		$browser = Browser::instance();
		$browser->get('users/settings');
		$browser->find('input[name="theme"][value="dark"]')->click();
		$browser->get('users/settings');
		$this->assertSame(UserTable::THEME_DARK, (int) $context->reloadUser()['theme_id']);

		Util::execute('UPDATE user SET theme_id = ? WHERE id = ?', [UserTable::THEME_LIGHT, $context->user['id']]);
		$browser->get('users/settings');

		$this->assertSame(UserTable::THEME_LIGHT, (int) $context->reloadUser()['theme_id']);
		$this->assertSame('light', $browser->driver->executeScript('return document.documentElement.dataset.theme;'));
	}

	//A guest's choice of boards is taken from the cookie it came in, and only a cookie that came is dropped
	public function testTheBoardsOfAGuestComeFromTheCookieTheyCameIn(): void
	{
		new ContextPreparator(['user' => null]);
		// what a request sends is kept for the tests across requests, so this one is told apart
		Util::setCookie('boards_bitmask', 'before');
		unset($_COOKIE['boards_bitmask']);
		$this->get('/sites/impressum');
		$this->assertSame('before', Util::sentCookie('boards_bitmask')['value']);
		$this->assertSame(BoardSelector::$DEFAULT_BOARDS_BITMASK, $this->viewVariable('boardsBitmask'));

		$_COOKIE['boards_bitmask'] = '3';
		$this->get('/sites/impressum');
		$this->assertSame('', Util::sentCookie('boards_bitmask')['value']);
		$this->assertSame(3, $this->viewVariable('boardsBitmask'));
	}
}
