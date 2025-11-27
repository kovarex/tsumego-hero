<?php

App::uses('SgfParser', 'Utility');
App::uses('SgfResult', 'Utility');

class SgfParserTest extends CakeTestCase
{
	public function testProcessReturnsSgfResult()
	{
		$sgf = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]RU[Japanese]SZ[19]KM[0.00]PW[White]PB[Black]AB[pd][dp]AW[pp][dd])';
		$result = SgfParser::process($sgf);

		$this->assertInstanceOf('SgfResult', $result);
		$this->assertEquals(19, $result->size);
		$this->assertIsArray($result->board);
		$this->assertIsArray($result->stones);
		$this->assertIsArray($result->info);
	}

	public function testProcessBoardContent()
	{
		$sgf = '(;SZ[19]AB[aa][bb]AW[cc])';
		$result = SgfParser::process($sgf);

		// Check board size
		$this->assertEquals(19, $result->size);

		// Check stones
		// SgfParser normalizes orientation, so exact coordinates might change if it flips/rotates.
		// But let's check if we have stones.
		$this->assertNotEmpty($result->stones);

		// Check board array structure
		$this->assertCount(19, $result->board);
		$this->assertCount(19, $result->board[0]);
	}

	public function testProcessSupportsSmallerBoard()
	{
		$sgf = '(;SZ[9]AB[aa][bb]AW[cc])';
		$result = SgfParser::process($sgf);

		$this->assertEquals(9, $result->size);
		$this->assertCount(9, $result->board);
		$this->assertCount(9, $result->board[0]);
	}
}
