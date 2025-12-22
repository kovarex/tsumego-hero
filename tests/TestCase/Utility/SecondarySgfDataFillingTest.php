<?php

class SecondarySgfDataFillingTest extends CakeTestCase
{
	public function testFillFirstAndCorrectMoves()
	{
		$browser = new Browser();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = ClassRegistry::init('sgf')->find('all');
		$this->assertSame(1, count($sgfs));
		$this->assertSame('B', $sgfs[0]['Sgf']['first_move_color']);
		$this->assertSame('aa', $sgfs[0]['Sgf']['correct_moves']);
	}

	public function testFillWhiteFirstAndMoreCorrectMoves()
	{
		$browser = new Browser();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[gg];B[ba]C[+])(;W[hh]C[+]))']]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = ClassRegistry::init('sgf')->find('all');
		$this->assertSame(1, count($sgfs));
		$this->assertSame('W', $sgfs[0]['Sgf']['first_move_color']);
		$this->assertSame('gghh', $sgfs[0]['Sgf']['correct_moves']);
	}

	public function testSearchSgfsToFillSecondaryDataInto()
	{
		$browser = new Browser();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' =>
				['set_order' => 1, 'sgf' => [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[gg];B[ba]C[+])(;W[hh]C[+]))',
					'first_move_color' => 'W',
					'correct_moves' => 'gghh']],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[jj];B[ba]C[+])(;W[kk]C[+]))']]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = ClassRegistry::init('sgf')->find('all');
		$this->assertSame(2, count($sgfs));
		$this->assertSame('W', $sgfs[1]['Sgf']['first_move_color']);
		$this->assertSame('jjkk', $sgfs[1]['Sgf']['correct_moves']);
	}
}
