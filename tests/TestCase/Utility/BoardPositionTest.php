<?php

use App\Utility\BoardPosition;

class BoardPositionTest extends AppTestCase
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

	//Each symmetry of the board takes a position to its own place, on the board of the largest size
	public function testTransformed()
	{
		$position = BoardPosition::pack(3, 2);
		$this->assertSame($position, BoardPosition::transformed($position, 0));
		$this->assertSame(BoardPosition::pack(15, 2), BoardPosition::transformed($position, BoardPosition::MIRROR_X));
		$this->assertSame(BoardPosition::pack(3, 16), BoardPosition::transformed($position, BoardPosition::MIRROR_Y));
		$this->assertSame(BoardPosition::pack(2, 3), BoardPosition::transformed($position, BoardPosition::TRANSPOSE));
		$this->assertSame(BoardPosition::pack(15, 16), BoardPosition::transformed($position, BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y));
		$this->assertSame(BoardPosition::pack(2, 15), BoardPosition::transformed($position, BoardPosition::MIRROR_X | BoardPosition::TRANSPOSE));
		$this->assertSame(BoardPosition::pack(16, 3), BoardPosition::transformed($position, BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE));
		$this->assertSame(BoardPosition::pack(16, 15), BoardPosition::transformed($position, BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE));
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
