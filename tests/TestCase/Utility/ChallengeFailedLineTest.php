<?php

use App\Utility\BoardTransformation;
use App\Utility\ChallengeFailedLine;
use App\Utility\Util;

class ChallengeFailedLineTest extends AppTestCase
{
	//The board turns the problem to the top left as the problem buttons show it, and the line with it
	public function testThePreviewTurnsTheLineWithTheStones(): void
	{
		$preview = ChallengeFailedLine::previewData('(;GM[1]FF[4]SZ[19]AB[qq](;B[ss];W[sr];B[rs]C[+]))', 'sssrtteo');
		$this->assertSame('cc', $preview['black']);
		$this->assertSame('aaabttoe', $preview['line']);
		$this->assertSame('B', $preview['firstColor']);
		// the board is cut to the stones, and the line reaches further
		$this->assertSame(14, $preview['xMax']);
		$this->assertSame(4, $preview['yMax']);
	}

	public function testALineTurnsPointByPoint(): void
	{
		$this->assertSame('aaabbatt', ChallengeFailedLine::transformed('sssrrstt', new BoardTransformation(flipX: true, flipY: true), 19));
		$this->assertSame('abtt', ChallengeFailedLine::transformed('batt', new BoardTransformation(transpose: true), 19));
		$this->assertSame('ia', ChallengeFailedLine::transformed('aa', new BoardTransformation(flipX: true), 9));
	}

	public function testThePreviewTellsWhoPlaysFirst(): void
	{
		$this->assertSame('W', ChallengeFailedLine::previewData('(;GM[1]FF[4]SZ[19]AB[cc];W[aa])', 'aa')['firstColor']);
	}

	public function testOnlyALineOnAVersionOfTheProblemIsKept(): void
	{
		$context = new ContextPreparator(['tsumegos' => [['set_order' => 1, 'sgf' => '(;GM[1]SZ[19]AB[cc];B[aa])'], ['set_order' => 2, 'sgf' => '(;GM[1]SZ[19]AB[cc];B[aa])']]]);
		$tsumegoID = (int) $context->tsumegos[0]['id'];
		$sgfID = (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ?', [$tsumegoID])[0]['id'];
		$otherSgfID = (int) Util::query('SELECT id FROM sgf WHERE tsumego_id = ?', [$context->tsumegos[1]['id']])[0]['id'];

		$this->assertSame('aatt', ChallengeFailedLine::fromRequest('aatt', $sgfID, $tsumegoID)?->moves);
		$this->assertNull(ChallengeFailedLine::fromRequest('aatt', $otherSgfID, $tsumegoID));
		$this->assertNull(ChallengeFailedLine::fromRequest('', $sgfID, $tsumegoID));
		$this->assertNull(ChallengeFailedLine::fromRequest('aab', $sgfID, $tsumegoID));
		$this->assertNull(ChallengeFailedLine::fromRequest('aazz', $sgfID, $tsumegoID));
		$this->assertNull(ChallengeFailedLine::fromRequest(['aa'], $sgfID, $tsumegoID));
		$this->assertNull(ChallengeFailedLine::fromRequest(str_repeat('aa', ChallengeFailedLine::MAXIMUM_MOVES + 1), $sgfID, $tsumegoID));
		$this->assertNotNull(ChallengeFailedLine::fromRequest(str_repeat('aa', ChallengeFailedLine::MAXIMUM_MOVES), $sgfID, $tsumegoID));
	}
}
