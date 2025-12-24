<?php

declare(strict_types=1);

require_once(__DIR__ . "/BoardBounds.php");
require_once(__DIR__ . "/BoardPosition.php");
require_once(__DIR__ . "/SgfBoard.php");

class SgfParser
{
	/**
	 * Parse SGF and return board, stones and info array.
	 *
	 * @param string $sgf
	 * @return SgfBoard
	 */
	public static function process(string $sgf, $correctMoves = []): SgfBoard
	{
		$boardSize = self::detectBoardSize($sgf);
		$sgfArr = str_split($sgf);

		$blackStones = self::getInitialPosition(strpos($sgf, 'AB'), $sgfArr);
		$whiteStones = self::getInitialPosition(strpos($sgf, 'AW'), $sgfArr);

		$stones = [];
		foreach ($blackStones as $blackStone)
			$stones[$blackStone] = SgfBoard::BLACK;
		foreach ($whiteStones as $whiteStone)
			$stones[$whiteStone] = SgfBoard::WHITE;

		$boardBounds = new BoardBounds();
		foreach ($stones as $position => $color)
			$boardBounds->add($position);
		foreach ($correctMoves as $position => $color)
			$boardBounds->add($position);

		$tInfo = [$boardBounds->x->max, $boardBounds->y->max];

		self::normalizeOrientation($stones, $correctMoves, $boardBounds, $boardSize);
		return new SgfBoard($stones, $tInfo, $boardSize, $correctMoves);
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

	private static function normalizeOrientation(array &$stones, array&$correctMoves, BoardBounds $boardBounds, int $boardSize): void
	{
		if ($boardBounds->x->isCloserToEnd($boardSize))
		{
			$stones = SgfBoard::getStonesFlipedX($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedX($correctMoves, $boardSize);
			$boardBounds->x->flip($boardSize);
		}
		if ($boardBounds->y->isCloserToEnd($boardSize))
		{
			$stones = SgfBoard::getStonesFlipedY($stones, $boardSize);
			$correctMoves = SgfBoard::getStonesFlipedY($correctMoves, $boardSize);
			$boardBounds->y->flip($boardSize);
		}
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
