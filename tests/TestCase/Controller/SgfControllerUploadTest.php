<?php

use App\Model\Table\SgfTable;
use App\Model\Entity\Sgf;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Entity\TsumegoComment;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;

class SgfControllerUploadTest extends IntegrationTestCase
{
	//A problem in the top-left corner: the solution is A19, A18, B19.
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
	//A comment on it, anchored at the second move.
	private const COMMENT = ['message' => 'black A19 then w A18', 'position' => '1/2/1/1/2/1/2/1/sgf|1/1+1/2'];
	//The board as the SGF stores it, so the page shows the problem in the top-left corner and its own colours.
	private const STORED_BOARD = ['board_orientation_setting' => UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL, 'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL];

	private function newestSgf(ContextPreparator $context): Sgf
	{
		return $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->orderByDesc('id')->firstOrFail();
	}

	private function comment(ContextPreparator $context): TsumegoComment
	{
		/** @var TsumegoComment */
		return $this->fetchTable('TsumegoComment')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->orderBy('id')->firstOrFail();
	}

	//Opens the problem in the board editor, presses the given buttons of its tool panel and saves it.
	private function saveFromEditor(Browser $browser, ContextPreparator $context, array $buttons = []): void
	{
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('openSgfLink');
		foreach ($buttons as $button)
			$browser->find('input[type="button"][value="' . $button . '"]')->click();
		$browser->saveFromEditor('Turned the problem');
		// the save goes through two more pages before it lands back on the problem
		$browser->waitUntilJs('location.pathname === ' . json_encode('/' . $context->setConnections[0]['id']) . ' && document.readyState === "complete"');
	}

	//Uploads an SGF file with the admin upload of the play page the browser is on.
	private function uploadSgfFile(Browser $browser, string $sgf): void
	{
		$browser->clickId('show4');
		// Set file content via JavaScript (avoids LocalFileDetector issues with geckodriver in Docker)
		$browser->driver->executeScript("
			var content = arguments[0];
			var input = document.getElementById('admin-upload-button');
			var file = new File([content], 'test.sgf', {type: 'application/x-sgf'});
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
			input.dispatchEvent(new Event('change', { bubbles: true }));
		", [$sgf]);
		$browser->clickId('admin-upload-submit');
	}

	public function testUploadSgfViaBesogoEditor()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc]C[+])',
				'set_order' => 1,
				'status' => 'S']]);

		// Verify initial SGF count
		$initialSgfCount = count($this->fetchTable('Sgf')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->toArray());
		$this->assertEquals(1, $initialSgfCount, 'Should have initial SGF from ContextPreparator');

		$browser->get('/' . $context->setConnections[0]['id']);

		$openLink = $browser->find('#openSgfLink');
		$this->assertTrue($openLink->isDisplayed());
		$this->assertSame($openLink->getText(), "Open");
		$openLink->click();
		$browser->clickId('sgfCommentButton');
		$browser->clickId('commentEditField');
		$browser->driver->getKeyboard()->sendKeys("Hello from test");
		$browser->clickId('sgfCommentButton');
		$browser->saveFromEditor('Greeting in the start position');

		// Wait for redirect after saving SGF
		$browser->waitUntilJs('!location.href.includes("/editor/")');

		// Upload new SGF via besogo (uses 'sgfForBesogo' field)
		$newSgfContent = '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc]C[Hello from test];B[cd];W[cc]C[+])';

		// Verify new SGF was added
		$sgfs = $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->orderByDesc('id')->toArray();
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after upload');
		$this->assertEquals($newSgfContent, $sgfs[0]->sgf);
		$this->assertEquals(Auth::getUserID(), $sgfs[0]->user_id);
		$this->assertEquals(SgfTable::STATUS_ACCEPTED, $sgfs[0]->sgf_status_id, 'Admin uploads should be auto-accepted');
		$this->assertSame('B', $sgfs[0]->first_move_color);
		$this->assertSame('cd', $sgfs[0]->correct_moves);

		// Verify admin activity was logged
		$activity = $this->fetchTable('AdminActivity')->find()
			->where([
				'type' => AdminActivityTypeTable::SGF_EDIT,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => Auth::getUserID(),
			])
			->orderByDesc('id')
			->first();
		$this->assertNotNull($activity, 'Admin activity should be logged after besogo SGF upload');
		$this->assertEquals($context->tsumegos[0]['set-connections'][0]['set_id'], $activity->set_id);
	}

	public function testUploadSgfViaFileUpload()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['status' => 'S', 'set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc])']]);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$newSgfContent = '(;FF[4]GM[1]SZ[19]ST[2]AB[dd][pd][dp]AW[dc][oc][oq];B[cd];W[cc]C[+From file])';
		$this->uploadSgfFile($browser, $newSgfContent);

		// Verify new SGF was added
		$sgfs = $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $context->tsumegos[0]['id']])->orderByDesc('id')->toArray();
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after file upload');
		$this->assertEquals($newSgfContent, $sgfs[0]->sgf);
		$this->assertEquals('B', $sgfs[0]->first_move_color);
		$this->assertEquals('cd', $sgfs[0]->correct_moves);

		// Verify admin activity was logged
		$activity = $this->fetchTable('AdminActivity')->find()
			->where([
				'type' => AdminActivityTypeTable::SGF_EDIT,
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => Auth::getUserID(),
			])
			->orderByDesc('id')
			->first();
		$this->assertNotNull($activity, 'Admin activity should be logged after file SGF upload');
		$this->assertEquals($context->tsumegos[0]['set-connections'][0]['set_id'], $activity->set_id);
	}

	//The problem turned in the board editor is saved turned, and its comments are turned with it.
	public function testCommentsFollowTheProblemTurnedInTheEditor()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true] + self::STORED_BOARD,
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF, 'comments' => [self::COMMENT]]]);
		$this->saveFromEditor($browser, $context, ['H Flip', 'Invert']);

		$saved = $this->newestSgf($context);
		$this->assertEquals(SgfTable::STATUS_ACCEPTED, $saved->sgf_status_id);
		$this->assertEquals($context->tsumegos[0]['sgfs'][0]['id'], $saved->source_sgf_id);
		$this->assertSame('{"transpose":false,"flipX":true,"flipY":false,"invertColors":true}', $saved->transformation_from_source);
		$comment = $this->comment($context);
		$this->assertSame('white T19 then b T18', $comment->message);
		$this->assertSame('19/2/19/1/18/1/2/1/sgf|19/1+19/2', $comment->position);

		// the page turns the new version back into the top-left corner, where the comment points at the same stones
		$browser->expandComments();
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'white A19 then b A18');
	}

	//The editor rotates the problem by a quarter turn, the comments follow.
	public function testCommentsFollowTheProblemRotatedInTheEditor()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true] + self::STORED_BOARD,
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF, 'comments' => [self::COMMENT]]]);
		$this->saveFromEditor($browser, $context, ['Rotate']);

		// clockwise: A19 goes to T19 and A18 to S19
		$this->assertSame('{"transpose":true,"flipX":true,"flipY":false,"invertColors":false}', $this->newestSgf($context)->transformation_from_source);
		$comment = $this->comment($context);
		$this->assertSame('black T19 then w S19', $comment->message);
		$this->assertSame('18/1/19/1/19/2/2/1/sgf|19/1+18/1', $comment->position);
	}

	//The editor shows a problem stored in another corner in the top-left, so saving it there
	//moves it, even without any change, and the comments move with it.
	public function testCommentsFollowTheProblemTheEditorMovedToTheTopLeft()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true] + self::STORED_BOARD,
			'tsumego' => [
				'set_order' => 1,
				'status' => 'S',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[qq];B[ss];W[sr];B[rs]C[+])',
				'comments' => [['message' => 'black T1 then w T2', 'position' => '19/18/19/19/18/19/2/1/sgf|19/19+19/18']]]]);
		$this->saveFromEditor($browser, $context);

		$this->assertSame('{"transpose":false,"flipX":true,"flipY":true,"invertColors":false}', $this->newestSgf($context)->transformation_from_source);
		$comment = $this->comment($context);
		$this->assertSame(self::COMMENT['message'], $comment->message);
		$this->assertSame(self::COMMENT['position'], $comment->position);
	}

	//A proposal turned in the board editor turns the comments when an admin accepts it, not before.
	public function testCommentsFollowAProposalWhenItIsAccepted()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE] + self::STORED_BOARD,
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF, 'comments' => [self::COMMENT]]]);
		$this->saveFromEditor($browser, $context, ['H Flip']);

		$proposal = $this->newestSgf($context);
		$this->assertEquals(SgfTable::STATUS_PROPOSED, $proposal->sgf_status_id);
		$this->assertSame('{"transpose":false,"flipX":true,"flipY":false,"invertColors":false}', $proposal->transformation_from_source);
		$comment = $this->comment($context);
		$this->assertSame(self::COMMENT['message'], $comment->message);
		$this->assertSame(self::COMMENT['position'], $comment->position);

		$this->fetchTable('User')->updateAll(['user_role_id' => UserTable::ROLE_ADMIN], ['id' => $context->user['id']]);
		$browser->get('/admin/sgf-proposals');
		$browser->post('/users/acceptSGFProposal/' . $proposal->id);

		$comment = $this->comment($context);
		$this->assertSame('black T19 then w T18', $comment->message);
		$this->assertSame('19/2/19/1/18/1/2/1/sgf|19/1+19/2', $comment->position);
	}

	//An uploaded file carries no transformation, its stones tell where the problem went and the comments follow.
	public function testCommentsFollowAnUploadedSgfInAnotherCorner()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => 'S',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc][dc]AW[bc];B[aa];W[ab];B[ba]C[+])',
				'comments' => [self::COMMENT]]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		// mirrored into the bottom-left corner
		$this->uploadSgfFile($browser, '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cq][dq]AW[bq];B[as];W[ar];B[bs]C[+])');

		$this->assertNull($this->newestSgf($context)->source_sgf_id);
		$comment = $this->comment($context);
		$this->assertSame('black A1 then w A2', $comment->message);
		$this->assertSame('1/18/1/19/2/19/2/1/sgf|1/19+1/18', $comment->position);
	}

	//A status based problem needs a status at every end position, the board editor refuses to save it otherwise and so does the file upload.
	public function testFileUploadOfAStatusProblemWithAnEndPositionWithoutStatusIsRefused()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		$this->uploadSgfFile($browser, '(;GM[1]FF[4]ST[2]SZ[19]G[KILL](;B[aa];W[ab];B[ba]S[DEAD])(;B[da];W[aa]))');

		$browser->waitUntilCssSelectorExistsWithText('#uploadProblem', 'The SGF was not saved: End position without status. (move 2)');
		$this->assertEquals($context->tsumegos[0]['sgfs'][0]['id'], $this->newestSgf($context)->id, 'Nothing was saved');
		$browser->assertNoJsErrors();
	}

	public function testFileUploadOfAStatusProblemWithAllStatusesIsSaved()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF]]);
		$browser->get('/' . $context->setConnections[0]['id']);
		$sgf = '(;GM[1]FF[4]ST[2]SZ[19]G[KILL](;B[aa];W[ab];B[ba]S[DEAD])(;B[da];W[aa]S[ALIVE]))';
		$this->uploadSgfFile($browser, $sgf);

		$browser->waitUntilJs('location.pathname === ' . json_encode('/' . $context->setConnections[0]['id']) . ' && document.readyState === "complete"');
		$this->assertSame($sgf, $this->newestSgf($context)->sgf);
	}
}
