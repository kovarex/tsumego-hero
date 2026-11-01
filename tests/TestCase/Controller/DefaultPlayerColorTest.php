<?php

//Tests that the player_color_setting user preference drives the
//player color and description on the puzzle page.
//"Original" means the player color matches the SGF's first move.
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

class DefaultPlayerColorTest extends IntegrationTestCase
{
	public function testOriginalUsesSgfColor(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'originalUser'],
			'tsumego' => ['sets' => [['name' => 'originalUserSet', 'num' => 1]], 'description' => 'Black to play.', 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];B[aa])']],
		]);
		$this->login('originalUser');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('options.swapColors = false', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testOriginalShowsWhiteWhenSgfIsWhiteFirst(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'originalUserWhite'],
			'tsumego' => [
				'sets' => [['name' => 'originalUserWhiteSet', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];W[aa])'],
			],
		]);
		$this->login('originalUserWhite');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertTextContains('options.playerColor = "white"', $this->view);
		$this->assertTextContains('options.swapColors = false', $this->view);
		$this->assertTextContains('White to play.', $this->view);
	}

	public function testOriginalForcesBlackOnSmallBoard(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'smallBoard'],
			'tsumego' => [
				'sets' => [['name' => 'Small Board Set', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[9];W[aa])'],
			],
		]);
		$this->login('smallBoard');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('options.swapColors = true', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	//A problem marked played_as_black puts the player on black whatever the setting, as its question
	//names a color, whichever collection it is played from
	public function testOriginalForcesBlackInAProblemPlayedAsBlack(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'playedAsBlack'],
			'tsumego' => [
				'played_as_black' => 1,
				'sets' => [['name' => 'First collection', 'num' => 1], ['name' => 'Second collection', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];W[aa])'],
			],
		]);
		$this->login('playedAsBlack');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		foreach ($context->tsumegos[0]['set-connections'] as $setConnection)
		{
			$this->get('/' . $setConnection['id']);
			$this->assertResponseOk();
			$this->assertTextContains('options.playerColor = "black"', $this->view);
			$this->assertTextContains('options.swapColors = true', $this->view);
			$this->assertTextContains('Black to play.', $this->view);
		}
	}

	//The flag is on the problem: a problem without it in one of the sets the old code picked by id
	//(109 and others) keeps the player's setting
	public function testAProblemInAnOldPlayedAsBlackSetKeepsTheSetting(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'oldSet'],
			'tsumego' => [
				'sets' => [['name' => 'Old set', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];W[aa])'],
			],
		]);
		$setID = (int) $context->tsumegos[0]['sets'][0]['id'];
		Util::execute('SET FOREIGN_KEY_CHECKS = 0');
		Util::execute('UPDATE set_connection SET set_id = 109 WHERE set_id = ?', [$setID]);
		Util::execute('UPDATE `set` SET id = 109 WHERE id = ?', [$setID]);
		Util::execute('SET FOREIGN_KEY_CHECKS = 1');
		$this->login('oldSet');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();
		$this->assertTextContains('options.playerColor = "white"', $this->view);
		$this->assertTextContains('options.swapColors = false', $this->view);
	}

	//The board size comes from the problem's SGF: a set title naming a board size changes nothing
	public function testSetTitleNamingABoardSizeDoesNotForceBlack(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'titleSize'],
			'tsumego' => [
				'sets' => [['name' => 'Life and death 9x9', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];W[aa])'],
			],
		]);
		$this->login('titleSize');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertTextContains('options.playerColor = "white"', $this->view);
		$this->assertTextContains('options.swapColors = false', $this->view);
	}

	public function testPlayerColorPreferenceStoresValue(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'prefStores']]);
		$this->login('prefStores');

		$this->post('/users/playerColorPreference', ['color' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL]);

		$this->assertSame('{"status":"ok"}', $this->view);
		$user = UserTable::row((int) $context->user['id']);
		$this->assertSame(UserTable::PLAYER_COLOR_SETTING_ORIGINAL, (int) $user['player_color_setting']);
	}

	public function testPlayerColorPreferenceClampsOutOfRangeValue(): void
	{
		foreach ([99, -5, 2] as $invalid)
		{
			Auth::logout();
			$name = 'prefClamps' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name]]);
			$this->login($name);

			$this->post('/users/playerColorPreference', ['color' => $invalid]);

			$user = UserTable::row((int) $context->user['id']);
			$this->assertSame(UserTable::PLAYER_COLOR_SETTING_RANDOM, (int) $user['player_color_setting']);
		}
	}

	public function testPlayerColorPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->post('/users/playerColorPreference', ['color' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL]);

		$this->assertResponseCode(401);
	}

	public function testRandomSwapMatchesChosenSide(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'randomSwap'],
			'tsumego' => ['sets' => [['name' => 'randomSwapSet', 'num' => 1]], 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];B[aa])']],
		]);
		$this->login('randomSwap');
		Auth::saveUserField('player_color_setting', UserTable::PLAYER_COLOR_SETTING_RANDOM);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		preg_match('/options\.playerColor = "(black|white)"/', $this->view, $playerColorMatch);
		preg_match('/options\.swapColors = (true|false)/', $this->view, $swapColorsMatch);

		$this->assertNotEmpty($playerColorMatch);
		$this->assertNotEmpty($swapColorsMatch);
		$this->assertSame(
			$playerColorMatch[1] === 'white' ? 'true' : 'false',
			$swapColorsMatch[1]
		);
	}
}
