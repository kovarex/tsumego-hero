<?php

use App\Utility\Constants;

//The opponent answers a move after the same short wait every time, and every stone, the player's or the
//opponent's, plays its sound exactly once, also the reply that ends the line.
#[RetryOnBrowserFailure]
class PlayStoneSoundBrowserTest extends IntegrationTestCase
{
	//Black (1,1), white (1,2), black (2,1), white (2,2) ends the line correct
	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19];B[aa];W[ab];B[ba];W[bb]C[+])';

	//How much later than the reply delay the reply may come on a slow test machine
	private const ALLOWED_LATENESS = 300;

	public function testTheReplyComesAfterTheReplyDelayAndEachStonePlaysOneSound()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'sgf' => self::SGF]]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		$browser->driver->executeScript(<<<'JS'
			updateSoundValue(true);
			window.__events = [];
			let mark = kind => window.__events.push({ kind: kind, time: performance.now() });
			//A sound starts either from the decoded buffer or, while it loads, from an <audio> element
			let originalStart = AudioBufferSourceNode.prototype.start;
			AudioBufferSourceNode.prototype.start = function () { mark('sound'); return originalStart.apply(this, arguments); };
			let originalPlay = HTMLMediaElement.prototype.play;
			HTMLMediaElement.prototype.play = function () { mark('sound'); return originalPlay.apply(this, arguments); };
			document.addEventListener('click', () => mark('click'), true);
			let shown = besogo.editor.getCurrent();
			besogo.editor.addListener(() => {
				let current = besogo.editor.getCurrent();
				if (current !== shown && current.move && current.move.color != current.getRoot().firstMove)
					mark('reply');
				shown = current;
			});
			JS);

		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('window.__events.some(event => event.kind === "reply")');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.__events.filter(event => event.kind === "reply").length === 2');
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Correct")');

		$events = $browser->driver->executeScript('return window.__events;');
		$delay = $browser->driver->executeScript('return besogo.OPPONENT_REPLY_DELAY;');
		$kinds = array_column($events, 'kind');
		//The reply's sound starts just before the board shows the reply
		$this->assertSame(['click', 'sound', 'sound', 'reply', 'click', 'sound', 'sound', 'reply'], $kinds, 'each stone plays one sound');
		$clicks = array_values(array_filter($events, fn($event) => $event['kind'] === 'click'));
		$replies = array_values(array_filter($events, fn($event) => $event['kind'] === 'reply'));
		foreach ([0, 1] as $index)
		{
			$wait = $replies[$index]['time'] - $clicks[$index]['time'];
			$this->assertGreaterThanOrEqual($delay - 1, $wait, 'the reply waits for the reply delay');
			$this->assertLessThanOrEqual($delay + self::ALLOWED_LATENESS, $wait, 'the reply comes right after the reply delay');
		}
	}
}
