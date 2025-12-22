<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
	public static function compareSimple(
		SgfBoard $a,
		$aFirstMoveColor,
		$aCorrectMoves,
		SgfBoard $b,
		$bFirstMoveColor,
		$bCorrectMoves): int
	{
		$aCorrectLowest = SgfBoard::getLowestPosition($aCorrectMoves);
		$bCorrectLowest = SgfBoard::getLowestPosition($bCorrectMoves);
		$aShifted = SgfBoard::getShiftedPositions($aLowest);
		$bShifted = SgfBoard::

		$bShiftedCorrectMoves = SgfBoard::getShiftedPositions($aCorrectMoves);

		$diff = self::compareSingle($a, $b);
		$d = $b->getColorSwitched();
		$a = $a->getShifted($a->getLowest());
		$b = $b->getShifted($b->getLowest());
		$c = $b->getColorSwitched();
		$diff = min($diff, self::compareSingle($a, $b));
		$diff = min($diff, self::compareSingle($a, $b->getMirrored()));

		$diff = min($diff, self::compareSingle($a, $d));
		$diff = min($diff, self::compareSingle($a, $c));
		$diff = min($diff, self::compareSingle($a, $c->getMirrored()));
		return $diff;
	}

	public static function getDiff(SgfBoard $a, SgfBoard $b): string
	{
		$result = new BoardComparisonResult();
		$compare = [];
		$compare [] = self::compareSingle($a, $b);
		$d = $b->getColorSwitched();
		$shiftA = $a->getLowest();
		$shiftB = $b->getLowest();
		$relativeDiff = BoardPosition::diff($shiftA, $shiftB);
		$a = $a->getShifted($shiftA);
		$b = $b->getShifted($shiftB);
		$c = $b->getColorSwitched();
		$compare[] = self::compareSingle($a, $b);
		$compare[] = self::compareSingle($a, $b->getMirrored());

		$compare [] = self::compareSingle($a, $d);
		$compare [] = self::compareSingle($a, $c);
		$compare [] = self::compareSingle($a, $c->getMirrored());

		$lowestCompare = $compare[0];
		$compareCount = count($compare);
		for ($i = 1; $i < $compareCount; $i++)
			if ($compare[$i] < $lowestCompare)
				$lowestCompare = $i;

		switch ($lowestCompare)
		{
			case 0:
		}

		$result->difference = $compare[$i];
		$result->transformType = $lowestCompare;
		if ($lowestCompare > 0)
		{
			$result->shiftA = $shiftA;
			$result->shiftB = $shiftB;
		}
		return $result;
	}

	private static function compareSingle(SgfBoard $a, SgfBoard $b): int
	{
		$diff = 0;
		foreach ($a->stones as $position => $color)
			if ($b->get($position) != $color)
				$diff++;
		foreach ($b->stones as $position => $color)
			if ($a->get($position) == SgfBoard::EMPTY)
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
