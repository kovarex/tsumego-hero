<?php

use App\Utility\ReadingStones;
use PHPUnitRetry\RetryTrait;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class ReadingModeTest extends TestCaseWithAuth
{
	use RetryTrait;

	/** 19x19 board with three setup stones at cc/dd/ee and a three move solution. */
	private const SGF_19 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc][dd]AW[ee];B[aa];W[ab];B[ba]C[+])';

	private ContextPreparator $context;

	private function openProblem(string $userName, array $reading, array $tsumego = []): Browser
	{
		$this->context = new ContextPreparator([
			'user' => array_merge([
				'name' => $userName,
				// Pin the colour and orientation so board coordinates in the test
				// are the authored ones and the board is never swapped or rotated.
				'pref_player_color' => User::PREF_PLAYER_COLOR_ORIGINAL,
				'pref_board_orientation' => User::PREF_BOARD_ORIENTATION_ORIGINAL,
			], $reading),
			'tsumego' => array_merge(['set_order' => 1, 'sgf' => self::SGF_19], $tsumego),
		]);

		$browser = Browser::instance();
		$browser->get('/' . $this->context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilJs(
			'(function () {'
			. 'if (typeof besogo === "undefined" || !besogo.editor) return false;'
			. 'var n = besogo.editor.getCurrent(), s = n.getSize(), c = 0;'
			. 'for (var y = 1; y <= s.y; y++)'
			. '  for (var x = 1; x <= s.x; x++)'
			. '    if (n.getStone(x, y)) c++;'
			. 'return c > 0;'
			. '})()'
		);
		// A problem opens with the trainer off; the player turns it on with the eye.
		$browser->driver->executeScript('besogo.editor.setReadingOn(true);');

		return $browser;
	}

	/** Stones drawn on the board, keyed by "x,y", with the state the renderer used. */
	private function drawnStones(Browser $browser): array
	{
		$json = $browser->driver->executeScript(
			'var out = {};'
			. 'var els = document.querySelectorAll("#target image[data-reading]");'
			. 'for (var i = 0; i < els.length; i++)'
			. '  out[els[i].getAttribute("data-x") + "," + els[i].getAttribute("data-y")] = {'
			. '    state: els[i].getAttribute("data-reading"),'
			. '    href: els[i].getAttributeNS("http://www.w3.org/1999/xlink", "href")'
			. '  };'
			. 'return out;'
		);

		return (array) $json;
	}

	/** The stones a pair of looks leaves on the board once the first move is played. */
	private function expectedBoard(ReadingStones $played, ReadingStones $setup): array
	{
		$expected = [];
		if ($played !== ReadingStones::Hidden)
			$expected['1,1'] = $played === ReadingStones::Glass ? 'glass' : 'normal';
		if ($setup !== ReadingStones::Hidden)
			$expected += array_fill_keys(array_keys($this->givenPosition()), $setup === ReadingStones::Glass ? 'glass' : 'normal');

		return $expected;
	}

	/** Stones still present in the game tree, independent of what is drawn. */
	private function modelStoneCount(Browser $browser): int
	{
		return (int) $browser->driver->executeScript(
			'var node = besogo.editor.getCurrent();'
			. 'var size = node.getSize();'
			. 'var count = 0;'
			. 'for (var y = 1; y <= size.y; y++)'
			. '  for (var x = 1; x <= size.x; x++)'
			. '    if (node.getStone(x, y)) count++;'
			. 'return count;'
		);
	}

	private function playFirstMove(Browser $browser): void
	{
		$browser->driver->executeScript('besogo.editor.setCurrent(besogo.editor.getRoot().children[0]);');
	}

	private function playNextMove(Browser $browser): void
	{
		$browser->driver->executeScript('besogo.editor.setCurrent(besogo.editor.getCurrent().children[0]);');
	}

	private function solve(Browser $browser): void
	{
		$browser->waitUntilJs('typeof displayResult === "function"');
		$browser->driver->executeScript('displayResult("S")');
	}

	/** Waits until the board settled on exactly these stones, keyed by "x,y". */
	private function waitForBoard(Browser $browser, array $expected): void
	{
		$signature = [];
		foreach ($expected as $point => $state)
			$signature[] = $point . '=' . $state;
		sort($signature);
		$expectedSignature = implode('|', $signature);

		try
		{
			$browser->waitUntilJs(
				'(function () {'
				. 'var out = [];'
				. 'var els = document.querySelectorAll("#target image[data-reading]");'
				. 'for (var i = 0; i < els.length; i++)'
				. '  out.push(els[i].getAttribute("data-x") + "," + els[i].getAttribute("data-y") + "=" + els[i].getAttribute("data-reading"));'
				. 'out.sort();'
				. 'return out.join("|") === ' . json_encode($expectedSignature) . ';'
				. '})()'
			);
		}
		catch (Facebook\WebDriver\Exception\TimeoutException $e)
		{
			// The assertion below reports what the board settled on instead.
		}

		$this->assertSame($expectedSignature, $this->boardSignature($browser), 'The board settled on the expected stones');
	}

	/** The stones the board draws right now, as one sorted string. */
	private function boardSignature(Browser $browser): string
	{
		$signature = [];
		foreach ($this->drawnStones($browser) as $point => $stone)
			$signature[] = $point . '=' . $stone['state'];
		sort($signature);

		return implode('|', $signature);
	}

	/** The given position of the fixture, all with their real colours. */
	private function givenPosition(): array
	{
		return ['3,3' => 'normal', '4,4' => 'normal', '5,5' => 'normal'];
	}

	/** Whether the eye has the trainer on right now. */
	private function trainerOn(Browser $browser): bool
	{
		return (bool) $browser->driver->executeScript('return besogo.editor.READING_ON;');
	}

	public function testHiddenStonesKeepTheGivenPosition(): void
	{
		$browser = $this->openProblem('readingGhost', [
			'pref_reading_played' => ReadingStones::Hidden->value,
			'pref_reading_setup' => ReadingStones::Normal->value,
		]);

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, $this->givenPosition());

		$stones = $this->drawnStones($browser);
		$this->assertArrayNotHasKey('1,1', $stones, 'The played stone is not drawn');
		$this->assertCount(3, $stones, 'Only the given position is left on the board');

		$this->assertSame(4, $this->modelStoneCount($browser), 'The move still exists in the game tree');
	}

	public function testGivenPositionHidesOnceAMoveIsPlayed(): void
	{
		$browser = $this->openProblem('readingPositionHidden', ['pref_reading_setup' => ReadingStones::Hidden->value]);

		$before = $this->drawnStones($browser);
		$this->assertCount(3, $before, 'The given position is shown before the first move');
		foreach ($before as $stone)
			$this->assertSame('normal', $stone['state'], 'Nothing is hidden while the player is still reading');

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, ['1,1' => 'normal']);

		$after = $this->drawnStones($browser);
		$this->assertCount(1, $after, 'Only the played stone is drawn after the first move');
		$this->assertArrayHasKey('1,1', $after, 'The played stone stays visible');
	}

	public function testEveryPairOfLooksIsDrawn(): void
	{
		foreach (ReadingStones::cases() as $played)
			foreach (ReadingStones::cases() as $setup)
			{
				$browser = $this->openProblem('readingPair' . $played->value . $setup->value, [
					'pref_reading_played' => $played->value,
					'pref_reading_setup' => $setup->value,
				]);

				// The given position is shown in full while the player is still reading.
				$this->waitForBoard($browser, $this->givenPosition());

				$this->playFirstMove($browser);
				$this->waitForBoard($browser, $this->expectedBoard($played, $setup));

				$stones = $this->drawnStones($browser);
				foreach ($stones as $point => $stone)
					if ($stone['state'] === 'glass')
						$this->assertStringContainsString('glass.png', $stone['href'],
							'Stone at ' . $point . ' of ' . $played->value . '/' . $setup->value . ' uses the glass asset');

				// A finished attempt is shown whole, whatever the pair hides.
				$this->solve($browser);
				$this->waitForBoard($browser, array_fill_keys(array_merge(['1,1'], array_keys($this->givenPosition())), 'normal'));
			}
	}

	public function testTheEyeTurnsTheTrainerOnAndOff(): void
	{
		$browser = $this->openProblem('readingEye', []);
		$browser->driver->executeScript('besogo.editor.setReadingOn(false);');

		$this->assertFalse($this->trainerOn($browser), 'A problem opens with the trainer off');

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, ['1,1' => 'normal'] + $this->givenPosition());
		$this->assertCount(4, $this->drawnStones($browser), 'Off draws everything with its real colour');

		$browser->clickId('readingToggle');
		$this->assertTrue($this->trainerOn($browser), 'The eye turns the trainer on');
		$this->waitForBoard($browser, $this->givenPosition());

		$browser->clickId('readingToggle');
		$this->assertFalse($this->trainerOn($browser), 'The same click turns it off again');
		$this->waitForBoard($browser, ['1,1' => 'normal'] + $this->givenPosition());
		$this->assertCount(4, $this->drawnStones($browser), 'Off draws everything with its real colour');
	}

	public function testTheEyeKeepsItsStateOnTheNextProblem(): void
	{
		$browser = $this->openProblem('readingEyeKeeps', ['pref_reading_played' => ReadingStones::Glass->value]);
		$browser->driver->executeScript('besogo.editor.setReadingOn(false);');

		$browser->clickId('readingToggle');
		$this->assertTrue($this->trainerOn($browser), 'The eye turns the trainer on');

		$browser->get('/' . $this->context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitUntilJs(
			'(function () {'
			. 'if (typeof besogo === "undefined" || !besogo.editor) return false;'
			. 'var n = besogo.editor.getCurrent(), s = n.getSize(), c = 0;'
			. 'for (var y = 1; y <= s.y; y++)'
			. '  for (var x = 1; x <= s.x; x++)'
			. '    if (n.getStone(x, y)) c++;'
			. 'return c > 0;'
			. '})()'
		);

		$this->assertTrue($this->trainerOn($browser), 'The switch is remembered, so the next problem opens the way this one was left');
	}

	public function testOnlyTheNewestMoveIsColoured(): void
	{
		$browser = $this->openProblem('readingNewest', [
			'pref_reading_played' => ReadingStones::Glass->value,
			'pref_reading_setup' => ReadingStones::Normal->value,
		]);

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, ['1,1' => 'glass'] + $this->givenPosition());

		$this->playNextMove($browser);
		$point = (string) $browser->driver->executeScript('var m = besogo.editor.getCurrent().move; return m.x + "," + m.y;');

		$stones = $this->drawnStones($browser);
		$this->assertSame('glass', $stones['1,1']['state'], 'The stone played before keeps its glass look');
		$this->assertSame('normal', $stones[$point]['state'], 'The stone played now shows its colour for a moment');

		$this->waitForBoard($browser, ['1,1' => 'glass', $point => 'glass'] + $this->givenPosition());
	}

	public function testTheMoveAndItsAnswerKeepTheirColourForAMoment(): void
	{
		$browser = $this->openProblem('readingGrace', [
			'pref_reading_played' => ReadingStones::Hidden->value,
			'pref_reading_setup' => ReadingStones::Normal->value,
		]);
		$grace = (int) $browser->driver->executeScript('return besogo.READING_GRACE_MS;');

		$this->playFirstMove($browser);
		$this->playNextMove($browser);

		$stones = $this->drawnStones($browser);
		$this->assertSame('normal', $stones['1,1']['state'], 'The move the player made keeps its colour');
		$this->assertSame('normal', $stones['1,2']['state'], 'The answer that landed with it keeps its colour too');
		$this->assertCount(5, $stones, 'Nothing is hidden yet');

		usleep((int) ($grace * 0.6) * 1000);
		$this->assertCount(5, $this->drawnStones($browser), 'Both stones are still there halfway through');

		$this->waitForBoard($browser, $this->givenPosition());
		$this->assertCount(3, $this->drawnStones($browser), 'After that moment both stones are gone');
	}

	public function testHiddenStoneLeavesNoLastMoveMarker(): void
	{
		$browser = $this->openProblem('readingMarker', [
			'pref_reading_played' => ReadingStones::Hidden->value,
			'pref_reading_setup' => ReadingStones::Normal->value,
		]);

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, $this->givenPosition());

		$this->assertSame(0, (int) $browser->driver->executeScript('return document.getElementById("markupGroup").children.length;'),
			'A marker on the last move would give the hidden stone away');
	}

	public function testSolvingRevealsEverything(): void
	{
		$browser = $this->openProblem('readingReveal', [
			'pref_reading_played' => ReadingStones::Hidden->value,
			'pref_reading_setup' => ReadingStones::Hidden->value,
		]);

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, []);
		$this->assertCount(0, $this->drawnStones($browser), 'Both axes hide the board while solving');

		$this->solve($browser);

		$stones = $this->drawnStones($browser);
		$this->assertCount(4, $stones, 'Solving shows the whole position again');
		foreach ($stones as $point => $stone)
			$this->assertSame('normal', $stone['state'], 'Stone at ' . $point . ' shows its colour once solved');
	}

	public function testResetStartsAFreshAttempt(): void
	{
		$browser = $this->openProblem('readingReset', [
			'pref_reading_played' => ReadingStones::Hidden->value,
			'pref_reading_setup' => ReadingStones::Normal->value,
		]);
		$grace = (int) $browser->driver->executeScript('return besogo.READING_GRACE_MS;');

		$this->playFirstMove($browser);
		$this->waitForBoard($browser, $this->givenPosition());
		$this->solve($browser);
		$this->assertCount(4, $this->drawnStones($browser), 'The solved position is fully visible');

		// The retry plays the same point, long after the first attempt lost its moment.
		usleep(($grace + 200) * 1000);
		$browser->driver->executeScript('document.getElementById("besogo-reset-button").click();');

		$this->playFirstMove($browser);

		$stones = $this->drawnStones($browser);
		$this->assertCount(4, $stones, 'The retry draws the move it just played');
		$this->assertSame('normal', $stones['1,1']['state'], 'The retry shows it in colour');

		$this->waitForBoard($browser, $this->givenPosition());
		$this->assertCount(3, $this->drawnStones($browser), 'The second attempt hides the played stone again');
	}

	public function testSemeaiProblemOffersNoTrainer(): void
	{
		$browser = $this->openProblem('readingSemeai', ['pref_reading_played' => ReadingStones::Glass->value], [
			'semeai_type' => 1,
			'min_lib' => 0,
			'max_lib' => 2,
			'liberty_count' => 6,
			'variance' => 2,
		]);

		$this->assertFalse(
			(bool) $browser->driver->executeScript('return besogo.editor.READING_ENABLED;'),
			'The trainer stays off a capturing race question'
		);
		$this->assertFalse($browser->idExists('readingToggle'), 'No eye toggle on a capturing race question');
	}

	public function testVariantProblemOffersNoTrainer(): void
	{
		$browser = $this->openProblem('readingVariant', ['pref_reading_setup' => ReadingStones::Hidden->value], [
			'variants' => [[
				'type' => 'multiple_choice',
				'answer1' => 'White is dead',
				'answer2' => 'Ko',
				'answer3' => 'Seki',
				'answer4' => 'Seki in gote',
				'numAnswer' => '3',
			]],
		]);

		$this->assertFalse(
			(bool) $browser->driver->executeScript('return besogo.editor.READING_ENABLED;'),
			'The trainer stays off a multiple choice question'
		);
		$this->assertFalse($browser->idExists('readingToggle'), 'No eye toggle on a multiple choice question');
	}
}
