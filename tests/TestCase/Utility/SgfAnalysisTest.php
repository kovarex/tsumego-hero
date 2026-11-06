<?php

use App\Utility\SgfAnalysis;
use Cake\Core\Configure;

//SgfAnalysis runs scripts/sgfCorrectMoves.mjs in node for the server
class SgfAnalysisTest extends AppTestCase
{
	public function testOneSgf(): void
	{
		$analysis = SgfAnalysis::of('(;SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr]C[+]))');
		$this->assertSame(['B', 'sssr', null], [$analysis->firstMoveColor, $analysis->correctMoves, $analysis->problem]);
	}

	public function testManySgfsKeepTheirKeysAndAnUnreadableOneSaysWhy(): void
	{
		$results = SgfAnalysis::ofMany([12 => '(;SZ[19];B[aa]C[+])', 'w' => '(;SZ[19](;W[bb]C[+]))', 40 => 'not an sgf']);
		$this->assertSame([12, 'w', 40], array_keys($results));
		$this->assertSame('aa', $results[12]->correctMoves);
		$this->assertSame(['W', 'bb'], [$results['w']->firstMoveColor, $results['w']->correctMoves]);
		$this->assertIsString($results[40]);
		$this->assertSame([], SgfAnalysis::ofMany([]));
	}

	public function testAnUnreadableSgfIsAnError(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The SGF could not be read');
		SgfAnalysis::of('(;SZ[19];B[');
	}

	//The node of core.local.php; one that isn't there fails clearly
	public function testTheNodeBinaryIsConfigurable(): void
	{
		$this->assertSame('node', SgfAnalysis::nodeBinary());
		Configure::write('SgfAnalysis.node', '/nonexistent/node');
		try
		{
			$this->assertSame('/nonexistent/node', SgfAnalysis::nodeBinary());
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessage('/nonexistent/node');
			SgfAnalysis::of('(;SZ[19];B[aa]C[+])');
		}
		finally
		{
			Configure::delete('SgfAnalysis.node');
		}
	}

	//The normalised text is given only when it loses nothing a save isn't expected to; otherwise the SGF as it came
	public function testANormalisedTextThatWouldLoseSomethingUnexpectedIsNotGiven(): void
	{
		$faithful = SgfAnalysis::of('(;CA[UTF-8]SZ[19]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))', true);
		$this->assertTrue($faithful->rewriteIsFaithful());
		$this->assertSame([0, 1], $faithful->propertyChanges['FF']);
		$this->assertSame([1, 0], $faithful->propertyChanges['CA']);
		$this->assertStringContainsString('AB[ba][bb]', $faithful->sgf);

		// XY isn't a property besogo knows, so the rewrite would lose it
		$sgf = '(;SZ[19]XY[1]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))';
		$this->assertSame(['XY 1→0'], SgfAnalysis::ofMany([$sgf], SgfAnalysis::TIMEOUT, true)[0]->unexpectedChanges);
		$kept = SgfAnalysis::of($sgf, true);
		$this->assertNull($kept->sgf);
		$this->assertSame('ss', $kept->correctMoves);
	}
}
