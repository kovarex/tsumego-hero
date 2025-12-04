<?php

declare(strict_types=1);

App::uses('SgfResult', 'Utility');
require_once(__DIR__ . "/BoardBounds.php");
require_once(__DIR__ . "/BoardPosition.php");

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

		$blackStones = self::getInitialPosition(strpos($sgf, 'AB'), $sgfArr, 'x');
		$whiteStones = self::getInitialPosition(strpos($sgf, 'AW'), $sgfArr, 'o');

		$boardBounds = new BoardBounds();
		foreach ($blackStones as $stone)
			$boardBounds->add($stone);
		foreach ($whiteStones as $stone)
			$boardBounds->add($stone);

		self::normalizeOrientation($blackStones, $whiteStones, $boardBounds, $boardSize);
		$tInfo = [$boardBounds->x->max, $boardBounds->y->max];
		return new SgfResult($blackStones, $whiteStones, $tInfo, $boardSize);
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

	private static function normalizeOrientation(array &$blackStones, array &$whiteStones, BoardBounds $boardBounds, int $boardSize): void
	{
		if ($boardBounds->x->isCloserToEnd($boardSize))
		{
			self::xFlip($blackStones, $boardSize);
			self::xFlip($whiteStones, $boardSize);
			$boardBounds->x->flip($boardSize);
		}
		if ($boardBounds->y->isCloserToEnd($boardSize))
		{
			self::yFlip($blackStones, $boardSize);
			self::yFlip($whiteStones, $boardSize);
			$boardBounds->y->flip($boardSize);
		}
	}

	private static function xFlip(array &$stones, int $boardSize): void
	{
		foreach ($stones as &$stone)
			$stone->flipX($boardSize);
	}

	private static function yFlip(array $stones, int $boardSize): void
	{
		foreach ($stones as &$stone)
			$stone->flipY($boardSize);
	}

	private static function getInitialPosition(int|bool $pos, array $sgfArr): array
	{
		if ($pos === false)
			return [];

		$coords = [];
		$end = self::getInitialPositionEnd($pos, $sgfArr);
		for ($i = $pos + 2; $i < $end; $i++)
			if ($sgfArr[$i] == '[')
			{
				$coords[] = BoardPosition::fromLetters($sgfArr[$i + 1], $sgfArr[$i + 2]);
				$i += 3;
			}
		return $coords;
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
