<?php

require_once __DIR__ . './../../../src/Utility/BoardComparator.php';

class BoardComparatorTest extends CakeTestCase
{
	public function testCompareEmptyWithEmpty()
	{
		$emptyBoard = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19])'));
		$result = BoardComparator::compare($emptyBoard, $emptyBoard);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareOneStoneWithEmpty()
	{
		$emptyBoard = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19])'));
		$oneStoneBoard = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])'));
		$result = BoardComparator::compare($emptyBoard, $oneStoneBoard);
		$this->assertSame(1, $result->difference);
	}

	public function testCompareOneStoneWithSameOneStone()
	{
		$oneStoneBoard = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])'));
		$result = BoardComparator::compare($oneStoneBoard, $oneStoneBoard);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareOneStoneWithStoneOnDifferentPosition()
	{
		$oneStoneBoardA = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])'));
		$oneStoneBoardB = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[bb])'));
		$result = BoardComparator::compare($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareAnchoredTwoStonesAtDifferentPositions()
	{
		$oneStoneBoardA = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][bb])'));
		$oneStoneBoardB = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])'));
		$result = BoardComparator::compare($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(2, $result->difference);
	}

	public function testCompareStoneWithBoardWhichIsTooSmallToContainIt()
	{
		$oneStoneBoardA = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[2]AB[aa])'));
		$oneStoneBoardB = new SgfResultBoard(SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])'));
		$result = BoardComparator::compare($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(1, $result->difference);
	}
}
