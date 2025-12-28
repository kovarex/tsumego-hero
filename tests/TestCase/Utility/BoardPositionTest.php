<?php

class BoardPositionTest extends CakeTestCase
{
	public function testBoardPositionPackAndUnpack()
	{
		$this->assertSame(3, BoardPosition::unpackX(BoardPosition::pack(3, 7)));
		$this->assertSame(7, BoardPosition::unpackY(BoardPosition::pack(3, 7)));
		$this->assertSame(19, BoardPosition::unpackX(BoardPosition::pack(19, 18)));
		$this->assertSame(18, BoardPosition::unpackY(BoardPosition::pack(19, 18)));
	}

	public function testBoardPositionFromLetters()
	{
		$this->assertSame(2, BoardPosition::unpackX(BoardPosition::fromLetters('c', 'd')));
		$this->assertSame(3, BoardPosition::unpackY(BoardPosition::fromLetters('c', 'd')));
	}

	public function testBoardPositionFromToLetter()
	{
		$this->assertSame('aa', BoardPosition::toLetters(BoardPosition::fromLetters('a', 'a')));
		$this->assertSame('df', BoardPosition::toLetters(BoardPosition::fromLetters('d', 'f')));
		$this->assertSame('kl', BoardPosition::toLetters(BoardPosition::fromLetters('k', 'l')));
	}

	public function testBoardPositionFlip()
	{
		$this->assertSame(18, BoardPosition::unpackX(BoardPosition::flipX(BoardPosition::pack(0, 18), 19)));
		$this->assertSame(18, BoardPosition::unpackY(BoardPosition::flipX(BoardPosition::pack(0, 18), 19)));

		$this->assertSame(0, BoardPosition::unpackX(BoardPosition::flipY(BoardPosition::pack(0, 18), 19)));
		$this->assertSame(0, BoardPosition::unpackY(BoardPosition::flipY(BoardPosition::pack(0, 18), 19)));
	}

	public function testMin()
	{
		$this->assertSame(0, BoardPosition::unpackX(BoardPosition::min(BoardPosition::pack(0, 15), BoardPosition::pack(1, 16))));
		$this->assertSame(15, BoardPosition::unpackY(BoardPosition::min(BoardPosition::pack(0, 15), BoardPosition::pack(1, 16))));
	}

	public function testBoardPositionShift()
	{
		$this->assertSame(5, BoardPosition::unpackX(BoardPosition::shift(BoardPosition::pack(7, 8), BoardPosition::pack(2, 1))));
		$this->assertSame(7, BoardPosition::unpackY(BoardPosition::shift(BoardPosition::pack(7, 8), BoardPosition::pack(2, 1))));
	}

	public function testBoardPositionDiff()
	{
		$this->assertSame(BoardPosition::pack(0, 0), BoardPosition::diff(BoardPosition::pack(7, 8), BoardPosition::pack(7, 8)));
		$this->assertSame(BoardPosition::pack(0, -1), BoardPosition::diff(BoardPosition::pack(7, 8), BoardPosition::pack(7, 9)));
		$this->assertSame(BoardPosition::pack(-1, 0), BoardPosition::diff(BoardPosition::pack(7, 8), BoardPosition::pack(8, 8)));
		$this->assertSame(BoardPosition::pack(1, 1), BoardPosition::diff(BoardPosition::pack(7, 8), BoardPosition::pack(6, 7)));
	}

	public function testShiftAround()
	{
		// . . . .
		// . . X .
		// . . X .
		// . . X .
		// with shape like this, we want to mirror around the left top corner, so we get a shape like this
		// . . . . .
		// . . X X X
		// . . . . .
		// . . . . .
		$position1 = BoardPosition::pack(3, 2);
		$position2 = BoardPosition::pack(3, 3);
		$position3 = BoardPosition::pack(3, 4);

		// we simulate searching left top corner of the shape to mirror around
		$min = $position3;
		$min = BoardPosition::min($min, $position2);
		$min = BoardPosition::min($min, $position1);
		$this->assertSame($min, $position1);


		$position1Mirrored = BoardPosition::mirrorAround($position1, $min);
		$position2Mirrored = BoardPosition::mirrorAround($position2, $min);
		$position3Mirrored = BoardPosition::mirrorAround($position3, $min);
		$this->assertSame($position1, $position1Mirrored); // first should be the same
		$this->assertSame(BoardPosition::pack(4, 2), $position2Mirrored);
		$this->assertSame(BoardPosition::pack(5, 2), $position3Mirrored);

	}

	// in some of the transformations, it is useful to support negative positions, as shifted and mirrored/transformed positions for comparisons can get out of the board
	public function testNegativePosition()
	{
		$this->assertSame(3, BoardPosition::unpackX(BoardPosition::pack(3, -7)));
		$this->assertSame(-7, BoardPosition::unpackY(BoardPosition::pack(3, -7)));

		$this->assertSame(-3, BoardPosition::unpackX(BoardPosition::pack(-3, 7)));
		$this->assertSame(7, BoardPosition::unpackY(BoardPosition::pack(-3, 7)));

		$this->assertSame(-19, BoardPosition::unpackX(BoardPosition::pack(-19, -18)));
		$this->assertSame(-18, BoardPosition::unpackY(BoardPosition::pack(-19, -18)));
	}
}
