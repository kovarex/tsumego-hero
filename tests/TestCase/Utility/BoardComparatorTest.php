<?php

use App\Utility\BoardComparator;
use App\Utility\BoardPosition;
use App\Utility\SgfBoard;
use App\Utility\SgfParser;

class BoardComparatorTest extends AppTestCase
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

	// the position is the same relative to the correct move, just horizontally switched
	public function testMatch2VerticallyMirroredPositions()
	{
		$blackIsOnTheLeftBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ac]AW[ae])');
		$blackIsOnTheRightBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ae]AW[ac])');
		$result = BoardComparator::compare(
			$blackIsOnTheLeftBoard->stones,
			'B',
			SgfBoard::decodePositionString('bd'),
			$blackIsOnTheRightBoard->stones,
			'B',
			SgfBoard::decodePositionString('bd'));
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}

	// the position is the same relative to the correct move, just horizontally switched
	public function testMatch2VerticallyMirroredPositionsShowsCorrectDiff()
	{
		$blackIsOnTheLeftBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ac]AW[ae])');
		$blackIsOnTheRightBoard = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ae]AW[ac][af])');
		$result = BoardComparator::compare(
			$blackIsOnTheLeftBoard->stones,
			'B',
			SgfBoard::decodePositionString('bd'),
			$blackIsOnTheRightBoard->stones,
			'B',
			SgfBoard::decodePositionString('bd'));
		$this->assertSame(1, $result->difference);
		$this->assertSame('af', $result->diff);
	}

	// without a first move, the problem can be either color's, so the colors switched are tried too
	public function testMatchColorSwitchedPositionWithCorrectMoveWhenNeitherHasAFirstMove()
	{
		$black = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ca][cb]AW[da])');
		$white = SgfParser::process('(;GM[1]FF[4]SZ[19]AW[ca][cb]AB[da])');
		$result = BoardComparator::compare(
			$black->stones,
			'N',
			SgfBoard::decodePositionString('cc'),
			$white->stones,
			'N',
			SgfBoard::decodePositionString('cc'));
		$this->assertSame(0, $result->difference);
		$this->assertSame('', $result->diff);
	}

	// the same problem with the colors switched and the other color to play is the same problem
	public function testMatchColorSwitchedPositionWhenTheOtherColorPlaysFirst()
	{
		$black = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[ca][cb]AW[da])');
		$white = SgfParser::process('(;GM[1]FF[4]SZ[19]AW[ca][cb]AB[da])');
		$result = BoardComparator::compare(
			$black->stones,
			'B',
			SgfBoard::decodePositionString('cc'),
			$white->stones,
			'W',
			SgfBoard::decodePositionString('cc'));
		$this->assertSame(0, $result->difference);
	}

	public function testPositionsDifferingInMoreThanFiveStonesAreNotSimilar()
	{
		$a = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa])');
		$b = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][ba][ca][da][ea][fa][ga])');
		$this->assertNull(BoardComparator::compare($a->stones, 'B', SgfBoard::decodePositionString('ab'), $b->stones, 'B', SgfBoard::decodePositionString('ab')));
		$c = SgfParser::process('(;GM[1]FF[4]SZ[19]AB[aa][ba][ca][da][ea][fa])');
		$this->assertSame(5, BoardComparator::compare($a->stones, 'B', SgfBoard::decodePositionString('ab'), $c->stones, 'B', SgfBoard::decodePositionString('ab'))->difference);
	}

	//A corner problem without any symmetry of its own, in SGF coordinates of a 19x19 board: black is
	//to play, white around it
	private const CORNER_BLACK = ['ac', 'bc', 'cb', 'db', 'da'];
	private const CORNER_WHITE = ['ad', 'bd', 'cd', 'dc', 'eb', 'ea', 'ce'];

	//The 8 symmetries of the 19x19 board, each taking the corner problem into one of its corners
	private static function symmetries(): array
	{
		return [
			'identity' => fn(int $x, int $y) => [$x, $y],
			'left to right' => fn(int $x, int $y) => [18 - $x, $y],
			'top to bottom' => fn(int $x, int $y) => [$x, 18 - $y],
			'180°' => fn(int $x, int $y) => [18 - $x, 18 - $y],
			'diagonal' => fn(int $x, int $y) => [$y, $x],
			'90°' => fn(int $x, int $y) => [18 - $y, $x],
			'270°' => fn(int $x, int $y) => [$y, 18 - $x],
			'anti-diagonal' => fn(int $x, int $y) => [18 - $y, 18 - $x]];
	}

	//The SGF coordinates through the symmetry, as packed positions with the color given
	private static function transformed(array $coordinates, callable $symmetry, int|bool $color): array
	{
		$result = [];
		foreach ($coordinates as $coordinate)
		{
			[$x, $y] = $symmetry(ord($coordinate[0]) - ord('a'), ord($coordinate[1]) - ord('a'));
			$result[BoardPosition::pack($x, $y)] = $color;
		}
		return $result;
	}

	//The corner problem through the symmetry, with the colors switched when asked
	private static function cornerStones(callable $symmetry, bool $colorsSwitched): array
	{
		return self::transformed(self::CORNER_BLACK, $symmetry, $colorsSwitched ? SgfBoard::WHITE : SgfBoard::BLACK)
			+ self::transformed(self::CORNER_WHITE, $symmetry, $colorsSwitched ? SgfBoard::BLACK : SgfBoard::WHITE);
	}

	//The same problem in any of the 8 symmetries of the board, and with the colors switched, is found
	//the same, from either side, with one correct move and with two
	public function testTheSameProblemIsFoundInEverySymmetryOfTheBoard()
	{
		$identity = self::symmetries()['identity'];
		foreach (['one correct move' => ['ba'], 'two correct moves' => ['ba', 'ab']] as $correctMovesName => $correctMoves)
			foreach (self::symmetries() as $symmetryName => $symmetry)
				foreach ([false, true] as $colorsSwitched)
				{
					$context = "$correctMovesName, $symmetryName" . ($colorsSwitched ? ', colors switched' : '');
					$original = [self::cornerStones($identity, false), 'B', self::transformed($correctMoves, $identity, true)];
					$other = [self::cornerStones($symmetry, $colorsSwitched), $colorsSwitched ? 'W' : 'B', self::transformed($correctMoves, $symmetry, true)];
					$this->assertSame(0, BoardComparator::compare(...$original, ...$other)?->difference, $context);
					$this->assertSame(0, BoardComparator::compare(...$other, ...$original)?->difference, $context . ', the other way');
				}
	}

	//Without correct moves the problem is found in every symmetry of the board as well, the colors
	//as they are and switched, from either side
	public function testTheSameProblemWithoutCorrectMovesIsFoundInEverySymmetryOfTheBoard()
	{
		$original = self::cornerStones(self::symmetries()['identity'], false);
		foreach (self::symmetries() as $symmetryName => $symmetry)
			foreach ([false, true] as $colorsSwitched)
			{
				$context = $symmetryName . ($colorsSwitched ? ', colors switched' : '');
				$other = self::cornerStones($symmetry, $colorsSwitched);
				$this->assertSame(0, BoardComparator::compare($original, 'N', [], $other, 'N', [])?->difference, $context);
				$this->assertSame(0, BoardComparator::compare($other, 'N', [], $original, 'N', [])?->difference, $context . ', the other way');
			}
	}

	//A problem turned by 90° that differs in a missing stone and a stone of the other color is found
	//with those two stones as the difference, shown where they are on the turned board
	public function testASlightlyDifferentProblemIsFoundTurned()
	{
		$identity = self::symmetries()['identity'];
		$turned = self::symmetries()['90°'];
		$other = self::cornerStones($turned, false);
		$missing = BoardPosition::pack(...$turned(2, 4)); // the white stone on ce
		$switched = BoardPosition::pack(...$turned(3, 1)); // the black stone on db
		unset($other[$missing]);
		$other[$switched] = SgfBoard::WHITE;

		$result = BoardComparator::compare(
			self::cornerStones($identity, false),
			'B',
			self::transformed(['ba'], $identity, true),
			$other,
			'B',
			self::transformed(['ba'], $turned, true));
		$this->assertSame(2, $result?->difference);
		$differentStones = str_split($result->diff, 2);
		sort($differentStones);
		$expected = [BoardPosition::toLetters($missing), BoardPosition::toLetters($switched)];
		sort($expected);
		$this->assertSame($expected, $differentStones);
	}
}
