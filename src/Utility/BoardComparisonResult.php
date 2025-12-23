<?php

class BoardComparisonResult
{
	public function __construct(int $difference, string $diff)
	{
		$this->difference = $difference;
		$this->diff = $diff;
	}

	public int $difference;
	public string $diff;
}
