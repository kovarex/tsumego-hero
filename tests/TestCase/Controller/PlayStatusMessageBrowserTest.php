<?php

use App\Utility\Constants;

/**
 * A status based problem tells the player what a wrong line ends in, when it isn't plainly dead
 * or alive. A line ending without a status has nothing to tell, so nothing is shown (forum#111).
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class PlayStatusMessageBrowserTest extends IntegrationTestCase
{
	/**
	 * Black (1,1) kills. Black (3,1) only makes seki and black (4,1) ends where white answers
	 * (1,1) without any status.
	 */
	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19]G[KILL](;B[aa];W[ab];B[ba]S[DEAD])(;B[ca];W[aa];B[ba]S[SEKI])(;B[da];W[aa]))';

	private function playLine(array $moves): Browser
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		foreach ($moves as [$x, $y])
			$browser->clickBoard($x, $y);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$browser->waitForSubmitResult();
		return $browser;
	}

	private function shownMessage(Browser $browser): string
	{
		return $browser->driver->executeScript('return document.getElementById("customText").textContent;');
	}

	public function testALineEndingInAnotherStatusSaysWhatItIs(): void
	{
		$browser = $this->playLine([[3, 1], [2, 1]]);
		$this->assertSame('It is SEKI (in gote), but it should be: DEAD', $this->shownMessage($browser));
	}

	public function testALineEndingWithoutAStatusShowsNoMessage(): void
	{
		$browser = $this->playLine([[4, 1]]);
		$this->assertSame('', $this->shownMessage($browser), 'There is no "It is undefined" message');
		$browser->assertNoJsErrors();
	}
}
