<?php

class IntegerBounds
{
	public function add(int $value): void
	{
		$this->min = min($this->min, $value);
		$this->max = max($this->max, $value);
	}

	public function isCloserToEnd($size)
	{
		return $size - 1 - $this->min < $this->max;
	}

	public function flip($size)
	{
		$minSave = $this->min;
		$this->min = $size - 1 - $this->max;
		$this->max = $size - 1 - $minSave;
	}

	public int $min = PHP_INT_MAX;
	public int $max = 0;
}
