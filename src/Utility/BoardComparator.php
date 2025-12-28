<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
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
		$diff = null;
		$diffColorSwitched = null;

		$diffHorizontallyMirrored = null;
		$diffHorizontallyMirroredColorSwitched = null;

		$diffVerticallyMirrored = null;
		$diffVerticallyMirroredColorSwitched = null;

		$diffMirrored = null;
		$diffMirroredColorSwitched = null;

		$aShiftedColorSwitched = null;

		$aShiftedHorizontallyMirrored = null;
		$aShiftedHorizontallyMirroredColorSwitched = null;

		$aShiftedVerticallyMirrored = null;
		$aShiftedVerticallyMirroredColorSwitched = null;

		$aShiftedMirrored = null;
		$aShiftedMirroredColorSwitched = null;

		if (BoardComparator::positionArraysMatch($aCorrectMovesShifted, $bCorrectMoves))
		{
			if ($aFirstMoveColor == 'N')
			{
				$diff = BoardComparator::compareSingle($aShifted, $bStones);
				$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
				$diffColorSwitched = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);
			}
			elseif ($aFirstMoveColor == $bFirstMoveColor)
				$diff = BoardComparator::compareSingle($aShifted, $bStones);
			else
			{
				$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
				$diffColorSwitched = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);
			}
		}

		$aCorrectMovesShiftedHorizontallyMirrored = SgfBoard::getPositionsHorizontallyMirroredAround($aCorrectMovesShifted, $bCorrectLowest);

		if (BoardComparator::positionArraysMatch($aCorrectMovesShiftedHorizontallyMirrored, $bCorrectMoves))
		{
			$aShiftedHorizontallyMirrored = SgfBoard::getPositionsHorizontallyMirroredAround($aShifted, $bCorrectLowest);
			if ($aFirstMoveColor == 'N')
			{
				$diffHorizontallyMirrored = BoardComparator::compareSingle($aShiftedHorizontallyMirrored, $bStones);
				$aShiftedHorizontallyMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedHorizontallyMirrored);
				$diffHorizontallyMirroredColorSwitched = BoardComparator::compareSingle($aShiftedHorizontallyMirroredColorSwitched, $bStones);
			}
			elseif ($aFirstMoveColor == $bFirstMoveColor)
				$diffHorizontallyMirrored = BoardComparator::compareSingle($aShiftedHorizontallyMirrored, $bStones);
			else
			{
				$aShiftedHorizontallyMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedHorizontallyMirrored);
				$diffHorizontallyMirroredColorSwitched = BoardComparator::compareSingle($aShiftedHorizontallyMirroredColorSwitched, $bStones);
			}
		}

		$aCorrectMovesShiftedVerticallyMirrored = SgfBoard::getPositionsVerticallyMirroredAround($aCorrectMovesShifted, $bCorrectLowest);

		if (BoardComparator::positionArraysMatch($aCorrectMovesShiftedVerticallyMirrored, $bCorrectMoves))
		{
			$aShiftedVerticallyMirrored = SgfBoard::getPositionsVerticallyMirroredAround($aShifted, $bCorrectLowest);
			if ($aFirstMoveColor == 'N')
			{
				$diffVerticallyMirrored = BoardComparator::compareSingle($aShiftedVerticallyMirrored, $bStones);
				$aShiftedVerticallyMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedVerticallyMirrored);
				$diffVerticallyMirroredColorSwitched = BoardComparator::compareSingle($aShiftedVerticallyMirroredColorSwitched, $bStones);
			}
			elseif ($aFirstMoveColor == $bFirstMoveColor)
				$diffVerticallyMirrored = BoardComparator::compareSingle($aShiftedVerticallyMirrored, $bStones);
			else
			{
				$aShiftedVerticallyMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedVerticallyMirrored);
				$diffVerticallyMirroredColorSwitched = BoardComparator::compareSingle($aShiftedVerticallyMirroredColorSwitched, $bStones);
			}
		}

		$aCorrectMovesShiftedMirrored = SgfBoard::getPositionsMirroredAround($aCorrectMovesShifted, $bCorrectLowest);

		if (BoardComparator::positionArraysMatch($aCorrectMovesShiftedMirrored, $bCorrectMoves))
		{
			$aShiftedMirrored = SgfBoard::getPositionsMirroredAround($aShifted, $bCorrectLowest);
			if ($aFirstMoveColor == 'N')
			{
				$diffMirrored = BoardComparator::compareSingle($aShiftedMirrored, $bStones);
				$aShiftedMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedMirrored);
				$diffMirroredColorSwitched = BoardComparator::compareSingle($aShiftedMirroredColorSwitched, $bStones);
			}
			elseif ($aFirstMoveColor == $bFirstMoveColor)
				$diffMirrored = BoardComparator::compareSingle($aShiftedMirrored, $bStones);
			else
			{
				$aShiftedMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedMirrored);
				$diffMirroredColorSwitched = BoardComparator::compareSingle($aShiftedMirroredColorSwitched, $bStones);
			}
		}

		return self::processDiffResult(
			$bStones,
			$diff, $aShifted,
			$diffColorSwitched, $aShiftedColorSwitched,
			$diffHorizontallyMirrored, $aShiftedHorizontallyMirrored,
			$diffHorizontallyMirroredColorSwitched, $aShiftedHorizontallyMirroredColorSwitched,
			$diffVerticallyMirrored, $aShiftedVerticallyMirrored,
			$diffVerticallyMirroredColorSwitched, $aShiftedVerticallyMirroredColorSwitched,
			$diffMirrored, $aShiftedMirrored,
			$diffMirroredColorSwitched, $aShiftedMirroredColorSwitched);
	}

	public static function processDiffResult(
		array $bStones,
		?int $diff, ?array $aShifted,
		?int $diffColorSwitch, ?array $aShiftedColorSwitched,
		?int $diffHorizontallyMirrored, ?array $aShiftedHorizontallyMirrored,
		?int $diffHorizontallyMirroredColorSwitched, ?array $aShiftedHorizontallyMirroredColorSwitched,
		?int $diffVerticallyMirrored, ?array $aShiftedVerticallyMirrored,
		?int $diffVerticallyMirroredColorSwitched, ?array $aShiftedVerticallyMirroredColorSwitched,
		?int $diffMirrored, ?array $aShiftedMirrored,
		?int $diffMirroredColorSwitch, ?array $aShiftedMirroredColorSwitched): ?BoardComparisonResult
	{
		$indexOfSmallestDiff = -1;
		$smallestDiff = 1000;
		if (!is_null($diff))
		{
			$smallestDiff = $diff;
			$indexOfSmallestDiff = 0;
		}
		if (!is_null($diffColorSwitch) && $diffColorSwitch < $smallestDiff)
		{
			$indexOfSmallestDiff = 1;
			$smallestDiff = $diffColorSwitch;
		}

		if (!is_null($diffHorizontallyMirrored) && $diffHorizontallyMirrored < $smallestDiff)
		{
			$indexOfSmallestDiff = 2;
			$smallestDiff = $diffHorizontallyMirrored;
		}

		if (!is_null($diffHorizontallyMirroredColorSwitched) && $diffHorizontallyMirroredColorSwitched < $smallestDiff)
		{
			$indexOfSmallestDiff = 3;
			$smallestDiff = $diffHorizontallyMirroredColorSwitched;
		}

		if (!is_null($diffVerticallyMirrored) && $diffVerticallyMirrored < $smallestDiff)
		{
			$indexOfSmallestDiff = 4;
			$smallestDiff = $diffVerticallyMirrored;
		}

		if (!is_null($diffVerticallyMirroredColorSwitched) && $diffVerticallyMirroredColorSwitched < $smallestDiff)
		{
			$indexOfSmallestDiff = 5;
			$smallestDiff = $diffVerticallyMirroredColorSwitched;
		}

		if (!is_null($diffMirrored) && $diffMirrored < $smallestDiff)
		{
			$indexOfSmallestDiff = 6;
			$smallestDiff = $diffMirrored;
		}

		if (!is_null($diffMirroredColorSwitch) && $diffMirroredColorSwitch < $smallestDiff)
		{
			$indexOfSmallestDiff = 7;
			$smallestDiff = $diffMirroredColorSwitch;
		}

		if ($indexOfSmallestDiff == -1)
			return null;

		if ($smallestDiff > 5)
			return null;

		if ($indexOfSmallestDiff == 0)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShifted, $bStones));
		if ($indexOfSmallestDiff == 1)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedColorSwitched, $bStones));
		if ($indexOfSmallestDiff == 2)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedHorizontallyMirrored, $bStones));
		if ($indexOfSmallestDiff == 3)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedHorizontallyMirroredColorSwitched, $bStones));
		if ($indexOfSmallestDiff == 4)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedVerticallyMirrored, $bStones));
		if ($indexOfSmallestDiff == 5)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedVerticallyMirroredColorSwitched, $bStones));
		if ($indexOfSmallestDiff == 6)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedMirrored, $bStones));
		//if ($indexOfSmallestDiff == 7)
		return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedMirroredColorSwitched, $bStones));
	}

	public static function compareWithoutCorrectMoves($aStones, $bStones): ?BoardComparisonResult
	{
		$aLowest = SgfBoard::getLowestPosition($aStones);
		$bLowest = SgfBoard::getLowestPosition($bStones);
		$shift = BoardPosition::diff($aLowest, $bLowest);

		$aShifted = SgfBoard::getShiftedPositions($aStones, $shift);

		$diff = BoardComparator::compareSingle($aShifted, $bStones);
		$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
		$diffColorSwitched = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);

		$aShiftedMirrored = SgfBoard::getPositionsMirroredAround($aShifted, $bLowest);
		$diffMirrored = BoardComparator::compareSingle($aShiftedMirrored, $bStones);
		$aShiftedMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedMirrored);
		$diffMirroredColorSwitched = BoardComparator::compareSingle($aShiftedMirroredColorSwitched, $bStones);
		return self::processDiffResult(
			$bStones,
			$diff, $aShifted,
			$diffColorSwitched, $aShiftedColorSwitched,
			null, null,
			null, null,
			$diffMirrored, $aShiftedMirrored,
			$diffMirroredColorSwitched, $aShiftedMirroredColorSwitched);
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
