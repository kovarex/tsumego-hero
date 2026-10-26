<?php

use App\Utility\Constants;

/**
 * Tests for board rotation functionality in besogo.
 *
 * Covers:
 * - Auto-rotation of boards too tall to view without scrolling
 * - Manual CW/CCW rotation buttons
 * - Rotation cycle (4 rotations = identity)
 * - Manual rotation after auto-rotation
 * - Manual rotation of a full board, which turns the stones
 *
 * These tests lock down behavior of rotateBoard() and autoRotate()
 * in editor.js.
 */
class RotationTest extends TestCaseWithAuth
{
	/** Tall regular board: the highest y exceeds x by 10, so it is turned on its side. */
	private const TALL_SGF = '(;GM[1]FF[4]SZ[19]AB[ab][ac][ad][ae][af][ag][ah][ai][aj][ak]AW[bb][bc][bd][be][bf][bg][bh][bi][bj][bk];B[al]C[+])';

	/** Tall vertical half board: the highest y exceeds x by 14, so it is turned on its side. */
	private const VERTICAL_HALF_SGF = '(;GM[1]FF[4]SZ[19]AB[aa][ab][ac][ad][ae][af][ag][ah][ai][aj][ak][al][am][an][ao]AW[ba][bb][bc][bd][be][bf][bg][bh][bi][bj][bk][bl][bm][bn][bo];B[ap]C[+])';

	/** Small corner problem — never triggers auto-rotation. */
	private const NORMAL_SGF = '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';

	/** Wide horizontal half board — never triggers auto-rotation. */
	private const WIDE_SGF = '(;GM[1]FF[4]SZ[19]AB[ba][ca][da][ea][fa][ga][ha][ia][ja][ka]AW[bb][cb][db][eb][fb][gb][hb][ib][jb][kb];B[la]C[+])';

	/** Stones in two opposite corners make the board show in full. */
	private const FULL_BOARD_SGF = '(;GM[1]FF[4]SZ[19]AB[aa][ss];B[jj]C[+])';

	private function getRotation(Browser $browser): int
	{
		return $browser->driver->executeScript('return besogo.scaleParameters["rotation"];');
	}

	private function getViewBox(Browser $browser): string
	{
		return $browser->driver->executeScript('return besogo.boardCanvasSvg.getAttribute("viewBox");');
	}

	/**
	 * @dataProvider autoRotationProvider
	 */
	public function testAutoRotation(string $sgf, bool $shouldRotate, string $reason)
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => $sgf],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$rotation = $this->getRotation($browser);
		if ($shouldRotate)
			$this->assertNotEquals(-1, $rotation, $reason);
		else
			$this->assertEquals(-1, $rotation, $reason);
	}

	public function autoRotationProvider(): array
	{
		return [
			'tall → rotates' => [self::TALL_SGF, true, 'Tall problem should auto-rotate'],
			'tall vertical half board → rotates' => [self::VERTICAL_HALF_SGF, true, 'Tall vertical half board should auto-rotate'],
			'normal → stays' => [self::NORMAL_SGF, false, 'Normal problem should not trigger auto-rotation'],
			'wide → stays' => [self::WIDE_SGF, false, 'Wide problem should not trigger auto-rotation'],
		];
	}

	public function testVerticalHalfBoardKeepsTheWideContainerAfterAutoRotation()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::VERTICAL_HALF_SGF],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$this->assertSame('vertical half board', $browser->driver->executeScript('return besogo.scaleParameters["boardCanvasSize"];'));
		$this->assertTrue($browser->driver->executeScript('return besogo.verticalHalfBoardRotationWidthToken;'));
	}

	/**
	 * @dataProvider manualRotationProvider
	 */
	public function testManualRotation(string $buttonId, string $direction)
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::NORMAL_SGF],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$initialViewBox = $this->getViewBox($browser);
		$this->assertEquals(-1, $this->getRotation($browser));

		$browser->clickId($buttonId);

		$this->assertNotEquals(-1, $this->getRotation($browser),
			"Rotation should change after $direction click");
		$this->assertNotEquals($initialViewBox, $this->getViewBox($browser),
			"ViewBox should change after $direction rotation");
	}

	public function manualRotationProvider(): array
	{
		return [
			'counter-clockwise' => ['boardSpinCounterClockwise', 'CCW'],
			'clockwise' => ['boardSpinClockwise', 'CW'],
		];
	}

	public function testFourCcwRotationsReturnToOriginalViewBox()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::NORMAL_SGF],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$originalViewBox = $this->getViewBox($browser);

		for ($i = 0; $i < 4; $i++)
			$browser->clickId('boardSpinCounterClockwise');

		$this->assertEquals($originalViewBox, $this->getViewBox($browser),
			'Four CCW rotations should return to original viewBox');
	}

	public function testManualRotationAfterAutoRotation()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::TALL_SGF],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$this->assertNotEquals(-1, $this->getRotation($browser), 'Auto-rotation should have triggered');
		$autoViewBox = $this->getViewBox($browser);

		$browser->clickId('boardSpinClockwise');
		$browser->assertNoJsErrors();
		$this->assertNotEquals($autoViewBox, $this->getViewBox($browser), 'Manual CW should change viewBox');

		$browser->clickId('boardSpinCounterClockwise');
		$browser->assertNoJsErrors();
		$this->assertEquals($autoViewBox, $this->getViewBox($browser), 'CCW after CW should restore viewBox');
	}

	public function testRotatingAFullBoardTurnsTheStones()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => self::FULL_BOARD_SGF],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		$this->assertSame('full-board', $browser->driver->executeScript('return besogo.scaleParameters["orientation"];'));
		$originalViewBox = $this->getViewBox($browser);

		// the stones are at A19 and T1 (internal 1/1 and 19/19), clockwise moves them to T19 and A1
		$this->assertEquals([true, false], $this->stonesAtA19AndT19($browser));
		$browser->clickId('boardSpinClockwise');
		$browser->assertNoJsErrors();
		$this->assertEquals([false, true], $this->stonesAtA19AndT19($browser));
		$this->assertEquals($originalViewBox, $this->getViewBox($browser), 'A full board keeps showing the whole board');
	}

	/** Whether there is a stone (of either colour, the colours may be swapped) at A19 and at T19. */
	private function stonesAtA19AndT19(Browser $browser): array
	{
		return $browser->driver->executeScript('let current = besogo.editor.getCurrent();
			return [current.getStone(1, 1) !== 0, current.getStone(19, 1) !== 0];');
	}
}
