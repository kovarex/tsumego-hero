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

	public function testShift()
	{
		$this->assertSame(5, BoardPosition::unpackX(BoardPosition::shift(BoardPosition::pack(7, 8), BoardPosition::pack(2, 1))));
		$this->assertSame(7, BoardPosition::unpackY(BoardPosition::shift(BoardPosition::pack(7, 8), BoardPosition::pack(2, 1))));
	}
}
