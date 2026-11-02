<?php

use App\Model\Table\UserTable;
use App\Utility\Util;

//The theme, sound and level bar cookies come with every request of a signed in player
//(AppController::saveSettingsFromCookies()); the account is written only when one differs from it.
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
}
