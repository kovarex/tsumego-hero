<?php

use Cake\ORM\TableRegistry;
use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class SecondarySgfDataFillingTest extends AppTestCase
{
	use RetryTrait;

	public function testFillFirstAndCorrectMoves()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = TableRegistry::getTableLocator()->get('Sgf')->find()->toArray();
		$this->assertSame(1, count($sgfs));
		$this->assertSame('B', $sgfs[0]->first_move_color);
		$this->assertSame('aa', $sgfs[0]->correct_moves);
	}

	public function testFillWhiteFirstAndMoreCorrectMoves()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[gg];B[ba]C[+])(;W[hh]C[+]))']]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = TableRegistry::getTableLocator()->get('Sgf')->find()->toArray();
		$this->assertSame(1, count($sgfs));
		$this->assertSame('W', $sgfs[0]->first_move_color);
		$this->assertSame('gghh', $sgfs[0]->correct_moves);
	}

	public function testSearchSgfsToFillSecondaryDataInto()
	{
		$browser = Browser::instance();
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [[
				'set_order' => 1,
				'sgf' => [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[gg];B[ba]C[+])(;W[hh]C[+]))',
					'first_move_color' => 'W',
					'correct_moves' => 'gghh']],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19](;W[cd])(;W[jj];B[ba]C[+])(;W[kk]C[+]))']]]);
		$browser->get('tsumegos/setupSgf');
		$sgfs = TableRegistry::getTableLocator()->get('Sgf')->find()->orderBy(['id' => 'ASC'])->toArray();
		$this->assertSame(2, count($sgfs));
		$this->assertSame('W', $sgfs[1]->first_move_color);
		$this->assertSame('jjkk', $sgfs[1]->correct_moves);
	}
}
