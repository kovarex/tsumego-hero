<?php

require_once __DIR__ . './../../../src/Utility/BoardComparator.php';

class BoardComparatorTest extends CakeTestCase
{
	public function testCompareEmptyWithEmpty()
	{
		$emptyBoard = SgfParser::process('(;GM[1]FF[4]SZ[19])');
		$result = BoardComparator::compareSimple($emptyBoard, $emptyBoard);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareOneStoneWithEmpty()
	{
		$emptyBoard = SgfParser::process('(;GM[1]FF[4]SZ[19])');
		$oneStoneBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$result = BoardComparator::compareSimple($emptyBoard, $oneStoneBoard);
		$this->assertSame(1, $result->difference);
	}

	public function testCompareOneStoneWithSameOneStone()
	{
		$oneStoneBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$result = BoardComparator::compareSimple($oneStoneBoard, $oneStoneBoard);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareOneStoneWithStoneOnDifferentPosition()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[bb])');
		$result = BoardComparator::compareSimple($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(0, $result->difference);
	}

	public function testCompareAnchoredTwoStonesAtDifferentPositions()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][bb])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])');
		$result = BoardComparator::compareSimple($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(2, $result->difference);
	}

	public function testCompareStoneWithBoardWhichIsTooSmallToContainIt()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[2]AB[aa])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])');
		$result = BoardComparator::compareSimple($oneStoneBoardA, $oneStoneBoardB);
		$this->assertSame(1, $result->difference);
	}
}
