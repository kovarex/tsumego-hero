<?php

require_once(__DIR__ . "/IntegerBounds.php");

class BoardBounds
{
	public function __construct()
	{
		$this->x = new IntegerBounds();
		$this->y = new IntegerBounds();
	}

	public function add(BoardPosition $position)
	{
		$this->x->add($position->x);
		$this->y->add($position->y);
	}

	public IntegerBounds $x;
	public IntegerBounds $y;
}
