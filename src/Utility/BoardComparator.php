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
		$size = max($a->input->size, $b->input->size);
		$position = new BoardPosition(0, 0);
		for ($position->y = 0; $position->y < $size; $position->y++)
			for ($position->x = 0; $position->x < $size; $position->x++)
				if ($a->getSafe($position) != $b->getSafe($position))
					$diff++;
		return $diff;
	}
}
