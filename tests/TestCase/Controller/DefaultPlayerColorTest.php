<?php

App::uses('User', 'Model');

/**
 * Tests that the default_player_color user preference drives the
 * player color and description on the puzzle page.
 */
class DefaultPlayerColorTest extends TestCaseWithAuth
{
	public function testDefaultBlack(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'blackDefault'],
			'tsumego' => ['sets' => [['name' => 'blackDefaultSet', 'num' => 1]], 'description' => '[b]to play.'],
		]);
		$this->login('blackDefault');
		ClassRegistry::init('User')->updateAll(
			['default_player_color' => User::PLAYER_COLOR_BLACK],
			['id' => Auth::getUserID()]
		);

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('besogoPlayerColor = "black"', $this->view);
		$this->assertTextContains('Black to play.', $this->view);
	}

	public function testDefaultWhite(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'whiteDefault'],
			'tsumego' => ['sets' => [['name' => 'whiteDefaultSet', 'num' => 1]], 'description' => '[b]to play.'],
		]);
		$this->login('whiteDefault');
		ClassRegistry::init('User')->updateAll(
			['default_player_color' => User::PLAYER_COLOR_WHITE],
			['id' => Auth::getUserID()]
		);

		$this->testAction(
			'/' . $context->tsumegos[0]['set-connections'][0]['id'],
			['return' => 'view']
		);

		$this->assertTextContains('besogoPlayerColor = "white"', $this->view);
		$this->assertTextContains('White to play.', $this->view);
	}

	public function testDefaultRandomYieldsBothColors(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'randomDefault'],
			'tsumego' => ['sets' => [['name' => 'randomDefaultSet', 'num' => 1]], 'description' => '[b]to play.'],
		]);
		$this->login('randomDefault');
		ClassRegistry::init('User')->updateAll(
			['default_player_color' => User::PLAYER_COLOR_RANDOM],
			['id' => Auth::getUserID()]
		);

		$sawBlack = false;
		$sawWhite = false;
		for ($seed = 0; $seed < 20 && !($sawBlack && $sawWhite); $seed++)
		{
			srand($seed);
			$this->testAction('/' . $context->tsumegos[0]['set-connections'][0]['id'], ['return' => 'view']);
			$sawBlack = $sawBlack || str_contains($this->view, 'besogoPlayerColor = "black"');
			$sawWhite = $sawWhite || str_contains($this->view, 'besogoPlayerColor = "white"');
		}
		$this->assertTrue($sawBlack, 'Random should produce black for some seeds');
		$this->assertTrue($sawWhite, 'Random should produce white for some seeds');
	}
}
