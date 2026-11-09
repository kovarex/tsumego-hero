<?php

use App\Utility\ProblemDiagram;

//The black-and-white boards of the print page: the part of the board with the stones, the edges
//where it ends, and the first correct line as numbered stones.
class ProblemDiagramTest extends AppTestCase
{
	//Black stones on the top edge, white below them; the solution is B[ca] W[da] B[ea]
	private const string CORNER = '(;SZ[19]AB[ab][bb][cb]AW[ac][bc][cc][dc][db];B[ca];W[da];B[ea]C[+])';

	/** @return list<array{string, float, float}> The stones of the picture: fill, x and y */
	private static function stones(string $svg): array
	{
		preg_match_all('/<circle class="print-board__stone[^"]*" cx="([\d.]+)" cy="([\d.]+)" r="[\d.]+" fill="(#000|#fff)"/', $svg, $matches, PREG_SET_ORDER);
		return array_map(fn(array $match) => [$match[3], (float) $match[1], (float) $match[2]], $matches);
	}

	public function testTheSameSgfGivesTheSamePicture(): void
	{
		$first = ProblemDiagram::fromSgf(self::CORNER, 'ca')->problemSvg('Problem 1');
		$second = ProblemDiagram::fromSgf(self::CORNER, 'ca')->problemSvg('Problem 1');

		$this->assertSame($first, $second);
		$this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" class="print-board"', $first);
		$this->assertStringContainsString('aria-label="Problem 1"', $first);
	}

	//Solid stones with outlines on white, no wood and no shading
	public function testTheStonesAreBlackAndWhite(): void
	{
		$svg = ProblemDiagram::fromSgf(self::CORNER, 'ca')->problemSvg('Problem 1');

		$this->assertCount(8, self::stones($svg));
		$this->assertSame(3, substr_count($svg, 'print-board__stone--black'));
		$this->assertSame(5, substr_count($svg, 'print-board__stone--white'));
		$this->assertStringNotContainsString('Gradient', $svg);
		$this->assertStringContainsString('<rect width="', $svg);
	}

	//The stones and the answer reach column e and row c; a line is added around them, and the board's edge is
	//close enough on the top and the left to be shown, so the picture starts at the corner
	public function testThePictureIsCroppedToTheStonesAndSnappedToTheEdges(): void
	{
		$svg = ProblemDiagram::fromSgf(self::CORNER, 'ca')->problemSvg('Problem 1');

		// columns a to f (5 gaps) and rows 1 to 4 (3 gaps) of 20 units, with 13 around
		$this->assertStringContainsString('viewBox="0 0 126 86"', $svg);
		// the stone in the corner (a2 of the SGF, ab) sits on the edge lines
		$this->assertContains(['#000', 13.0, 33.0], self::stones($svg));
		// the top and the left edge are thick, the lines run out of the picture on the other sides
		$this->assertStringContainsString('<line x1="13" y1="13" x2="13" y2="86" stroke-width="2.5"/>', $svg);
		$this->assertStringContainsString('<line x1="13" y1="13" x2="126" y2="13" stroke-width="2.5"/>', $svg);
	}

	//The stones on the 10th and the 11th lines show with a line around them on every side: 4 lines each way, no edge
	public function testAProblemInTheMiddleOfTheBoardKeepsItsSides(): void
	{
		$svg = ProblemDiagram::fromSgf('(;SZ[19]AB[jj][kj]AW[jk];B[kk])', 'kk')->problemSvg('Problem 1');

		$this->assertStringContainsString('viewBox="0 0 86 86"', $svg);
		$this->assertStringNotContainsString('stroke-width="2.5"', $svg);
		// the star point in the middle of the board, j10, is under a stone and so not drawn
		$this->assertStringNotContainsString('r="2.5"', $svg);
	}

	//Beyond the line around the stones, a side three lines away is left out; one two lines away or closer is shown,
	//as its edge matters
	public function testAnEdgeTwoLinesOrCloserIsShown(): void
	{
		// the stones reach column o: the picture ends at column p, leaving out q, r and s
		$cut = ProblemDiagram::fromSgf('(;SZ[19]AB[nj][oj]AW[nk];B[ok])', 'ok')->problemSvg('Problem 1');
		$this->assertStringContainsString('viewBox="0 0 86 86"', $cut);
		$this->assertStringNotContainsString('stroke-width="2.5"', $cut);
		// the stones reach column p: the picture goes on to the edge, column s, which is drawn thick
		$edge = ProblemDiagram::fromSgf('(;SZ[19]AB[oj][pj]AW[ok];B[pk])', 'pk')->problemSvg('Problem 1');
		$this->assertStringContainsString('viewBox="0 0 126 86"', $edge);
		$this->assertStringContainsString('stroke-width="2.5"', $edge);
	}

	//The answer is the first correct move, then the first variation at every move after it
	public function testTheAnswerNumbersTheFirstCorrectLine(): void
	{
		$diagram = ProblemDiagram::fromSgf('(;SZ[19]AB[ab][bb][cb]AW[ac][bc][cc][dc][db](;B[ba]C[-];W[ca])(;B[ca];W[da](;B[ea]C[+])(;B[aa]))(;B[aa]))', 'ca');

		$this->assertTrue($diagram->hasAnswer());
		$this->assertSame('B', $diagram->colorToPlay());
		$this->assertSame([['B', 2, 0], ['W', 3, 0], ['B', 4, 0]], $diagram->answer);
		$svg = $diagram->answerSvg('Answer 1');
		$this->assertMatchesRegularExpression('#<circle class="print-board__stone print-board__stone--black" cx="53" cy="13"[^>]*/><text x="53" y="13"[^>]*fill="\#fff">1</text>#', $svg);
		$this->assertMatchesRegularExpression('#<circle class="print-board__stone print-board__stone--white" cx="73" cy="13"[^>]*/><text x="73" y="13"[^>]*fill="\#000">2</text>#', $svg);
		$this->assertStringContainsString('>3</text>', $svg);
		$this->assertSame([], $diagram->answerNotes());
		$this->assertStringNotContainsString('</text>', $diagram->problemSvg('Problem 1'));
	}

	//A move on a point that was played before is told beside the board, as "4 at 1"
	public function testAMoveOnAPointPlayedBeforeIsANote(): void
	{
		$diagram = ProblemDiagram::fromSgf('(;SZ[19]AB[ab][bb]AW[ac][bc][cb];B[ba];W[aa];B[ca];W[ba])', 'ba');

		$this->assertSame(['4 at 1'], $diagram->answerNotes());
		$this->assertStringNotContainsString('>4</text>', $diagram->answerSvg('Answer 1'));
	}

	public function testAProblemWithoutACorrectMoveHasNoAnswer(): void
	{
		$diagram = ProblemDiagram::fromSgf(self::CORNER, '');

		$this->assertFalse($diagram->hasAnswer());
		$this->assertNull($diagram->colorToPlay());
	}

	public function testAProblemWithoutStonesHasNoPicture(): void
	{
		$this->assertNull(ProblemDiagram::fromSgf('(;SZ[19];B[dd])', 'dd'));
		$this->assertNull(ProblemDiagram::fromSgf('', ''));
	}

	public function testACoordinateIsNamedAsOnTheBoard(): void
	{
		$diagram = ProblemDiagram::fromSgf(self::CORNER, 'ca');

		$this->assertSame('A19', $diagram->coordinate(0, 0));
		$this->assertSame('J10', $diagram->coordinate(8, 9));
	}
}
