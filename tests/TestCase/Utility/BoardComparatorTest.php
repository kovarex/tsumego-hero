<?php

require_once __DIR__ . './../../../src/Utility/BoardComparator.php';

class BoardComparatorTest extends CakeTestCase
{
	public function testCompareEmptyWithEmpty()
	{
		$emptyBoard = SgfParser::process('(;GM[1]FF[4]SZ[19])');
		$result = BoardComparator::compare($emptyBoard->stones, 'N', [], $emptyBoard->stones, 'N', []);
		$this->assertNull($result); // empty board doesn't match
	}

	public function testCompareOneStoneWithEmpty()
	{
		$emptyBoard = SgfParser::process('(;GM[1]FF[4]SZ[19])');
		$oneStoneBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$result = BoardComparator::compare($emptyBoard->stones, 'N', [], $oneStoneBoard->stones, 'N', []);
		$this->assertNull($result); // empty board doesn't match
	}

	public function testCompareOneStoneWithSameOneStone()
	{
		$oneStoneBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$result = BoardComparator::compare($oneStoneBoard->stones, 'N', [], $oneStoneBoard->stones, 'N', []);
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}

	public function testCompareOneStoneWithStoneOnDifferentPosition()
	{
		// just one stone will be shifted to the same position and the result diff will be empty again
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[bb])');
		$result = BoardComparator::compare($oneStoneBoardA->stones, 'N', [], $oneStoneBoardB->stones, 'N', []);
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}

	public function testCompareAnchoredTwoStonesAtDifferentPositions()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][bb])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])');
		$result = BoardComparator::compare($oneStoneBoardA->stones, 'N', [], $oneStoneBoardB->stones, 'N', []);
		$this->assertSame(2, $result->difference);
		$this->assertSame('bbcc', $result->diff);
	}

	public function testCompareStoneWithBoardWhichIsTooSmallToContainIt()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[2]AB[aa])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][cc])');
		$result = BoardComparator::compare($oneStoneBoardA->stones, 'N', [], $oneStoneBoardB->stones, 'N', []);
		$this->assertSame(1, $result->difference);
		$this->assertSame('cc', $result->diff);
	}

	// the correct move anchors the diff, so the one stone boards will start to be different
	public function testCompareOneStoneWithOneStoneAtOtherPositionButCorrectMoveIsProvided()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[bb])');
		$result = BoardComparator::compare(
			$oneStoneBoardA->stones,
			'B',
			SgfBoard::decodePositionString('cc'),
			$oneStoneBoardB->stones,
			'B',
			SgfBoard::decodePositionString('cc'));
		$this->assertSame(2, $result->difference);
		$this->assertSame('aabb', $result->diff);
	}

	// the correct move anchors the diff, so the one stone boards will start to be different
	// but in this case, it should be just mirror around diagonal axis
	public function testMatchTwoPositionsWithSameRelativePositionToCorrectMove()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ab])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ba])');
		$result = BoardComparator::compare(
			$oneStoneBoardA->stones,
			'B',
			SgfBoard::decodePositionString('cc'),
			$oneStoneBoardB->stones,
			'B',
			SgfBoard::decodePositionString('cc'));
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}

	// the correct move anchors the diff, so the one stone boards will start to be different
	public function testDiscardTwoPositionsWithSameRelativePositionToCorrectMoveWhenFirstMoveForcesColorSwitch()
	{
		$oneStoneBoardA = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ab])');
		$oneStoneBoardB = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ba])');
		$result = BoardComparator::compare(
			$oneStoneBoardA->stones,
			'B',
			SgfBoard::decodePositionString('cc'),
			$oneStoneBoardB->stones,
			'W',
			SgfBoard::decodePositionString('cc'));
		// the difference is one stone, as  the mirror can match the stones on same position
		// so it is just a color diff
		$this->assertSame(1, $result->difference);
		$this->assertSame('ba', $result->diff);
	}

	// the shape is the same, but the position of the correct move makes it compatibile or not
	public function testDifferentCorrectMovesMakesSameShapeDifferent()
	{
		foreach (['aa', 'ab', 'ba', 'bb'] as $otherCorrectMove)
		{
			$fenceShape = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ca][cb][cc][bc][ac])');
			$result = BoardComparator::compare(
				$fenceShape->stones,
				'B',
				SgfBoard::decodePositionString('aa'),
				$fenceShape->stones,
				'B',
				SgfBoard::decodePositionString($otherCorrectMove));
			if ($otherCorrectMove === 'aa')
			{
				$this->assertNotNull($result);
				$this->assertSame(0, $result->difference);
				$this->assertSame('', $result->diff);
			}
			else
				$this->assertNull($result);
		}
	}

	// the position is the same relative to the correct move, just horizontally switched
	public function testMatch2HorizontallyMirroredPositions()
	{
		$blackIsOnTheLeftBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ca]AW[ea])');
		$blackIsOnTheRightBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ea]AW[ca])');
		$result = BoardComparator::compare(
			$blackIsOnTheLeftBoard->stones,
			'B',
			SgfBoard::decodePositionString('db'),
			$blackIsOnTheRightBoard->stones,
			'B',
			SgfBoard::decodePositionString('db'));
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}
}
