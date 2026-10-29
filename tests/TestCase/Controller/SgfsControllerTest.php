<?php

use App\Model\Table\SgfTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

class SgfsControllerTest extends IntegrationTestCase
{
	public function testUploadHistoryOfADeletedUploader()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer', 'admin' => true],
			'other-users' => [['name' => 'goner', 'email' => 'goner@example.com', 'deleted' => true]],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc]C[+])',
				'set_order' => 1,
				'status' => 'S']]);

		$uploader = $context->otherUsers[0];
		$tsumegoId = $context->tsumegos[0]['id'];
		Util::execute('UPDATE sgf SET user_id = ? WHERE tsumego_id = ?', [$uploader['id'], $tsumegoId]);

		$this->get('/sgfs/view/' . $tsumegoId . '?user=' . $uploader['id']);
		$html = preg_replace('/\s+/', ' ', $this->view);

		$this->assertStringContainsString('Upload History of ' . UserTable::DELETED_NAME, $html);
		$this->assertStringNotContainsString($uploader['display_name'], $html);
		$this->assertStringNotContainsString(md5('goner@example.com'), $html);
	}

	//Uploads of different problems interleave in the lists, the diff has to compare
	//with the previous version of the same problem, not with the row below.
	public function testUploadListsDiffAgainstThePreviousVersionOfTheSameProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer', 'admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]SZ[19]C[A1])'],
			'tsumegos' => [['set_order' => 2, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]SZ[19]C[B1])']]]);
		$problemA = $context->tsumegos[0];
		$problemB = $context->tsumegos[1];
		$a1 = (int) $problemA['sgfs'][0]['id'];
		$b1 = (int) $problemB['sgfs'][0]['id'];
		$a2 = SgfTable::upload('(;GM[1]FF[4]SZ[19]C[A2];B[aa])', $problemA['id'], Auth::getUserID(), true)->id;
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 10:00:00', $a1]);
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 11:00:00', $b1]);
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 12:00:00', $a2]);

		// all uploads: A2, B1, A1
		$this->get('/admin/sgf-changes?show=all');
		$uploads = $this->view;
		$this->assertStringContainsString('id="compare-' . $a2 . '" href="/editor?sgfID=' . $a2 . '&diffID=' . $a1 . '"', $uploads);
		$this->assertStringNotContainsString('id="compare-' . $b1 . '"', $uploads);
		$this->assertStringNotContainsString('id="compare-' . $a1 . '"', $uploads);

		// uploads of one user
		$this->get('/sgfs/view/' . $problemA['id'] . '?user=' . Auth::getUserID());
		$userUploads = $this->view;
		$this->assertStringContainsString('href="/editor?sgfID=' . $a2 . '&diffID=' . $a1 . '"', $userUploads);
		$this->assertStringNotContainsString('id="compare-' . $b1 . '"', $userUploads);

		// history of the problem
		$this->get('/sgfs/view/' . $problemA['id']);
		$history = $this->view;
		$this->assertStringContainsString('href="/editor?sgfID=' . $a2 . '&diffID=' . $a1 . '"', $history);
		$this->assertStringNotContainsString('id="compare-' . $a1 . '"', $history);
	}
}
