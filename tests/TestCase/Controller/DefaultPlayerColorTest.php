<?php

App::uses('User', 'Model');

/**
 * Tests that the pref_player_color user preference drives the
 * player color and description on the puzzle page.
 * "Original" means the player color matches the SGF's first move.
 */
class DefaultPlayerColorTest extends TestCaseWithAuth
{
	public function testOriginalUsesSgfColor(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'originalUser'],
			'tsumego' => ['sets' => [['name' => 'originalUserSet', 'num' => 1]], 'description' => '[b]to play.', 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];B[aa])']],
		]);
		$this->login('originalUser');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_ORIGINAL;

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testOriginalShowsWhiteWhenSgfIsWhiteFirst(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'originalUserWhite'],
			'tsumego' => [
				'sets' => [['name' => 'originalUserWhiteSet', 'num' => 1]],
				'description' => '[b]to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[19];W[aa])'],
			],
		]);
		$this->login('originalUserWhite');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_ORIGINAL;

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('options.playerColor = "white"', $this->view);
		$this->assertTextContains('White to play.', $this->view);
	}

	public function testOriginalForcesBlackOnSmallBoard(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'smallBoard'],
			'tsumego' => [
				'sets' => [['name' => '9x9 Test Set', 'num' => 1]],
				'description' => '[b]to play.',
				'sgf' => ['data' => '(;GM[1]FF[4]SZ[9];W[aa])'],
			],
		]);
		$this->login('smallBoard');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_ORIGINAL;

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testPlayerColorPreferenceStoresValue(): void
	{
		Auth::logout();
		$context = new ContextPreparator(['user' => ['name' => 'prefStores']]);
		$this->login('prefStores');

		$result = $this->testAction('/users/playerColorPreference', [
			'method' => 'post',
			'data' => ['color' => User::PREF_PLAYER_COLOR_ORIGINAL],
			'return' => 'contents',
		]);

		$this->assertSame('{"status":"ok"}', $result);
		$user = ClassRegistry::init('User')->findById($context->user['id']);
		$this->assertSame(User::PREF_PLAYER_COLOR_ORIGINAL, (int) $user['User']['pref_player_color']);
	}

	public function testPlayerColorPreferenceClampsOutOfRangeValue(): void
	{
		foreach ([99, -5, 2] as $invalid)
		{
			Auth::logout();
			$name = 'prefClamps' . $invalid;
			$context = new ContextPreparator(['user' => ['name' => $name]]);
			$this->login($name);

			$this->testAction('/users/playerColorPreference', [
				'method' => 'post',
				'data' => ['color' => $invalid],
			]);

			$user = ClassRegistry::init('User')->findById($context->user['id']);
			$this->assertSame(User::PREF_PLAYER_COLOR_RANDOM, (int) $user['User']['pref_player_color']);
		}
	}

	public function testPlayerColorPreferenceRequiresLogin(): void
	{
		Auth::logout();

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/users/playerColorPreference', [
			'method' => 'post',
			'data' => ['color' => User::PREF_PLAYER_COLOR_ORIGINAL],
		]);
	}
}
