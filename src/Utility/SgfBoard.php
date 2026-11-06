<?php

declare(strict_types=1);

namespace App\Utility;

class SgfBoard
{
	/**
	 * @param array<int,int> $stones
	 * @param array{int,int} $info
	 */
	public function __construct(array $stones, array $info, int $size, array $correctMoves = [])
	{
		$this->stones = $stones;
		$this->correctMoves = $correctMoves;
		$this->info = $info;
		$this->size = $size;
	}

	public function filterStonesPositions(int $color): array
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

	public static function getPositionsTransformed(array $positions, int $symmetry): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::transformed($position, $symmetry)] = $color;
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

	public static function getStonesFlipedX(array $positions, int $size): array
	{
		$result = [];
		foreach ($positions as $position => $color)
			$result[BoardPosition::flipX($position, $size)] = $color;
		return $result;
	}

	public static function getStonesFlipedY(array $positions, int $size): array
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

	//A point of the SGF where the board has it: SgfParser::process() turns the stones to the top left
	public function orientedPoint(int $position): int
	{
		if ($this->flippedX)
			$position = BoardPosition::flipX($position, $this->size);
		if ($this->flippedY)
			$position = BoardPosition::flipY($position, $this->size);
		return $position;
	}

	public array $stones = [];
	public array $correctMoves = [];
	public array $info;
	public int $size;
	//How SgfParser::process() turned the stones of the SGF
	public bool $flippedX = false;
	public bool $flippedY = false;

	public const int BLACK = 0;
	public const int WHITE = 1;
	public const int EMPTY = 2;
}
