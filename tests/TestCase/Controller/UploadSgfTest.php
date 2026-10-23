<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Util;

class UploadSgfTest extends TestCaseWithAuth
{
	public function testUploadSgf()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => [ 'set_order' => 1, 'status' => 'S']]);
		$initialCount = count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
		$this->assertSame(0, $initialCount);
		$browser->get($context->setConnections[0]['id']);
		$this->assertSame(0, count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]])));
		$openLink = $browser->find('#openSgfLink');
		$this->assertTrue($openLink->isDisplayed());
		$this->assertSame($openLink->getText(), "Open");
		$openLink->click();
		$commentEditButton = $browser->findById('sgfCommentButton');
		$commentEditButton->click();
		$commentEditField = $browser->find('#commentEditField');
		$commentEditField->click();
		$browser->driver->getKeyboard()->sendKeys("Hello from test");
		$commentEditButton->click();
		$browser->saveFromEditor();

		// Wait for the form submit to complete (page navigates away from editor)
		$browser->waitUntilJs('!location.href.includes("/editor/")');

		$sgf = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC']);

		// After the addition of a new problem, and first edit, there is exactly 1 entry in the sgf database, which
		// is the first edit.
		// This is to avoid default first empty sgf in the history for every problem.
		$this->assertSame(1, count($sgf));
		$this->assertTextContains('Hello from test', $sgf[0]['Sgf']['sgf']);
		$this->assertSame('B', $sgf[0]['Sgf']['first_move_color']);
		$this->assertSame('', $sgf[0]['Sgf']['correct_moves']);
	}

	public function testOpeningSgfFromHistory()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => 'S',
				'sgfs' => ['(;GM[1]FF[4]SZ[19]C[Version 1])', '(;GM[1]FF[4]SZ[19]C[Version 2])']]]);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]])), 2);
		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$links = $browser->getElements('.openHistoryPointLink');
		$this->assertCount(2, $links);

		$links[0]->click();

		$commentBox = $browser->findById('commentBox');
		$this->assertSame($commentBox->getText(), 'Version 2');

		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$links = $browser->getElements('.openHistoryPointLink');
		$this->assertCount(2, $links);
		$links[1]->click();
		$commentBox = $browser->findById('commentBox');
		$this->assertSame($commentBox->getText(), 'Version 1');
	}

	public function testOpeningSgfDiff()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'status' => 'S',
				'sgfs' => ['(;GM[1]FF[4]SZ[19]C[Version 1])', '(;GM[1]FF[4]SZ[19]C[Version 2];B[aa])']]]);
		$this->assertEquals(count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]])), 2);
		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$links = $browser->getElements('.openDiff');
		$this->assertCount(1, $links);
		$links[0]->click();
		$commentBox = $browser->findById('commentBox');
		$this->assertSame($commentBox->getText(), 'Version 2');

		$browser->find('.sgf-plus-mark');
		$plusMark = $browser->getElements('.sgf-plus-mark');
		$this->assertCount(2, $plusMark); // one on the board, one on the tree I guess? But mainly there is some
	}

	public function testOpeningSgfDiffFromTheUploadsList()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]C[Version 1];B[aa])'],
			'tsumegos' => [['set_order' => 2, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]SZ[19]AW[bb]C[Other problem];W[ab])']]]);
		$problem = $context->tsumegos[0];
		$latest = ClassRegistry::init('Sgf')->uploadSgf('(;GM[1]FF[4]SZ[19]AB[cc]C[Version 2](;B[aa])(;B[ba]))', $problem['id'], Auth::getUserID(), true);
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 10:00:00', $problem['sgfs'][0]['id']]);
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 11:00:00', $context->tsumegos[1]['sgfs'][0]['id']]);
		Util::execute('UPDATE sgf SET created = ? WHERE id = ?', ['2026-01-01 12:00:00', $latest['Sgf']['id']]);

		$browser = Browser::instance();
		$browser->get('/users/uploads');
		$browser->find('#compare-' . $latest['Sgf']['id'])->click();
		$this->assertSame('Version 2', $browser->findById('commentBox')->getText());

		// only the added variation is marked, compared with the other problem both moves would be
		$browser->waitUntilJs('document.querySelectorAll(".besogo-board .sgf-plus-mark").length > 0');
		$this->assertCount(1, $browser->getElements('.besogo-board .sgf-plus-mark'));
	}
}
