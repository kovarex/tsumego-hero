<?php

require_once(__DIR__ . "/IntegerBounds.php");

class BoardBounds
{
	public function __construct()
	{
		$this->x = new IntegerBounds();
		$this->y = new IntegerBounds();
	}

	public function add(int $packed)
	{
		$this->x->add(BoardPosition::unpackX($packed));
		$this->y->add(BoardPosition::unpackY($packed));
	}

	public IntegerBounds $x;
	public IntegerBounds $y;
}
