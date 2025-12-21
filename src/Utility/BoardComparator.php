<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
	public static function compare(SgfResultBoard $a, SgfResultBoard $b): BoardComparisonResult
	{
		$result = new BoardComparisonResult();
		$compare = [];
		$compare [] = self::compareSingle($a, $b);
		$d = $b->getColorSwitched();
		$a = $a->getShifted($a->getLowest());
		$b = $b->getShifted($b->getLowest());
		$c = $b->getColorSwitched();
		$compare[] = self::compareSingle($a, $b);
		$compare[] = self::compareSingle($a, $b->getMirrored());

		$compare [] = self::compareSingle($a, $d);
		$compare [] = self::compareSingle($a, $c);
		$compare [] = self::compareSingle($a, $c->getMirrored());

		$lowestCompare = 6;
		$lowestCompareNum = 100;
		$compareCount = count($compare);

		for ($i = 0; $i < $compareCount; $i++)
			if ($compare[$i] < $lowestCompareNum)
			{
				$lowestCompareNum = $compare[$i];
				$lowestCompare = $i;
			}

		$result->difference = $lowestCompareNum;
		$result->transformType = $lowestCompare;
		return $result;
	}

	private static function compareSingle(SgfResultBoard $a, SgfResultBoard $b)
	{
		$diff = 0;
		foreach ($a->data as $position => $color)
			if ($b->get($position) != $color)
				$diff++;
		foreach ($b->data as $position => $color)
			if ($a->get($position) == SgfResultBoard::EMPTY)
				$diff++;
		return $diff;
	}
}
