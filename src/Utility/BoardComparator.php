<?php

require_once __DIR__ . '/BoardComparisonResult.php';

class BoardComparator
{
	public static function compare($a, $b, $switch = false)
	{
		$result = new BoardComparisonResult();
		$compare = [];
		self::displayArray($a);
		$diff1 = self::compareSingle($a, $b);
		array_push($compare, $diff1);
		self::displayArray($b);
		if ($switch)
			$d = self::colorSwitch($b);
		$arr = self::getLowest($a);
		$a = self::shiftToCorner($a, $arr[0], $arr[1]);
		$arr = self::getLowest($b);
		$b = self::shiftToCorner($b, $arr[0], $arr[1]);
		if ($switch)
			$c = self::colorSwitch($b);
		$diff2 = self::compareSingle($a, $b);
		array_push($compare, $diff2);
		self::displayArray($b);

		$b = self::mirror($b);
		$diff3 = self::compareSingle($a, $b);
		array_push($compare, $diff3);
		self::displayArray($b);

		if ($switch)
		{
			$diff4 = self::compareSingle($a, $d);
			array_push($compare, $diff4);
			self::displayArray($d);

			self::displayArray($c);
			$diff5 = self::compareSingle($a, $c);
			array_push($compare, $diff5);

			$c = self::mirror($c);
			$diff6 = self::compareSingle($a, $c);
			array_push($compare, $diff6);
			self::displayArray($c);
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
		if ($lowestCompareNum < 10)
			$lowestCompareNum = '0' . $lowestCompareNum;
		elseif ($lowestCompareNum > 99)
			$lowestCompareNum = 99;
		$order = $lowestCompareNum . '-' . $lowestCompare;

		$result->difference = $lowestCompareNum;
		$result->transformType = $lowestCompare;
		return $result;
		//return [$lowestCompareNum, $lowestCompare, $order];
	}

	private static function compareSingle($a, $b)
	{
		$diff = 0;
		$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++)
		{
			$bYCount = count($b[$y]);

			for ($x = 0; $x < $bYCount; $x++)
				if ($a[$x][$y] != $b[$x][$y])
					$diff++;
		}

		return $diff;
	}

	/**
	 * @param array $b Board array
	 * @param bool $trigger Trigger flag
	 * @return void
	 */
	private static function displayArray($b, $trigger = false)
	{
		$bCount = 0;
		if ($trigger)
			$bCount = count($b);

		for ($y = 0; $y < $bCount; $y++)
		{
			$bYCount = count($b[$y]);
			for ($x = 0; $x < $bYCount; $x++)
				echo '&nbsp;&nbsp;' . $b[$x][$y] . ' ';
			if ($y != 18)
				echo '<br>';
		}
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

	private static function mirror($a)
	{
		$a1 = [];
		$black = [];
		$white = [];
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++)
		{
			$a1[$y] = [];
			$aYCount = count($a[$y]);

			for ($x = 0; $x < $aYCount; $x++)
				$a1[$y][$x] = $a[$x][$y];
		}

		return $a1;
	}

	private static function getLowest($a)
	{
		$lowestX = 19;
		$lowestY = 19;
		$aCount = count($a);

		for ($y = 0; $y < $aCount; $y++)
		{
			$aYCount = count($a[$y]);
			for ($x = 0; $x < $aYCount; $x++)
				if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o')
				{
					if ($x < $lowestX)
						$lowestX = $x;
					if ($y < $lowestY)
						$lowestY = $y;
				}
		}
		$arr = [];
		array_push($arr, $lowestX);
		array_push($arr, $lowestY);

		return $arr;
	}

	private static function shiftToCorner($a, $lowestX, $lowestY)
	{
		if ($lowestX != 0)
		{
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++)
			{
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++)
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o')
					{
						$c = $a[$x][$y];
						$a[$x - $lowestX][$y] = $c;
						$a[$x][$y] = '-';
					}
			}
		}
		if ($lowestY != 0)
		{
			$aCount = count($a);

			for ($y = 0; $y < $aCount; $y++)
			{
				$aYCount = count($a[$y]);

				for ($x = 0; $x < $aYCount; $x++)
					if ($a[$x][$y] == 'x' || $a[$x][$y] == 'o')
					{
						$c = $a[$x][$y];
						$a[$x][$y - $lowestY] = $c;
						$a[$x][$y] = '-';
					}
			}
		}

		return $a;
	}
}
