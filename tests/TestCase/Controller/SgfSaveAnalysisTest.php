<?php

use App\Model\Table\SgfTable;
use App\Utility\CookieFlash;

//A new version of a problem's SGF is stored as the site keeps every SGF, turned to the top-left with the correct
//variations first, and gets its color to move first and its correct first moves from the server (SgfAnalysis), in
//the coordinates of the stored SGF, whatever the client sends along
class SgfSaveAnalysisTest extends IntegrationTestCase
{
	//Stones in the bottom-right corner, the correct first move sq, after a wrong one
	private const string CORNER = '(;GM[1]FF[4]SZ[19]AB[rr][sr][qr]AW[pr][pq][qq][rq](;B[pp];W[sq])(;B[sq]C[+]))';
	//It turned to the top-left, the correct variation first
	private const string CORNER_STORED = "(;FF[4]GM[1]SZ[19]ST[0]\nAB[ab][bb][cb]AW[bc][cc][db][dc]\n(;B[ac]\nC[+])\n(;B[dd]\n;W[ac]))";

	private function newest(ContextPreparator $context): object
	{
		return $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->orderByDesc('id')->first();
	}

	public function testTheSaveOfTheBoardEditorWorksOutTheCorrectMovesItself(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$this->post('/sgf/upload/' . $context->setConnections[0]['id'], [
			'sgfForBesogo' => self::CORNER,
			//what the setup page used to send, now ignored
			'firstMoveColor' => 'W',
			'correctMoves' => 'ac',
			'correct_moves' => 'ac']);

		$this->assertRedirect('/' . $context->setConnections[0]['id']);
		$saved = $this->newest($context);
		$this->assertSame(self::CORNER_STORED, $saved->sgf);
		$this->assertSame(['B', 'ac'], [$saved->first_move_color, $saved->correct_moves]);
		$this->assertEquals(SgfTable::STATUS_ACCEPTED, $saved->sgf_status_id);
	}

	public function testAProposalWorksThemOutToo(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'proposer', 'rating' => App\Utility\Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$this->post('/sgf/upload/' . $context->setConnections[0]['id'], ['sgfForBesogo' => '(;SZ[19]AW[bb](;W[aa]C[+])(;W[cc];B[aa]))', 'description' => 'White to move', 'correctMoves' => 'cc']);

		$saved = $this->newest($context);
		$this->assertEquals(SgfTable::STATUS_PROPOSED, $saved->sgf_status_id);
		$this->assertSame(['W', 'aa', 'White to move'], [$saved->first_move_color, $saved->correct_moves, $saved->description]);
	}

	//The board editor refuses to save these; an uploaded file is checked the same way and not saved
	public function testAnSgfTheBoardEditorWouldRefuseIsNotSaved(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$before = $this->newest($context)->id;
		$this->post('/sgf/upload/' . $context->setConnections[0]['id'], ['sgfForBesogo' => '(;SZ[19]AB[bb](;B[aa]C[+])(;B[dd]))']);

		$this->assertRedirect('/' . $context->setConnections[0]['id']);
		$this->assertSame($before, $this->newest($context)->id);
		$this->assertStringContainsString('The SGF was not saved: Last solver move not marked correct', (string) CookieFlash::get());
	}

	//A problem added to a collection with its SGF (SgfTable::upload) gets them too
	public function testAnAddedProblemGetsThemToo(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$saved = SgfTable::upload(self::CORNER, (int) $context->tsumegos[0]['id'], (int) $context->user['id'], true);
		$this->assertSame([self::CORNER_STORED, 'B', 'ac'], [$saved->sgf, $saved->first_move_color, $saved->correct_moves]);

		$unreadable = SgfTable::upload('(;SZ[19];B[', (int) $context->tsumegos[0]['id'], (int) $context->user['id'], true);
		$this->assertSame(['(;SZ[19];B[', null, null], [$unreadable->sgf, $unreadable->first_move_color, $unreadable->correct_moves]);
	}

	//An SGF already as the site keeps it is stored as it came
	public function testAnSgfAlreadyNormalisedIsStoredAsItCame(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$sgf = '(;GM[1]FF[4]CA[UTF-8]SZ[19]AB[bb](;B[aa]C[+])(;B[dd];W[aa]))';
		$this->post('/sgf/upload/' . $context->setConnections[0]['id'], ['sgfForBesogo' => $sgf]);
		$this->assertSame($sgf, $this->newest($context)->sgf);
	}

	//What the board editor did to the version it was opened with, and then the turn of the server, is what the
	//comments follow from that version
	public function testTheTransformationFromTheSourceTakesInTheTurn(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		$source = $this->newest($context);
		$this->post('/sgf/upload/' . $context->setConnections[0]['id'], [
			'sgfForBesogo' => self::CORNER,
			'sourceSgfID' => (string) $source->id,
			'transformationFromSource' => '{"transpose":true,"flipX":false,"flipY":false,"invertColors":false}']);
		$saved = $this->newest($context);
		$this->assertSame($source->id, $saved->source_sgf_id);
		$this->assertSame('{"transpose":true,"flipX":true,"flipY":true,"invertColors":false}', $saved->transformation_from_source);
	}

	//G, the goal of a status problem, and any other G stay as they were written, in whichever corner the problem was
	public function testGSurvivesTheSave(): void
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'sgf' => '(;SZ[19];B[aa]C[+])']]);
		foreach ([
			'(;SZ[19]G[KILL]AB[rb][sb](;B[sa];W[ra]S[DEAD])(;B[ra];W[qa]S[ALIVE]))' => 'G[KILL]',
			'(;SZ[19]G[LIVE]AW[br][bs](;B[as];W[ar]S[ALIVE])(;B[ar];W[as]S[DEAD]))' => 'G[LIVE]',
			'(;SZ[19]G[tt]AB[rr][rs](;B[ss]C[+])(;B[sr];W[qs]))' => 'G[tt]'] as $sgf => $goal)
		{
			$this->post('/sgf/upload/' . $context->setConnections[0]['id'], ['sgfForBesogo' => $sgf]);
			$saved = $this->newest($context)->sgf;
			$this->assertStringContainsString($goal, $saved);
			$this->assertSame(1, substr_count($saved, 'G['), 'once, on the root');
			$this->assertStringNotContainsString('AB[rb]', $saved, 'turned to the top-left');
		}
	}
}
