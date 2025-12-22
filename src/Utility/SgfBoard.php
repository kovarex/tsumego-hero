<?php

declare(strict_types=1);

class SgfBoard
{
	/**
	 * @param array<int,int> $stones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(array $stones, array $info, int $size)
	{
		$this->stones = $stones;
		$this->info = $info;
		$this->size = $size;
	}

	public function filterStonesPositions($color): array
	{
		$result = [];
		foreach ($this->stones as $position => $stone)
			if ($stone == $color)
				$result[] = $position;
		return $result;
	}

	public function getStoneCount(): int
	{
		return count($this->stones);
	}

	public function getMirrored(): SgfBoard
	{
		$result = new SgfBoard([], $this->info, $this->size);
		foreach ($this->stones as $position => $color)
			$result->stones[BoardPosition::mirror($position)] = $color;
		return $result;
	}

	public function getColorSwitched(): SgfBoard
	{
		$stones = [];
		foreach ($this->stones as $position => $color)
			$stones[$position] = 1 - $color;
		return new SgfBoard($stones, $this->info, $this->size);
	}

	public function getShifted(int $shift): SgfBoard
	{
		$result = new SgfBoard([], $this->info, $this->size);
		foreach ($this->stones as $position => $color)
			$result->stones[BoardPosition::shift($position, $shift)] = $color;
		return $result;
	}

	public function getLowest(): int
	{
		$result = BoardPosition::pack($this->size, $this->size);
		foreach ($this->stones as $position => $stone)
			$result = BoardPosition::min($result, $position);
		return $result;
	}

	public function get(int $packed): int
	{
		return $this->stones[$packed] ?? self::EMPTY;
	}

	public function getDifferentPositions(SgfBoard $other): string
	{
		$result = '';
		foreach ($this->stones as $position => $color)
			if ($other->get($position) != $color)
				$result .= BoardPosition::toLetters($position);
		foreach ($other->stones as $position => $color)
			if ($this->get($position) == SgfBoard::EMPTY)
				$result .= BoardPosition::toLetters($position);
		return $result;
	}

	public array $stones = [];
	public array $info;
	public int $size;

	public const int BLACK = 0;
	public const int WHITE = 1;
	public const int EMPTY = 2;
}
