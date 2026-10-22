<?php

use App\Utility\BoardTransformation;
use App\Utility\SgfCommentReorientation;

class SgfCommentReorientationTest extends CakeTestCase
{
	/** The problem in the top-left corner, the solution starts at A19 (aa). */
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc][dc]AW[bc];B[aa];W[ab];B[ba]C[+])';
	/** The same problem mirrored left to right, into the top-right corner. */
	private const MIRRORED_SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[qc][pc]AW[rc];B[sa];W[sb];B[ra]C[+])';

	/** A problem with a comment anchored at its first move. */
	private function prepare(string $sgf = self::SGF): ContextPreparator
	{
		return new ContextPreparator(['tsumego' => [
			'set_order' => 1,
			'sgf' => $sgf,
			'comments' => [['message' => 'black A19 then w A18', 'position' => '1/1/-1/-1/1/2/1/1/sgf|1/1']]]]);
	}

	/** Saves a new accepted SGF version of the problem, returns its id. */
	private function saveVersion(ContextPreparator $context, array $sgf): int
	{
		$Sgf = ClassRegistry::init('Sgf');
		$Sgf->create();
		$Sgf->save(['tsumego_id' => $context->tsumegos[0]['id'], 'accepted' => true] + $sgf);
		return (int) $Sgf->id;
	}

	private function comment(ContextPreparator $context): array
	{
		return ClassRegistry::init('TsumegoComment')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]])['TsumegoComment'];
	}

	public function testCommentsFollowTheTransformationTheBoardEditorRecorded()
	{
		$context = $this->prepare();
		$sgfID = $this->saveVersion($context, [
			'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[qc][pc]AB[rc];W[sa];B[sb];W[ra]C[+])',
			'source_sgf_id' => $context->tsumegos[0]['sgfs'][0]['id'],
			'transformation_from_source' => new BoardTransformation(false, true, false, true)->toJson()]);
		SgfCommentReorientation::afterAccepted($sgfID);

		$comment = $this->comment($context);
		$this->assertSame('white T19 then b T18', $comment['message']);
		$this->assertSame('19/1/-1/-1/19/2/1/1/sgf|19/1', $comment['position']);
	}

	/**
	 * A proposal edited from an older version and accepted after a newer one: the comments
	 * go back from the newer version to the one both were edited from, then to the proposal.
	 */
	public function testCommentsFollowAProposalEditedFromAnOlderVersion()
	{
		$context = $this->prepare();
		$first = $context->tsumegos[0]['sgfs'][0]['id'];
		$mirrored = $this->saveVersion($context, [
			'sgf' => self::MIRRORED_SGF,
			'source_sgf_id' => $first,
			'transformation_from_source' => new BoardTransformation(flipX: true)->toJson()]);
		SgfCommentReorientation::afterAccepted($mirrored);
		$this->assertSame('black T19 then w T18', $this->comment($context)['message']);

		$swapped = $this->saveVersion($context, [
			'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[cc][dc]AB[bc];W[aa];B[ab];W[ba]C[+])',
			'source_sgf_id' => $first,
			'transformation_from_source' => new BoardTransformation(invertColors: true)->toJson()]);
		SgfCommentReorientation::afterAccepted($swapped);

		$comment = $this->comment($context);
		$this->assertSame('white A19 then b A18', $comment['message']);
		$this->assertSame('1/1/-1/-1/1/2/1/1/sgf|1/1', $comment['position']);
	}

	public function testCommentsFollowAnUploadedSgfWhenOnlyOneTransformationMatchesItsStones()
	{
		$context = $this->prepare();
		SgfCommentReorientation::afterAccepted($this->saveVersion($context, ['sgf' => self::MIRRORED_SGF]));

		$comment = $this->comment($context);
		$this->assertSame('black T19 then w T18', $comment['message']);
		$this->assertSame('19/1/-1/-1/19/2/1/1/sgf|19/1', $comment['position']);
	}

	public function testCommentsStayWhenTheStonesMatchSeveralWays()
	{
		// a single stone on the diagonal looks the same transposed
		$context = $this->prepare('(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])');
		SgfCommentReorientation::afterAccepted($this->saveVersion($context, ['sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[qc];B[sa];W[sb];B[ra]C[+])']));

		$comment = $this->comment($context);
		$this->assertSame('black A19 then w A18', $comment['message']);
		$this->assertSame('1/1/-1/-1/1/2/1/1/sgf|1/1', $comment['position']);
	}

	public function testCommentsStayWhenANewerVersionIsShown()
	{
		$context = $this->prepare();
		$mirrored = $this->saveVersion($context, ['sgf' => self::MIRRORED_SGF]);
		$this->saveVersion($context, ['sgf' => self::MIRRORED_SGF]);
		SgfCommentReorientation::afterAccepted($mirrored);

		$this->assertSame('black A19 then w A18', $this->comment($context)['message']);
	}
}
