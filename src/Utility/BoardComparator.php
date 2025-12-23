<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
	public static function compare(
		$aStones,
		$aFirstMoveColor,
		$aCorrectMoves,
		$bStones,
		$bFirstMoveColor,
		$bCorrectMoves): ?BoardComparisonResult
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

		if (BoardComparator::positionArraysMatch($aCorrectMovesShifted, $bCorrectMoves))
		{

			if ($aFirstMoveColor == 'N')
			{
				$diff = BoardComparator::compareSingle($aShifted, $bStones);
				$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
				$diffColorSwitch = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);
			}
			else if ($aFirstMoveColor == $bFirstMoveColor)
				$diff = BoardComparator::compareSingle($aShifted, $bStones);
			else
			{
				$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
				$diffColorSwitch = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);
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
				$diffMirroredColorSwitch = BoardComparator::compareSingle($aShiftedMirroredColorSwitched, $bStones);
			}
			else if ($aFirstMoveColor == $bFirstMoveColor)
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
			$diffColorSwitch, $aShiftedColorSwitched,
			$diffMirrored, $aShiftedMirrored,
			$diffMirroredColorSwitch, $aShiftedMirroredColorSwitched);
	}

	public static function processDiffResult(
		$bStones,
		$diff, $aShifted,
		$diffColorSwitch, $aShiftedColorSwitched,
		$diffMirrored, $aShiftedMirrored,
		$diffMirroredColorSwitch, $aShiftedMirroredColorSwitched): ?BoardComparisonResult
	{
		$indexOfSmallestDiff = 0;
		$smallestDiff = $diff;
		if ($diffColorSwitch < $smallestDiff)
		{
			$indexOfSmallestDiff = 1;
			$smallestDiff = $diffColorSwitch;
		}

		if ($diffMirrored < $smallestDiff)
		{
			$indexOfSmallestDiff = 2;
			$smallestDiff = $diffMirrored;
		}

		if ($diffMirroredColorSwitch < $smallestDiff)
		{
			$indexOfSmallestDiff = 3;
			$smallestDiff = $diffMirroredColorSwitch;
		}

		if ($smallestDiff > 5)
			return null;

		if ($indexOfSmallestDiff == 0)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShifted, $bStones));
		if ($indexOfSmallestDiff == 1)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedColorSwitched, $bStones));
		if ($indexOfSmallestDiff == 2)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedMirrored, $bStones));
		if ($indexOfSmallestDiff == 3)
			return new BoardComparisonResult($smallestDiff, SgfBoard::getDifferentStones($aShiftedMirroredColorSwitched, $bStones));

		throw new Exception("Logic error");
	}

	public static function compareWithoutCorrectMoves($aStones, $bStones): ?BoardComparisonResult
	{
		$aLowest = SgfBoard::getLowestPosition($aStones);
		$bLowest = SgfBoard::getLowestPosition($bStones);
		$shift = BoardPosition::diff($aLowest, $bLowest);

		$aShifted = SgfBoard::getShiftedPositions($aStones, $shift);

		$diff = BoardComparator::compareSingle($aShifted, $bStones);
		$aShiftedColorSwitched = SgfBoard::getColorSwitchedStones($aShifted);
		$diffColorSwitch = BoardComparator::compareSingle($aShiftedColorSwitched, $bStones);

		$aShiftedMirrored = SgfBoard::getPositionsMirroredAround($aShifted, $bLowest);
		$diffMirrored = BoardComparator::compareSingle($aShiftedMirrored, $bStones);
		$aShiftedMirroredColorSwitched = SgfBoard::getColorSwitchedStones($aShiftedMirrored);
		$diffMirroredColorSwitch = BoardComparator::compareSingle($aShiftedMirroredColorSwitched, $bStones);
		return self::processDiffResult(
			$bStones,
			$diff, $aShifted,
			$diffColorSwitch, $aShiftedColorSwitched,
			$diffMirrored, $aShiftedMirrored,
			$diffMirroredColorSwitch, $aShiftedMirroredColorSwitched);
	}

	public static function compareSingle(array $stonesA, array $stonesB): int
	{
		$diff = 0;
		foreach ($stonesA as $position => $color)
		{
			$bValue = $stonesB[$position];
			if (!isset($bValue) || $bValue != $color)
				$diff++;
		}
		foreach ($stonesB as $position => $color)
			if (!isset($stonesA[$position]))
				$diff++;
		return $diff;
	}

	// I'm assuming $a and $b are arrays of packed positions, and they have
	// already been checked to have the same size and are unique
	private static function positionArraysMatch(array $a, array $b): int
	{
		foreach ($a as $position => $x)
			if (is_null($b[$position]))
				return false;
		return true;
	}
}
