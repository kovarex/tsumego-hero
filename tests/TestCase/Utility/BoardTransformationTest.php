<?php

use App\Utility\BoardTransformation;

class BoardTransformationTest extends AppTestCase
{
	public function testApplyTransposesBeforeFlipping()
	{
		$this->assertSame([3, 2], (new BoardTransformation(true))->applyToPoint(2, 3, 19));
		$this->assertSame([18, 3], (new BoardTransformation(false, true))->applyToPoint(2, 3, 19));
		$this->assertSame([2, 17], (new BoardTransformation(false, false, true))->applyToPoint(2, 3, 19));
		$this->assertSame([17, 2], (new BoardTransformation(true, true))->applyToPoint(2, 3, 19));
	}

	public function testInverseAndCompositionOfAllTransformations()
	{
		foreach (BoardTransformation::all() as $first)
		{
			$this->assertTrue($first->then($first->inverse())->isIdentity());
			foreach (BoardTransformation::all() as $second)
			{
				$composed = $first->then($second);
				$this->assertSame($first->invertColors != $second->invertColors, $composed->invertColors);
				for ($x = 1; $x <= 19; $x += 3)
					for ($y = 1; $y <= 19; $y += 4)
					{
						[$middleX, $middleY] = $first->applyToPoint($x, $y, 19);
						$this->assertSame($second->applyToPoint($middleX, $middleY, 19), $composed->applyToPoint($x, $y, 19));
					}
			}
		}
	}

	public function testJsonRoundTrip()
	{
		$transformation = new BoardTransformation(true, false, true, true);
		$this->assertTrue(BoardTransformation::fromJson($transformation->toJson())->equals($transformation));
		$this->assertTrue(BoardTransformation::fromJson(null)->isIdentity());
		$this->assertTrue(BoardTransformation::fromJson('not json')->isIdentity());
		$this->assertTrue(BoardTransformation::fromJson('{"flipX":true}')->equals(new BoardTransformation(false, true)));
	}
}
