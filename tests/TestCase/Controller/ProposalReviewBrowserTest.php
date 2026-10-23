<?php

use App\Utility\Constants;

/**
 * What the reviewer of a proposal sees: what the proposer wrote about it, a diff that shows more than
 * the added and removed moves, and Accept / Reject with a comment for the proposer.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class ProposalReviewBrowserTest extends TestCaseWithAuth
{
	private const SGF_VERSION_1 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]C[Old start];B[aa];W[ab];B[ba]C[+])';
	/** The same moves, but the correct ends are controlled by status now, and the start comment changed. */
	private const SGF_VERSION_2 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]C[New start]G[KILL];B[aa];W[ab];B[ba]S[DEAD])';

	public function testDiffShowsChangesOtherThanMoves(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgfs' => [
				['data' => self::SGF_VERSION_1, 'accepted' => true],
				['data' => self::SGF_VERSION_2, 'accepted' => false]]]]);
		App\Utility\Util::execute('UPDATE sgf SET description = ? WHERE id = ?', ['Made it status based', $context->tsumegos[0]['sgfs'][1]['id']]);
		$browser = Browser::instance();
		$browser->get('/editor/?sgfID=' . $context->tsumegos[0]['sgfs'][1]['id'] . '&diffID=' . $context->tsumegos[0]['sgfs'][0]['id']);

		$browser->waitUntilJs('document.getElementById("diffSummary") !== null');
		$this->assertStringContainsString('0 added', $browser->find('#diffSummary')->getText());
		// what the author wrote about the version is above the changes
		$browser->waitUntilJs('document.getElementById("diffDescription") !== null');
		$this->assertStringEndsWith('wrote: Made it status based', $browser->find('#diffDescription')->getText());
		$entries = $browser->getElements('.besogo-diff-entry');
		$texts = array_map(fn($entry) => $entry->getText(), $entries);
		$all = implode("\n", $texts);
		$this->assertStringContainsString('Correct controlled by status: no (correct ends are marked) → yes, goal: kill', $all);
		$this->assertStringContainsString('Comment: Old start → New start', $all);
		$this->assertStringContainsString('Status: (none) → DEAD', $all);
		$this->assertStringStartsWith('Start position', $texts[0]);

		// an entry takes the editor to its position
		$statusEntry = null;
		foreach ($entries as $entry)
			if (str_contains($entry->getText(), 'Status:'))
				$statusEntry = $entry;
		$this->assertStringStartsWith('1. B A19, 2. W A18, 3. B B19', $statusEntry->getText());
		$statusEntry->click();
		$this->assertSame(3, $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;'));
		$browser->assertNoJsErrors();
	}

	public function testDiffShowsReorderedBranches(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgfs' => [
				['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc](;B[aa]C[+])(;B[ba]))', 'accepted' => true],
				['data' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc](;B[ba])(;B[aa]C[+]))', 'accepted' => false]]]]);
		$browser = Browser::instance();
		$browser->get('/editor/?sgfID=' . $context->tsumegos[0]['sgfs'][1]['id'] . '&diffID=' . $context->tsumegos[0]['sgfs'][0]['id']);

		$browser->waitUntilJs('document.getElementById("diffSummary") !== null');
		$this->assertStringContainsString('1 changed', $browser->find('#diffSummary')->getText());
		$this->assertStringContainsString('Order of the branches: A19, B19 → B19, A19', $browser->find('.besogo-diff-entry')->getText());
		$browser->assertNoJsErrors();
	}

	/** Wide panels put the changes in a column of their own (sooner below the board), low narrow ones start with the list folded. */
	public function testChangesListFitsTheRoom(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgfs' => [
				['data' => self::SGF_VERSION_1, 'accepted' => true],
				['data' => self::SGF_VERSION_2, 'accepted' => false]]]]);
		$url = '/editor/?sgfID=' . $context->tsumegos[0]['sgfs'][1]['id'] . '&diffID=' . $context->tsumegos[0]['sgfs'][0]['id'];
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		$layout = 'return [document.querySelector(".besogo-panels").classList.contains("besogo-panels--diff-column"), document.querySelector(".besogo-diff").classList.contains("besogo-diff--folded")];';
		try
		{
			$window->setSize(new Facebook\WebDriver\WebDriverDimension(1920, 1080));
			$browser->get($url);
			$browser->waitUntilJs('document.getElementById("diffSummary") !== null');
			$this->assertSame([true, false], $browser->driver->executeScript($layout));

			$window->setSize(new Facebook\WebDriver\WebDriverDimension(1400, 900));
			$browser->waitUntilJs('!document.querySelector(".besogo-panels").classList.contains("besogo-panels--diff-column")');
			$this->assertSame([false, false], $browser->driver->executeScript($layout));

			// below the board there is room for two columns sooner
			$window->setSize(new Facebook\WebDriver\WebDriverDimension(800, 1200));
			$browser->waitUntilJs('document.querySelector(".besogo-panels").classList.contains("besogo-panels--diff-column")');

			$window->setSize(new Facebook\WebDriver\WebDriverDimension(500, 900));
			$browser->waitUntilJs('document.querySelector(".besogo-diff").classList.contains("besogo-diff--folded")');
			$browser->clickId('diffSummary');
			$this->assertSame([false, false], $browser->driver->executeScript($layout));
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}

	public function testProposalCantBeSavedWithoutADescription(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false, 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['sgf' => self::SGF_VERSION_1, 'status' => 'S', 'set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('openSgfLink');
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor !== null');

		$browser->saveFromEditor();
		$browser->waitUntilCssSelectorExistsWithText('.besogo-save-dialog__problem', 'Please describe the change, the reviewer will see it.');
		$this->assertStringContainsString('/editor/', $browser->driver->getCurrentURL());
		$this->assertCount(1, ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));

		$browser->findById('sgfDescriptionField')->sendKeys('Nothing really');
		$browser->clickId('sgfDescriptionSave');
		$browser->waitUntilJs('!location.href.includes("/editor/")');
		$proposal = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $context->tsumegos[0]['id']], 'order' => 'id DESC'])['Sgf'];
		$this->assertEquals(0, $proposal['accepted']);
		$this->assertSame('Nothing really', $proposal['description']);
	}

	public function testReviewerSeesTheDescriptionAndCantRejectWithoutAComment(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgfs' => [
				['data' => self::SGF_VERSION_1, 'accepted' => true],
				['data' => self::SGF_VERSION_2, 'accepted' => false]]]]);
		$proposalID = $context->tsumegos[0]['sgfs'][1]['id'];
		App\Utility\Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], 'Made it status based', $proposalID]);
		$browser = Browser::instance();
		$browser->get('/users/adminstats');
		$this->assertSame('Made it status based', $browser->findById('description-' . $proposalID)->getText());

		// a long one is shortened, the whole is in the tooltip
		$long = str_repeat('Made it status based. ', 10);
		App\Utility\Util::execute('UPDATE sgf SET description = ? WHERE id = ?', [$long, $proposalID]);
		$browser->get('/users/adminstats');
		$this->assertStringEndsWith('…', $browser->findById('description-' . $proposalID)->getText());
		$this->assertSame($long, $browser->find('#description-' . $proposalID . ' span')->getAttribute('title'));

		// the browser asks for the comment before the form goes anywhere
		$browser->clickId('reject-' . $proposalID);
		$this->assertTrue((bool) $browser->driver->executeScript('return !document.getElementById(arguments[0]).validity.valid;', ['comment-' . $proposalID]));
		$this->assertNotEmpty(ClassRegistry::init('Sgf')->findById($proposalID));

		$browser->findById('comment-' . $proposalID)->sendKeys('Status based is not wanted here');
		$browser->clickId('reject-' . $proposalID);
		$browser->waitUntilCssSelectorExistsWithText('#sgf_proposals_page_header', 'SGF Proposals (0)');
		$this->assertEmpty(ClassRegistry::init('Sgf')->findById($proposalID));
		$message = App\Utility\Util::query('SELECT text FROM message WHERE recipient_user_id = ?', [$context->otherUsers[0]['id']]);
		$this->assertCount(1, $message);
		$this->assertStringContainsString('Status based is not wanted here', $message[0]['text']);
	}
}
