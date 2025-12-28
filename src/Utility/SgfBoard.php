<?php

declare(strict_types=1);

class SgfBoard
{
	/**
	 * @param array<int,int> $stones
	 * @param array{int,int} $info
	 * @param int $size
	 */
	public function __construct(array $stones, array $info, int $size, $correctMoves = [])
	{
		$this->stones = $stones;
		$this->correctMoves = $correctMoves;
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

	public static function getPositionsMirroredAround(array $positions, int $pivot): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::mirrorAround($position, $pivot)] = $color;
		return $result;
	}

	public static function getPositionsHorizontallyMirroredAround(array $positions, int $pivot): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::horizontallyMirroredAround($position, $pivot)] = $color;
		return $result;
	}

	public static function getPositionsVerticallyMirroredAround(array $positions, int $pivot): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::verticallyMirroredAround($position, $pivot)] = $color;
		return $result;
	}

	public static function getColorSwitchedStones(array $stones): array
	{
		$result = [];
		foreach ($stones as $position => $color)
			$result[$position] = 1 - $color;
		return $result;
	}

	public static function getShiftedPositions(array $positions, int $shift): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::shift($position, $shift)] = $color;
		return $result;
	}

	public static function getLowestPosition(array $positions): int
	{
		$result = BoardPosition::pack(31, 31);
		foreach ($positions as $position => $color)
			$result = BoardPosition::min($result, $position);
		return $result;
	}

	public static function getStonesFlipedX(array $positions, int $size)
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::flipX($position, $size)] = $color;
		return $result;
	}

	public static function getStonesFlipedY(array $positions, int $size)
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::flipY($position, $size)] = $color;
		return $result;
	}

	public static function getDifferentStones(array $stonesA, array $stonesB): string
	{
		$result = '';
		foreach ($stonesA as $position => $color)
			if (!isset($stonesB[$position]) || $stonesB[$position] != $color)
				$result .= BoardPosition::toLetters($position);
		foreach ($stonesB as $position => $color)
			if (!isset($stonesA[$position]))
				$result .= BoardPosition::toLetters($position);
		return $result;
	}

	public static function decodePositionString(string $input): array
	{
		$result = [];
		$steps = (int) strlen($input) / 2;
		for ($i = 0; $i < $steps; $i++)
			$result[BoardPosition::fromLetters($input[$i * 2], $input[$i * 2 + 1])] = true;
		return $result;
	}

	public array $stones = [];
	public array $correctMoves = [];
	public array $info;
	public int $size;

	public const int BLACK = 0;
	public const int WHITE = 1;
	public const int EMPTY = 2;
}
