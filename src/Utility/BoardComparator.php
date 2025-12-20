<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
	public static function compare(SgfResultBoard $a, SgfResultBoard $b, $switch = false): BoardComparisonResult
	{
		$result = new BoardComparisonResult();
		$compare = [];
		$compare []= self::compareSingle($a, $b);
		if ($switch)
			$d = self::colorSwitch($b);
		$a = $a->getShifted($a->getLowest());
		$b = $b->getShifted($b->getLowest());
		if ($switch)
			$c = self::colorSwitch($b);
		$compare[]= self::compareSingle($a, $b);
		$compare[]= self::compareSingle($a, $b->getMirrored());

		if ($switch)
		{
			$compare []= self::compareSingle($a, $d);
			$compare [] = self::compareSingle($a, $c);
			$compare []= self::compareSingle($a, $c->getMirrored());
		}
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

	private static function colorSwitch($b)
	{
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++)
		{
			$bYCount = count($b[$y]);
			for ($x = 0; $x < $bYCount; $x++)
				if ($b[$x][$y] == 'x')
					$b[$x][$y] = 'o';
				elseif ($b[$x][$y] == 'o')
					$b[$x][$y] = 'x';
		}

		return $b;
	}
}
