<?php

use App\Utility\LegacyCommentConverter;
use App\Utility\SgfTree;

class LegacyCommentConverterTest extends CakeTestCase
{
	/** A problem in the top-right corner of the SGF, the solution starts at S18 (rb). */
	private const TOP_RIGHT_SGF = '(;GM[1]FF[4]SZ[19]AB[pc][qd][rd]AW[qc][rc][sd];B[rb](;W[sb];B[qb]C[+])(;W[qb];B[sb]C[+]))';

	private function convert(string $message, ?string $position = null): App\Utility\LegacyCommentConversion
	{
		return (new LegacyCommentConverter(SgfTree::parse(self::TOP_RIGHT_SGF)))->convert($message, $position);
	}

	public function testCoordinatesWrittenInAnotherCornerMoveToTheMovesOfTheSgf()
	{
		// written while the problem was shown in the top-left corner
		$conversion = $this->convert('B18 is the key, then A18 and C18');
		$this->assertSame('S18 is the key, then T18 and R18', $conversion->message);
		$this->assertSame('unique', $conversion->textMethod);
	}

	public function testCoordinatesAlreadyInTheOrientationOfTheSgfStay()
	{
		$this->assertSame('S18 works', $this->convert('S18 works')->message);
	}

	public function testAnchorIsConvertedFromItsCornerAndTheMarkerRemoved()
	{
		$conversion = $this->convert('B18[current position]', '2/2/-1/-1/1/2/1/2/top-left|2/2');
		$this->assertSame('S18', $conversion->message);
		$this->assertSame('exact', $conversion->positionMethod);
		$this->assertSame('18/2/-1/-1/19/2/1/2/sgf|18/2', $conversion->position);
	}

	public function testAnchorStoredFromTheMoveBackToTheRootIsTurnedAround()
	{
		// the old PHP form listed the path from the current move back to the root
		$conversion = $this->convert('here', '19/2/18/2/17/2/2/1/top-right|19/2+18/2');
		$this->assertSame('exact', $conversion->positionMethod);
		$this->assertSame('19/2/18/2/17/2/2/1/sgf|18/2+19/2', $conversion->position);
	}

	public function testEmptyAnchorIsDropped()
	{
		$conversion = $this->convert('B18', '-1/-1/0/0/0/0/0/0/0');
		$this->assertNull($conversion->position);
		$this->assertSame('S18', $conversion->message);
	}

	public function testColorsAreSwappedOnlyWhenTheWrittenColorsContradictTheTree()
	{
		$sgf = '(;GM[1]FF[4]SZ[19]AB[cc][dc]AW[bc][cb];B[bb];W[ab];B[ba]C[+])';
		$converter = new LegacyCommentConverter(SgfTree::parse($sgf));
		$this->assertSame('black B18, white A18', $converter->convert('black B18, white A18')->message);
		$swapped = $converter->convert('white B18, black A18');
		$this->assertTrue($swapped->colorsSwapped);
		$this->assertSame('black B18, white A18', $swapped->message);
	}

	public function testTextWithoutCoordinatesOnlyLosesTheMarker()
	{
		$conversion = $this->convert('nice problem [current position]');
		$this->assertSame('nice problem ', $conversion->message);
		$this->assertSame('no coordinates', $conversion->textMethod);
	}
}
