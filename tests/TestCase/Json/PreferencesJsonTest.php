<?php

use App\Model\Table\UserTable;

//The JSON the settings page and the board get back when a preference is stored.
class PreferencesJsonTest extends JsonGoldenTestCase
{
	public function testPreferences(): void
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);
		$this->post('/users/playerColorPreference', ['color' => 1]);
		$this->assertJsonGolden('player color');
		$this->post('/users/boardOrientationPreference', ['orientation' => 0]);
		$this->assertJsonGolden('board orientation');
		$this->post('/users/dateFormatPreference', ['format' => UserTable::DATE_FORMAT_BROWSER]);
		$this->assertJsonGolden('date format');
		$this->post('/users/emailOnNewMessagePreference', ['enabled' => '0']);
		$this->assertJsonGolden('email on a new message');
		$this->post('/users/avatarPreference', ['avatar' => UserTable::AVATAR_GRAVATAR]);
		$this->assertJsonGolden('avatar');
	}
}
