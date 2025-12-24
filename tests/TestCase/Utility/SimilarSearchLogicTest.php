<?php

class SimilarSearchLogicTest extends CakeTestCase
{
	public function testSimilarSearchSameProblem()
	{
		$browser = Browser::instance();
		// problem 1 is the source
		// problem 2 has different stones, it shouldn't be found
		// problem 3 is same as the source
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['set_order' => 1, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][df][fd][ff];B[aa];W[ab];B[ba]C[+])'],
			['set_order' => 2, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[de][ed][df][fd][ha][hb][hc][hd];B[aa];W[ab];B[ba]C[+])'],
			['set_order' => 3, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][df][fd][ff];B[aa];W[ab];B[ba]C[+])']]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('findSimilarProblems');
		$tsumegoButtons = $browser->getCssSelect('.setViewButtons1');
		$this->assertSame(2, count($tsumegoButtons));
		$this->assertSame('1', $tsumegoButtons[0]->getText()); // the original problem
		$this->assertSame('3', $tsumegoButtons[1]->getText()); // the third problem same as original
	}

	public function testSimilarSearchMirroredProblemMirrorsCorrectMovesAsWell()
	{
		$browser = Browser::instance();
		// problem 1 is the source
		// problem 2 has different stones, it shouldn't be found
		// problem 3 is same as the source
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			// first sgf is in top left corner X is stone, C is correct move
			//  +-+-+---
			//  + C X
			//  + X X
			//  +
			//
			[
				'set_order' => 1,
				'status' => 'S',
				'sgf'
				=> [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[ba][bb][ab];B[aa]C[+])',
					'correct_moves' => 'aa', // correct is in top left
					'first_move_color' => 'B'
				],
			],
			// second sgf is completely different
			['set_order' => 2, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[de][ed][df][fd][ha][hb][hc][hd];B[aa];W[ab];B[ba]C[+])'],
			// third sgf is in top right corner
			//  -+-+-+-+
			//     X C +
			//     X X +
			//
			[
				'set_order' => 3,
				'status' => 'S',
				'sgf'
				=> [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[ra][rb][sb];B[ss]C[+])',
					'correct_moves' => 'sa', // correct is in top right
					'first_move_color' => 'B'
				],
			]]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('findSimilarProblems');
		$tsumegoButtons = $browser->getCssSelect('.setViewButtons1');
		$this->assertSame(2, count($tsumegoButtons));
		$this->assertSame('1', $tsumegoButtons[0]->getText()); // the original problem
		$this->assertSame('3', $tsumegoButtons[1]->getText()); // the third problem same as original
	}

	public function testSimilarSearchMirroredProblemAnchoredByCorrectMove()
	{
		$browser = Browser::instance();
		// problem 1 is the source
		// problem 2 has different stones, it shouldn't be found
		// problem 3 is same as the source
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			// first sgf is in top left corner X is stone, C is correct move
			//  +-+-+-+-
			//  + X X X
			//  + X C X
			//  + X X X
			//
			[
				'set_order' => 1,
				'status' => 'S',
				'sgf'
				=> [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[aa][ba][ca][ab][cb][ac][bc][cc];B[bb]C[+])',
					'correct_moves' => 'bb', // correct is in the middle
					'first_move_color' => 'B'
				],
			],
			// second sgf is completely different
			['set_order' => 2, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[de][ed][df][fd][ha][hb][hc][hd];B[aa];W[ab];B[ba]C[+])'],
			// Same shape as first sgf, but in left bottom corner, and moved away from the left side by one
			//  +
			//  +   X X X
			//  +   X C X
			//  +   X X X
			//  +-+-+-+-+
			[
				'set_order' => 3,
				'status' => 'S',
				'sgf'
				=> [
					'data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[bq][cq][dq][br][dr][bs][cs][ds];B[cr]C[+])',
					'correct_moves' => 'cr', // correct is in the middle of the shape
					'first_move_color' => 'B'
				],
			]]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('findSimilarProblems');
		$tsumegoButtons = $browser->getCssSelect('.setViewButtons1');
		$this->assertSame(2, count($tsumegoButtons));
		$this->assertSame('1', $tsumegoButtons[0]->getText()); // the original problem
		$this->assertSame('3', $tsumegoButtons[1]->getText()); // the third problem same as original
	}
}
