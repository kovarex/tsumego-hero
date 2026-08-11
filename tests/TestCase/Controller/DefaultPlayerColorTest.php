<?php

App::uses('User', 'Model');

/**
 * Tests that the pref_player_color user preference drives the
 * player color and description on the puzzle page.
 */
class DefaultPlayerColorTest extends TestCaseWithAuth
{
	public function testFromPuzzleUsesSgfColor(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'fromPuzzle'],
			'tsumego' => ['sets' => [['name' => 'fromPuzzleSet', 'num' => 1]], 'description' => '[b]to play.'],
		]);
		$this->login('fromPuzzle');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_FROM_PUZZLE],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_FROM_PUZZLE;

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('options.playerColor = "black"', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testFromPuzzleShowsWhiteWhenSgfIsWhiteFirst(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'fromPuzzleWhite'],
			'tsumego' => [
				'sets' => [['name' => 'fromPuzzleWhiteSet', 'num' => 1]],
				'description' => '[b]to play.',
				'sgf' => ['first_move_color' => 'W'],
			],
		]);
		$this->login('fromPuzzleWhite');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_FROM_PUZZLE],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_FROM_PUZZLE;

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('options.playerColor = "white"', $this->view);
		$this->assertTextContains('White to play.', $this->view);
	}

	public function testRandomYieldsBothColors(): void
	{
		Auth::logout();
		$context = new ContextPreparator([
			'user' => ['name' => 'randomDefault2'],
			'tsumego' => ['sets' => [['name' => 'randomDefaultSet2', 'num' => 1]], 'description' => '[b]to play.'],
		]);
		$this->login('randomDefault2');
		ClassRegistry::init('User')->updateAll(
			['pref_player_color' => User::PREF_PLAYER_COLOR_RANDOM],
			['id' => Auth::getUserID()]
		);
		Auth::getUser()['pref_player_color'] = User::PREF_PLAYER_COLOR_RANDOM;

		$sawBlack = false;
		$sawWhite = false;
		for ($seed = 0; $seed < 20 && !($sawBlack && $sawWhite); $seed++)
		{
			srand($seed);
			$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id'], ['return' => 'view']);
			$sawBlack = $sawBlack || str_contains($this->view, 'options.playerColor = "black"');
			$sawWhite = $sawWhite || str_contains($this->view, 'options.playerColor = "white"');
		}
		$this->assertTrue($sawBlack, 'Random should produce black for some seeds');
		$this->assertTrue($sawWhite, 'Random should produce white for some seeds');
	}
}
