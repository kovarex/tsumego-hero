<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261108235144_clear_default_goals.php';

//Only a description that is the default goal of the color to move of the problem's current SGF is emptied
class ClearDefaultGoalsMigrationTest extends AppTestCase
{
	public function testOnlyTheDefaultGoalOfTheColorToMoveIsEmptied(): void
	{
		$blackFirst = ['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])', 'first_move_color' => 'B'];
		$whiteFirst = ['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];W[aa];B[ab];W[ba]C[+])', 'first_move_color' => 'W'];
		$context = new ContextPreparator(['tsumegos' => [
			['set_order' => 1, 'description' => 'Black to play.', 'sgf' => $blackFirst],
			['set_order' => 2, 'description' => 'white to play', 'sgf' => $whiteFirst],
			['set_order' => 3, 'description' => 'White to play', 'sgf' => $blackFirst],
			['set_order' => 4, 'description' => 'Black to play and live', 'sgf' => $blackFirst],
			['set_order' => 5, 'description' => 'Black to kill', 'sgf' => $blackFirst]]]);

		self::migration()->up();

		$descriptions = array_map(fn($tsumego) => Util::query('SELECT description FROM tsumego WHERE id = ?', [$tsumego['id']])[0]['description'], $context->tsumegos);
		$this->assertSame(['', '', 'White to play', 'Black to play and live', 'Black to kill'], $descriptions);
	}

	private static function migration(): ClearDefaultGoals
	{
		$config = require ROOT . '/phinx.php';
		$migration = new ClearDefaultGoals('test', 20261108235144);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
