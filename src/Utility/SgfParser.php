<?php

declare(strict_types=1);

App::uses('SgfResult', 'Utility');

class SgfParser
{
	/**
	 * Parse SGF and return board, stones and info array.
	 *
	 * @param string $sgf
	 * @return SgfResult
	 */
	public static function process(string $sgf): SgfResult
	{
		$boardSize = self::detectBoardSize($sgf);
		$sgfArr = str_split($sgf);

		$black = self::getInitialPosition(strpos($sgf, 'AB'), $sgfArr, 'x');
		$white = self::getInitialPosition(strpos($sgf, 'AW'), $sgfArr, 'o');
		$stones = array_merge($black, $white);

		$board = self::emptyBoard($boardSize);
		$stones = self::normalizeOrientation($stones, $boardSize);

		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
		{
			if ($stones[$i][0] > $highestX)
				$highestX = $stones[$i][0];
			if ($stones[$i][1] > $highestY)
				$highestY = $stones[$i][1];
			$board[$stones[$i][0]][$stones[$i][1]] = $stones[$i][2];
		}

		$tInfo = [$highestX, $highestY];

		return new SgfResult($board, $stones, $tInfo, $boardSize);
	}

	private static function detectBoardSize(string $sgf): int
	{
		$boardSizePos = strpos($sgf, 'SZ');
		if ($boardSizePos === false)
			return 19;

		$sgfArr = str_split($sgf);
		$size = $sgfArr[$boardSizePos + 3] . $sgfArr[$boardSizePos + 4];
		if (substr($size, 1) == ']')
			$size = substr($size, 0, 1);

		return (int) $size;
	}

	private static function emptyBoard(int $size): array
	{
		$board = [];
		for ($i = 0; $i < $size; $i++)
		{
			$board[$i] = [];
			for ($j = 0; $j < $size; $j++)
				$board[$i][$j] = '-';
		}

		return $board;
	}

	private static function normalizeOrientation(array $stones, int $boardSize): array
	{
		if (empty($stones))
			return $stones;

		$maxCoord = $boardSize - 1;
		$lowestX = $maxCoord;
		$lowestY = $maxCoord;
		$highestX = 0;
		$highestY = 0;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
		{
			$lowestX = min($lowestX, $stones[$i][0]);
			$highestX = max($highestX, $stones[$i][0]);
			$lowestY = min($lowestY, $stones[$i][1]);
			$highestY = max($highestY, $stones[$i][1]);
		}

		if ($maxCoord - $lowestX < $lowestX)
			$stones = self::xFlip($stones, $boardSize);
		if ($maxCoord - $lowestY < $lowestY)
			$stones = self::yFlip($stones, $boardSize);

		return $stones;
	}

	private static function xFlip(array $stones, int $boardSize): array
	{
		$maxCoord = $boardSize - 1;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][0] = $maxCoord - $stones[$i][0];

		return $stones;
	}

	private static function yFlip(array $stones, int $boardSize): array
	{
		$maxCoord = $boardSize - 1;
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][1] = $maxCoord - $stones[$i][1];

		return $stones;
	}

	private static function getInitialPosition(int|bool $pos, array $sgfArr, string $color): array
	{
		if ($pos === false)
			return [];

		$coords = [];
		$end = self::getInitialPositionEnd($pos, $sgfArr);
		for ($i = $pos + 2; $i < $end; $i++)
			if ($sgfArr[$i] != '[' && $sgfArr[$i] != ']')
				$coords[] = strtolower($sgfArr[$i]);

		$alphabet = array_flip(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z']);

		$pairs = [];
		$xy = true;
		$c = 0;
		$coordsCount = count($coords);
		for ($i = 0; $i < $coordsCount; $i++)
		{
			$coords[$i] = $alphabet[$coords[$i]];
			if ($xy)
				$pairs[$c] = [$coords[$i], null, null];
			else
			{
				$pairs[$c][1] = $coords[$i];
				$pairs[$c][2] = $color;
				$c++;
			}
			$xy = !$xy;
		}

		return $pairs;
	}

	private static function getInitialPositionEnd(int $pos, array $sgfArr): int
	{
		$endCondition = $pos;
		$currentPos1 = $pos + 2;
		$currentPos2 = $pos + 5;
		while (isset($sgfArr[$currentPos1], $sgfArr[$currentPos2]) && $sgfArr[$currentPos1] == '[' && $sgfArr[$currentPos2] == ']')
		{
			$endCondition = $currentPos2;
			$currentPos1 += 4;
			$currentPos2 += 4;
		}

		return $endCondition;
	}
}
