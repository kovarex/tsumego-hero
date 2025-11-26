<?php

class SgfParser
{
	/**
	 * Parse SGF and return board, stones and info array.
	 *
	 * @param string $sgf
	 * @return array{array<int,array<int,string>>, array<int,array{int,int,string}>, array{int,int}, int|string}
	 */
	public static function process($sgf)
	{
		$boardSize = self::detectBoardSize($sgf);
		$sgfArr = str_split($sgf);

		$black = self::getInitialPosition(strpos($sgf, 'AB'), $sgfArr, 'x');
		$white = self::getInitialPosition(strpos($sgf, 'AW'), $sgfArr, 'o');
		$stones = array_merge($black, $white);

		$board = self::emptyBoard(19);
		$stones = self::normalizeOrientation($stones);

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

		return [$board, $stones, $tInfo, $boardSize];
	}

	private static function detectBoardSize($sgf)
	{
		$boardSizePos = strpos($sgf, 'SZ');
		if ($boardSizePos === false)
			return 19;

		$sgfArr = str_split($sgf);
		$size = $sgfArr[$boardSizePos + 3] . $sgfArr[$boardSizePos + 4];
		if (substr($size, 1) == ']')
			$size = substr($size, 0, 1);

		return $size;
	}

	private static function emptyBoard($size)
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

	private static function normalizeOrientation($stones)
	{
		if (empty($stones))
			return $stones;

		$lowestX = 18;
		$lowestY = 18;
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

		if (18 - $lowestX < $lowestX)
			$stones = self::xFlip($stones);
		if (18 - $lowestY < $lowestY)
			$stones = self::yFlip($stones);

		return $stones;
	}

	private static function xFlip($stones)
	{
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][0] = 18 - $stones[$i][0];

		return $stones;
	}

	private static function yFlip($stones)
	{
		$stonesCount = count($stones);
		for ($i = 0; $i < $stonesCount; $i++)
			$stones[$i][1] = 18 - $stones[$i][1];

		return $stones;
	}

	private static function getInitialPosition($pos, $sgfArr, $color)
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
			{
				$pairs[$c] = [$coords[$i], null, null];
			}
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

	private static function getInitialPositionEnd($pos, $sgfArr)
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
