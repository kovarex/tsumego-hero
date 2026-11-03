<?php

namespace App\Utility;

class BoardComparator
{
	//The most stones two problems can differ in and still be similar
	private const MAXIMUM_DIFFERENCE = 5;

	public static function compare(
		array $aStones,
		string $aFirstMoveColor,
		array $aCorrectMoves,
		array $bStones,
		string $bFirstMoveColor,
		array $bCorrectMoves): ?BoardComparisonResult
	{
		if (empty($aStones) || empty($bStones))
			return null;

		if (($aFirstMoveColor == 'N') != ($bFirstMoveColor == 'N'))
			return null;

		// correct moves length was supposed to be checked long before this step as optimisation
		if (empty($aCorrectMoves))
			return self::compareWithoutCorrectMoves($aStones, $bStones);

		$aCorrectLowest = SgfBoard::getLowestPosition($aCorrectMoves);
		$bCorrectLowest = SgfBoard::getLowestPosition($bCorrectMoves);
		$shift = BoardPosition::diff($aCorrectLowest, $bCorrectLowest);
		// we always shift A to b, so we can later show diff positions relative to original B
		$aCorrectMovesShifted = SgfBoard::getShiftedPositions($aCorrectMoves, $shift);
		$aShifted = SgfBoard::getShiftedPositions($aStones, $shift);

		return self::closest(self::candidates($aShifted, $aFirstMoveColor, $aCorrectMovesShifted, $bFirstMoveColor, $bCorrectMoves, $bCorrectLowest), $bStones);
	}

	//A as it is and mirrored around the lowest correct move of B, each when it puts the correct
	//moves of A onto those of B, with the colors as they are and switched; made as they are asked for
	/** @return \Generator<int, array<int, int>> */
	private static function candidates(array $aShifted, string $aFirstMoveColor, array $aCorrectMovesShifted, string $bFirstMoveColor, array $bCorrectMoves, int $pivot): \Generator
	{
		// a problem without a first move can be either color's, so both are tried
		$anyColor = $aFirstMoveColor == 'N';
		foreach ([null, 'getPositionsHorizontallyMirroredAround', 'getPositionsVerticallyMirroredAround', 'getPositionsMirroredAround'] as $mirror)
		{
			if (!self::positionArraysMatch($mirror ? SgfBoard::$mirror($aCorrectMovesShifted, $pivot) : $aCorrectMovesShifted, $bCorrectMoves))
				continue;
			$aTransformed = $mirror ? SgfBoard::$mirror($aShifted, $pivot) : $aShifted;
			if ($anyColor || $aFirstMoveColor == $bFirstMoveColor)
				yield $aTransformed;
			if ($anyColor || $aFirstMoveColor != $bFirstMoveColor)
				yield SgfBoard::getColorSwitchedStones($aTransformed);
		}
	}

	public static function compareWithoutCorrectMoves(array $aStones, array $bStones): ?BoardComparisonResult
	{
		$aLowest = SgfBoard::getLowestPosition($aStones);
		$bLowest = SgfBoard::getLowestPosition($bStones);
		$aShifted = SgfBoard::getShiftedPositions($aStones, BoardPosition::diff($aLowest, $bLowest));
		$aShiftedMirrored = SgfBoard::getPositionsMirroredAround($aShifted, $bLowest);
		return self::closest([
			$aShifted,
			SgfBoard::getColorSwitchedStones($aShifted),
			$aShiftedMirrored,
			SgfBoard::getColorSwitchedStones($aShiftedMirrored)], $bStones);
	}

	//The candidate with the fewest stones different from B, the first of them on a tie, null when
	//there is none or it differs in more than MAXIMUM_DIFFERENCE stones
	/** @param iterable<array<int, int>> $candidates */
	private static function closest(iterable $candidates, array $bStones): ?BoardComparisonResult
	{
		$closest = null;
		$smallestDiff = null;
		foreach ($candidates as $candidate)
		{
			$diff = self::compareSingle($candidate, $bStones);
			if ($smallestDiff === null || $diff < $smallestDiff)
			{
				$closest = $candidate;
				$smallestDiff = $diff;
				if ($diff === 0) // no later candidate can be closer, nor need to be made
					break;
			}
		}
		if ($smallestDiff === null || $smallestDiff > self::MAXIMUM_DIFFERENCE)
			return null;
		return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($closest, $bStones));
	}

	public static function compareSingle(array $stonesA, array $stonesB): int
	{
		$diff = 0;
		foreach ($stonesA as $position => $color)
			if (!isset($stonesB[$position]) || $stonesB[$position] != $color)
				$diff++;
		foreach ($stonesB as $position => $color)
			if (!isset($stonesA[$position]))
				$diff++;
		return $diff;
	}

	// I'm assuming $a and $b are arrays of packed positions, and they have
	// already been checked to have the same size and are unique
	private static function positionArraysMatch(array $a, array $b): bool
	{
		foreach ($a as $position => $x)
			if (!isset($b[$position]))
				return false;
		return true;
	}
}
