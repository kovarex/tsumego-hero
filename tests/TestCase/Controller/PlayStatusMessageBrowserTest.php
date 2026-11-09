<?php

use App\Utility\Constants;

//A status based problem tells the player what a wrong line ends in, when it isn't plainly dead
//or alive, after Incorrect in the row of the messages above the board, no box popping up. A line ending without a
//status is an error in the problem and the player is told so, instead of "It is undefined" (forum#111).
#[RetryOnBrowserFailure]
class PlayStatusMessageBrowserTest extends IntegrationTestCase
{
	//Black (1,1) kills. Black (3,1) only makes seki and black (4,1) ends where white answers (1,1) without any status.
	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19]G[KILL](;B[aa];W[ab];B[ba]S[DEAD])(;B[ca];W[aa];B[ba]S[SEKI])(;B[da];W[aa]))';

	private function playLine(array $moves, ?array $windowSize = null): Browser
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF]]);
		$browser = Browser::instance();
		if ($windowSize)
			$browser->driver->manage()->window()->setSize(new Facebook\WebDriver\WebDriverDimension(...$windowSize));
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		foreach ($moves as $index => [$x, $y])
		{
			// a click before white's automatic answer lands is ignored, so wait for it
			if ($index > 0)
				$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= ' . (2 * $index));
			$browser->clickBoard($x, $y);
		}
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$browser->waitForSubmitResult();
		return $browser;
	}

	private function shownMessage(Browser $browser): string
	{
		return $browser->driver->executeScript('return document.getElementById("status").textContent;');
	}

	public function testALineEndingInAnotherStatusSaysWhatItIs(): void
	{
		$browser = $this->playLine([[3, 1], [2, 1]]);
		$this->assertSame('Incorrect, it is SEKI (in gote), but it should be DEAD', $this->shownMessage($browser));
	}

	public function testALineEndingWithoutAStatusSaysItIsAnErrorInTheProblem(): void
	{
		$browser = $this->playLine([[4, 1]]);
		$this->assertSame('Incorrect, this line ends without a status, which is an error in the problem. Please report it in the comments.', $this->shownMessage($browser));
		$browser->assertNoJsErrors();
	}

	//No box pops up: the page has none for such a message any more, and the box of the potion is left as it is
	public function testNoBoxPopsUp(): void
	{
		$browser = $this->playLine([[3, 1], [2, 1]]);
		$this->assertSame([false, 'Hero Power', false], $browser->driver->executeScript(
			'const banner = document.querySelector("#potionAlerts .toast__banner");'
			. ' return [document.getElementById("customAlerts") !== null, banner.firstChild.textContent.trim(), [...document.querySelectorAll(".toast")].some(toast => getComputedStyle(toast).display !== "none" && toast.offsetParent !== null)];'));
	}

	//On a phone the long message wraps inside the row, in the red of Incorrect, whole, with Reset and Next at the right
	public function testALongMessageWrapsInTheRowOfAPhone(): void
	{
		$browser = Browser::instance();
		$original = $browser->driver->manage()->window()->getSize();
		try
		{
			$browser = $this->playLine([[4, 1]], [390, 844]);
			$this->assertEquals(['inTheRow' => true, 'lines' => true, 'whole' => true, 'inside' => true, 'buttonsAtTheRight' => true, 'noScroll' => true, 'red' => true],
				$browser->driver->executeScript(<<<'JS'
					const heading = document.querySelector('#status h2');
					const row = document.querySelector('.play-message');
					const end = row.querySelector('.play-actions__end');
					const probe = document.createElement('div');
					probe.style.color = 'var(--play-message-incorrect)';
					document.body.append(probe);
					const red = getComputedStyle(probe).color;
					probe.remove();
					const text = heading.getBoundingClientRect(), box = row.getBoundingClientRect(), buttons = end.getBoundingClientRect();
					return {
						inTheRow: row.contains(heading),
						lines: text.height > parseFloat(getComputedStyle(heading).lineHeight) * 1.5,
						whole: heading.scrollWidth <= heading.clientWidth + 1 && heading.scrollHeight <= heading.clientHeight + 1,
						inside: text.top >= box.top - 1 && text.bottom <= box.bottom + 1 && text.left >= box.left,
						buttonsAtTheRight: buttons.left >= text.right && Math.abs(buttons.right - box.right) < 20,
						noScroll: document.documentElement.scrollWidth === document.documentElement.clientWidth,
						red: getComputedStyle(heading).color === red,
					};
					JS));
		}
		finally
		{
			$browser->driver->manage()->window()->setSize($original);
		}
	}
}
