<?php

class BoardPosition
{
	public function __construct($x, $y)
	{
		$this->x = $x;
		$this->y = $y;
	}

	public static function fromLetters($x, $y): BoardPosition
	{
		return new BoardPosition(ord($x) - ord('a'), ord($y) - ord('a'));
	}

	public function toLetters(): string
	{
		return chr($this->x + ord('a')) . chr($this->y + ord('a'));
	}

	public function flipX($size): void
	{
		$this->x = $size - 1 - $this->x;
	}

	public function flipY($size): void
	{
		$this->y = $size - 1 - $this->y;
	}

	public function getMirrored(): BoardPosition
	{
		return new BoardPosition($this->y, $this->x);
	}

	public function getShifted(BoardPosition $shift): BoardPosition
	{
		return new BoardPosition($this->x - $shift->x, $this->y - $shift->y);
	}

	public function minEqual(BoardPosition $other): void
	{
		$this->x = min($this->x, $other->x);
		$this->y = min($this->y, $other->y);
	}

	public function pack(): int
	{
		return ($this->x << 5) | $this->y;
	}

	public $x;
	public $y;
}
