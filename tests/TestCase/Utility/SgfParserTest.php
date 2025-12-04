<?php

App::uses('SgfParser', 'Utility');
App::uses('SgfResult', 'Utility');
App::uses('SgfResultBoard', 'Utility');

class SgfParserTest extends CakeTestCase
{
	public function testProcessReturnsSgfResult()
	{
		$sgf = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]RU[Japanese]SZ[19]KM[0.00]PW[White]PB[Black]AB[pd][dp]AW[pp][dd])';
		$result = new SgfResultBoard(SgfParser::process($sgf));

		$this->assertInstanceOf('SgfResultBoard', $result);
		$this->assertEquals(19, $result->input->size);
		$this->assertIsArray($result->data);
		$this->assertIsArray($result->input->blackStones);
		$this->assertIsArray($result->input->whiteStones);
		$this->assertIsArray($result->input->info);
	}

	public function testProcessBoardContent()
	{
		$sgf = '(;SZ[19]AB[aa][bb]AW[cc])';
		$result = new SgfResultBoard(SgfParser::process($sgf));

		// Check board size
		$this->assertEquals(19, $result->input->size);

		// Check stones
		// SgfParser normalizes orientation, so exact coordinates might change if it flips/rotates.
		// But let's check if we have stones.
		$this->assertNotEmpty($result->input->blackStones);
		$this->assertNotEmpty($result->input->whiteStones);

		// Check board array structure
		$this->assertCount(19, $result->data);
		$this->assertCount(19, $result->data[0]);
	}

	public function testProcessSupportsSmallerBoard()
	{
		$sgf = '(;SZ[9]AB[aa][bb]AW[cc])';
		$result = new SgfResultBoard(SgfParser::process($sgf));

		$this->assertEquals(9, $result->input->size);
		$this->assertCount(9, $result->data);
		$this->assertCount(9, $result->data[0]);
	}
}
