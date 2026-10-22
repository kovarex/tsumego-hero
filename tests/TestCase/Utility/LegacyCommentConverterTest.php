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
		$this->assertSame('the anchor', $conversion->textMethod);
		$this->assertSame('exact', $conversion->positionMethod);
		$this->assertSame('18/2/-1/-1/19/2/1/2/sgf|18/2', $conversion->position);
	}

	public function testTheTextFollowsTheAnchor()
	{
		// alone, S18 is read as the first move; next to an anchor set in the top-left
		// corner it is the point the author saw there
		$this->assertSame('S18', $this->convert('S18')->message);
		$conversion = $this->convert('S18', '2/2/-1/-1/1/2/1/2/top-left|2/2');
		$this->assertSame('B18', $conversion->message);
		$this->assertSame('the anchor', $conversion->textMethod);
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

	public function testATransposedReadingHasToExplainTwoMoreMoves()
	{
		// transposed, B2 and B1 are the first two moves, but the site never showed the
		// problem transposed: the corner putting B2 on the first move wins
		$this->assertSame('S18 S19', $this->convert('B2 B1')->message);
		// a third move only the transposition explains: the SGF was replaced since
		$this->assertSame('S18 T18 R18', $this->convert('B2 B1 B3')->message);
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

	public function testColorsOfAHypotheticalMoveDoNotCount()
	{
		// S18 is the first move, black's
		$this->assertTrue($this->convert('w S18 is wrong')->colorsSwapped);
		$this->assertFalse($this->convert('if w S18, b T18')->colorsSwapped);
		$this->assertFalse($this->convert('it kills white - S18 first')->colorsSwapped);
	}

	public function testTheSideToPlayInTheTextIsComparedWithTheDescription()
	{
		$converter = new LegacyCommentConverter(SgfTree::parse(self::TOP_RIGHT_SGF), 'Black to kill.');
		$this->assertSame('Nice black to kill problem', $converter->convert('Nice black to kill problem')->message);
		$swapped = $converter->convert('Nice white to kill problem');
		$this->assertTrue($swapped->colorsSwapped);
		$this->assertSame('Nice black to kill problem', $swapped->message);
		// asking about another side to play says nothing about the colours seen
		$this->assertFalse($converter->convert('Should this be white to kill?')->colorsSwapped);
	}

	public function testLinksStayAsWritten()
	{
		// written in the top-left corner, where the first move is B18
		$this->assertSame('S18, see https://senseis.xmp.net/?B18', $this->convert('B18, see https://senseis.xmp.net/?B18')->message);
	}

	public function testTextWithoutCoordinatesOnlyLosesTheMarker()
	{
		$conversion = $this->convert('nice problem [current position]');
		$this->assertSame('nice problem', $conversion->message);
		$this->assertSame('no coordinates', $conversion->textMethod);
	}

	public function testTheMarkerGoesWithItsSpaces()
	{
		$this->assertSame('+ S18 works', $this->convert('[current position] + S18 works ')->message);
		$this->assertSame('S18 or T18', $this->convert('S18 [current position] or T18')->message);
		$this->assertSame('S18 works ', $this->convert('S18 works ')->message);
	}
}
