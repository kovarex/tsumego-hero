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

		return self::closest(self::candidates($aStones, $aFirstMoveColor, $aCorrectMoves, $bFirstMoveColor, $bCorrectMoves), $bStones);
	}

	//The 8 symmetries of the board as the flags of BoardPosition::transformed: the identity and the
	//mirrors first, so that on a tie the simplest one is shown, then the rotations and the diagonals
	private const SYMMETRIES = [
		0,
		BoardPosition::MIRROR_X,
		BoardPosition::MIRROR_Y,
		BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y,
		BoardPosition::MIRROR_X | BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE,
		BoardPosition::MIRROR_X | BoardPosition::MIRROR_Y | BoardPosition::TRANSPOSE];

	//A in each symmetry of the board that puts its correct moves onto those of B, pivoting on their
	//lowest corner, with the colors as they are and switched; made as they are asked for
	/** @return \Generator<int, array<int, int>> */
	private static function candidates(array $aStones, string $aFirstMoveColor, array $aCorrectMoves, string $bFirstMoveColor, array $bCorrectMoves): \Generator
	{
		$bCorrectLowest = SgfBoard::getLowestPosition($bCorrectMoves);
		// a problem without a first move can be either color's, so both are tried
		$anyColor = $aFirstMoveColor == 'N';
		foreach (self::SYMMETRIES as $symmetry)
		{
			$aCorrectMovesTransformed = SgfBoard::getPositionsTransformed($aCorrectMoves, $symmetry);
			// we always shift A to B, so we can later show diff positions relative to original B
			$shift = BoardPosition::diff(SgfBoard::getLowestPosition($aCorrectMovesTransformed), $bCorrectLowest);
			if (!self::positionArraysMatch(SgfBoard::getShiftedPositions($aCorrectMovesTransformed, $shift), $bCorrectMoves))
				continue;
			$aTransformed = SgfBoard::getShiftedPositions(SgfBoard::getPositionsTransformed($aStones, $symmetry), $shift);
			if ($anyColor || $aFirstMoveColor == $bFirstMoveColor)
				yield $aTransformed;
			if ($anyColor || $aFirstMoveColor != $bFirstMoveColor)
				yield SgfBoard::getColorSwitchedStones($aTransformed);
		}
	}

	public static function compareWithoutCorrectMoves(array $aStones, array $bStones): ?BoardComparisonResult
	{
		return self::closest(self::candidatesWithoutCorrectMoves($aStones, SgfBoard::getLowestPosition($bStones)), $bStones);
	}

	//A in each symmetry of the board, pivoting on the lowest corner of the stones, with the colors as
	//they are and switched; made as they are asked for
	/** @return \Generator<int, array<int, int>> */
	private static function candidatesWithoutCorrectMoves(array $aStones, int $bLowest): \Generator
	{
		foreach (self::SYMMETRIES as $symmetry)
		{
			$aTransformed = SgfBoard::getPositionsTransformed($aStones, $symmetry);
			$aShifted = SgfBoard::getShiftedPositions($aTransformed, BoardPosition::diff(SgfBoard::getLowestPosition($aTransformed), $bLowest));
			yield $aShifted;
			yield SgfBoard::getColorSwitchedStones($aShifted);
		}
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
