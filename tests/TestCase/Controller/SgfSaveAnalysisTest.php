<?php

use App\Model\Table\SgfTable;
use App\Utility\CookieFlash;

//A new version of a problem's SGF gets its color to move first and its correct first moves from the server
//(SgfAnalysis), in the coordinates of the SGF, whatever the client sends along
class SgfSaveAnalysisTest extends IntegrationTestCase
{
	//Stones in the bottom-right corner, the correct first move sq
	private const string CORNER = '(;GM[1]FF[4]SZ[19]AB[rr][sr][qr]AW[pr][pq][qq][rq](;B[sq]C[+])(;B[pp];W[sq]))';

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
		$this->assertSame(self::CORNER, $saved->sgf);
		$this->assertSame(['B', 'sq'], [$saved->first_move_color, $saved->correct_moves]);
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
		$this->assertSame(['B', 'sq'], [$saved->first_move_color, $saved->correct_moves]);

		$unreadable = SgfTable::upload('(;SZ[19];B[', (int) $context->tsumegos[0]['id'], (int) $context->user['id'], true);
		$this->assertSame([null, null], [$unreadable->first_move_color, $unreadable->correct_moves]);
	}
}
