<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;

class DateFormatPreferenceTest extends IntegrationTestCase
{
	public function testStoresTheChosenFormat(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'dateFormatStores']]);
		$this->login('dateFormatStores');

		$this->post('/users/dateFormatPreference', ['format' => UserTable::DATE_FORMAT_DAY_MONTH_YEAR]);

		$this->assertSame('{"status":"ok"}', $this->view);
		$this->assertSame(UserTable::DATE_FORMAT_DAY_MONTH_YEAR, (int) $context->reloadUser()['date_format_id']);
	}

	public function testFormatOutOfRangeFallsBackToIso(): void
	{
		foreach ([UserTable::DATE_FORMAT_BROWSER + 1, 0, -1] as $invalid)
		{
			Auth::logout();
			$name = 'dateFormatClamps' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name, 'date_format_id' => UserTable::DATE_FORMAT_DAY_MONTH_YEAR]]);
			$this->login($name);

			$this->post('/users/dateFormatPreference', ['format' => $invalid]);

			$this->assertSame(UserTable::DATE_FORMAT_ISO, (int) $context->reloadUser()['date_format_id']);
		}
	}
}
