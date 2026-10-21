<?php

use App\Utility\Auth;

class DateFormatPreferenceTest extends TestCaseWithAuth
{
	public function testStoresTheChosenFormat(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'dateFormatStores']]);
		$this->login('dateFormatStores');

		$result = $this->testAction('/users/dateFormatPreference', [
			'method' => 'post',
			'data' => ['format' => User::DATE_FORMAT_DAY_MONTH_YEAR],
			'return' => 'contents',
		]);

		$this->assertSame('{"status":"ok"}', $result);
		$this->assertSame(User::DATE_FORMAT_DAY_MONTH_YEAR, (int) $context->reloadUser()['date_format_id']);
	}

	public function testFormatOutOfRangeFallsBackToIso(): void
	{
		foreach ([User::DATE_FORMAT_BROWSER + 1, 0, -1] as $invalid)
		{
			Auth::logout();
			$name = 'dateFormatClamps' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name, 'date_format_id' => User::DATE_FORMAT_DAY_MONTH_YEAR]]);
			$this->login($name);

			$this->testAction('/users/dateFormatPreference', [
				'method' => 'post',
				'data' => ['format' => $invalid],
			]);

			$this->assertSame(User::DATE_FORMAT_ISO, (int) $context->reloadUser()['date_format_id']);
		}
	}
}
