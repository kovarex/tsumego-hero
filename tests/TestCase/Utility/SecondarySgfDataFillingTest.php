<?php

class SecondarySgfDataFillingTest extends CakeTestCase
{
	public function testFillFirstAndCorrectMoves()
	{
		$browser = new Browser();
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$browser->get('tsumegos/setupSgf/' . $context->tsumegos[0]['id']);
		$sgfs = ClassRegistry::init('sgf')->find('all');
		$this->assertSame(1, count($sgfs));
		$this->assertSame('B', $sgfs[0]['first_move_color']);
	}
}
