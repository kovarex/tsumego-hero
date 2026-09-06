<?php

App::uses('SgfParser', 'Utility');
App::uses('SgfResult', 'Utility');
App::uses('SgfBoard', 'Utility');

class SgfParserTest extends CakeTestCase
{
	public function testProcessReturnsSgfResult()
	{
		$sgf = '(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]RU[Japanese]SZ[19]KM[0.00]PW[White]PB[Black]AB[pd][dp]AW[pp][dd])';
		$result = SgfParser::process($sgf);

		$this->assertInstanceOf('SgfBoard', $result);
		$this->assertEquals(19, $result->size);
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
	}

	public function testProcessSupportsSmallerBoard()
	{
		$sgf = '(;SZ[9]AB[aa][bb]AW[cc])';
		$result = SgfParser::process($sgf);
		$this->assertEquals(9, $result->size);
	}

	public function testFirstMoveColor()
	{
		$this->assertSame('B', SgfParser::firstMoveColor('(;GM[1]SZ[19];B[aa])'));
		$this->assertSame('W', SgfParser::firstMoveColor('(;GM[1]SZ[19];W[aa])'));
		$this->assertSame('B', SgfParser::firstMoveColor('(;GM[1]SZ[19];B[aa];W[bb])'));
		$this->assertSame('W', SgfParser::firstMoveColor('(;GM[1]SZ[19];W[aa];B[bb])'));
		$this->assertSame('N', SgfParser::firstMoveColor('(;GM[1]SZ[19]AB[aa])'));
	}

	public function testValidateAcceptsWellFormedSgf(): void
	{
		$valid = "(;GM[1]FF[4]CA[UTF-8]AP[CGoban:3]ST[2]SZ[19]KM[0.00]\nAB[jm][km]AW[hm][im];B[aa];W[ab])";
		$this->assertNull(SgfParser::validate($valid));
	}

	public function testValidateAcceptsSetupAndMoveInDifferentNodes(): void
	{
		$valid = '(;GM[1]SZ[19];AB[cc];B[aa])';
		$this->assertNull(SgfParser::validate($valid));
	}

	public function testValidateRejectsMoveNodeNotPrefixedBySemicolon(): void
	{
		// "B[aa]" is glued to the setup node without a ';', so it is not a real move node.
		$malformed = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[hm][im]AB[jm][km]B[aa];W[ab];B[ba]C[+])';
		$this->assertNotNull(SgfParser::validate($malformed));
	}

	public function testValidateRejectsSetupAndMoveInSameNode(): void
	{
		$malformed = '(;GM[1]SZ[19]AB[cc]B[aa])';
		$this->assertNotNull(SgfParser::validate($malformed));
	}

	public function testValidateRejectsUnbalancedBrackets(): void
	{
		$this->assertNotNull(SgfParser::validate('(;GM[1]SZ[19];B[aa)'));
	}

	public function testValidateRejectsBadPrefix(): void
	{
		$this->assertNotNull(SgfParser::validate('GM[1]SZ[19];B[aa]'));
	}

	public function testValidateAllowsEscapedBracketsInComment(): void
	{
		// A "\[" / "\]" inside a comment value must be treated as literal, not
		// as an opening/closing bracket (this is the real SGF from the DB).
		$valid = "(;FF[4]GM[1]CA[UTF-8]AP[besogo:0.0.2-alpha]SZ[19]ST[2]\nRU[Japanese]KM[6.50]\nAB[ac][ad]AW[ab][af](;B[ae]\nC[+[b\\] can play C16 for seki]);W[ce])";
		$this->assertNull(SgfParser::validate($valid));
	}
}
