<?php

use App\Model\Table\SgfTable;
use App\Utility\Auth;

//The scripts of the problem admin pages (webroot/js/UploadHistoryPage.js, DuplicateSearchPage.js).
#[RetryOnBrowserFailure]
class ProblemAdminPagesBrowserTest extends AppTestCase
{
	//The download link of a version saves its SGF under the number of the problem.
	public function testTheDownloadOfAVersionSavesItsSgf(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 3, 'sgf' => '(;GM[1]FF[4]SZ[19]C[first])']]);
		$secondID = SgfTable::upload('(;GM[1]FF[4]SZ[19]C[second];B[aa])', $context->tsumegos[0]['id'], Auth::getUserID(), true)->id;

		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$saved = $browser->driver->executeAsyncScript("
			var callback = arguments[arguments.length - 1];
			window.saveAs = function(blob, name) {
				var reader = new FileReader();
				reader.onload = function() { callback(name + '|' + reader.result); };
				reader.readAsText(blob);
			};
			document.getElementById('dl2-" . $secondID . "').click();
		");

		$this->assertSame('3.sgf|(;GM[1]FF[4]SZ[19]C[second];B[aa])', $saved);
	}

	public function testAPlayerDeletesTheirVersionAfterConfirming(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]C[first])']]);
		$ownID = SgfTable::upload('(;GM[1]FF[4]SZ[19]C[own];B[aa])', $context->tsumegos[0]['id'], Auth::getUserID(), true)->id;

		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$browser->find('[data-delete="' . $ownID . '"]')->click();
		$browser->driver->switchTo()->alert()->accept();
		$browser->waitUntilJs('location.search.includes("delete=' . $ownID . '") && document.readyState === "complete"');

		$this->assertFalse($this->fetchTable('Sgf')->exists(['id' => $ownID]));
		$this->assertCount(0, $browser->getElements('[id="' . $ownID . '"]'));
	}

	//Pointing at the diff of a version marks it and the version it is compared with.
	public function testTheDiffMarksBothVersions(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]C[first])']]);
		$firstID = $context->tsumegos[0]['sgfs'][0]['id'];
		$secondID = SgfTable::upload('(;GM[1]FF[4]SZ[19]C[second];B[aa])', $context->tsumegos[0]['id'], Auth::getUserID(), true)->id;

		$browser = Browser::instance();
		$browser->get('/sgfs/view/' . $context->tsumegos[0]['id']);
		$browser->hover($browser->find('#compare-' . $secondID));

		foreach ([$secondID, $firstID] as $sgfID)
			$this->assertStringContainsString('linear-gradient', $browser->find('[id="' . $sgfID . '"] td')->getCSSValue('background-image'));
	}

	public function testTheSimilarSearchDrawsTheBoardOfEachProblem(): void
	{
		$sgf = '(;GM[1]FF[4]SZ[19]AB[dd][df][fd][ff];B[aa]C[+])';
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumegos' => [
			['set_order' => 1, 'sgf' => $sgf],
			['set_order' => 2, 'sgf' => $sgf]]]);

		$browser = Browser::instance();
		$browser->get('/tsumegos/duplicatesearch/' . $context->setConnections[0]['id']);

		$this->assertCount(1, $browser->getElements('#previewMaster svg'));
		$this->assertCount(1, $browser->getElements('#preview' . $context->setConnections[1]['id'] . ' svg'));
	}
}
