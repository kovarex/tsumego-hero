<?php

class BoardPosition
{
	function __construct($x, $y)
	{
		$this->x = $x;
		$this->y = $y;
	}

	static function fromLetters($x, $y): BoardPosition
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

	public $x, $y;
}
