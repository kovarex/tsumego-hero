<?php

//Tests that the player_color_setting user preference drives the
//player color and description on the puzzle page.
//"Original" means the player color matches the SGF's first move.
use App\Utility\Auth;

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
		Auth::saveUserField('player_color_setting', User::PLAYER_COLOR_SETTING_ORIGINAL);

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
		Auth::saveUserField('player_color_setting', User::PLAYER_COLOR_SETTING_ORIGINAL);

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
				'sets' => [['name' => '9x9 Test Set', 'num' => 1]],
				'description' => 'White to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[9];W[aa])'],
			],
		]);
		$this->login('smallBoard');
		Auth::saveUserField('player_color_setting', User::PLAYER_COLOR_SETTING_ORIGINAL);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertResponseOk();

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('options.swapColors = true', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testPlayerColorPreferenceStoresValue(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'prefStores']]);
		$this->login('prefStores');

		$this->post('/users/playerColorPreference', ['color' => User::PLAYER_COLOR_SETTING_ORIGINAL]);

		$this->assertSame('{"status":"ok"}', $this->view);
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::PLAYER_COLOR_SETTING_ORIGINAL, (int) $user['User']['player_color_setting']);
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

			$user = ClassRegistry::init('User')->findById($context->user['id']);
			$this->assertSame(User::PLAYER_COLOR_SETTING_RANDOM, (int) $user['User']['player_color_setting']);
		}
	}

	public function testPlayerColorPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->post('/users/playerColorPreference', ['color' => User::PLAYER_COLOR_SETTING_ORIGINAL]);

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
		Auth::saveUserField('player_color_setting', User::PLAYER_COLOR_SETTING_RANDOM);

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
