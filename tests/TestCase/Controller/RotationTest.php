<?php

use Facebook\WebDriver\WebDriverDimension;

/**
 * Tests for board rotation functionality in besogo.
 *
 * Covers:
 * - Auto-rotation based on board/viewport aspect ratio mismatch
 * - Manual CW/CCW rotation buttons
 * - Rotation cycle (4 rotations = identity)
 * - Manual rotation after auto-rotation
 *
 * These tests lock down behavior of rotateBoard() and autoRotate()
 * in editor.js.
 */
class RotationTest extends TestCaseWithAuth
{
	/** Tall vertical half board — triggers auto-rotation on landscape screens. */
	private const TALL_SGF = '(;GM[1]FF[4]SZ[19]AB[ab][ac][ad][ae][af][ag][ah][ai][aj][ak]AW[bb][bc][bd][be][bf][bg][bh][bi][bj][bk];B[al]C[+])';

	/** Small corner problem — never triggers auto-rotation. */
	private const NORMAL_SGF = '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';

	/** Wide horizontal half board — triggers auto-rotation on portrait screens. */
	private const WIDE_SGF = '(;GM[1]FF[4]SZ[19]AB[ba][ca][da][ea][fa][ga][ha][ia][ja][ka]AW[bb][cb][db][eb][fb][gb][hb][ib][jb][kb];B[la]C[+])';

	private function getRotation(Browser $browser): int
	{
		return $browser->driver->executeScript('return besogo.scaleParameters["rotation"];');
	}

	private function getViewBox(Browser $browser): string
	{
		return $browser->driver->executeScript('return besogo.boardCanvasSvg.getAttribute("viewBox");');
	}

	private function resizeWindow(Browser $browser, int $width, int $height): WebDriverDimension
	{
		$original = $browser->driver->manage()->window()->getSize();
		$browser->driver->manage()->window()->setSize(new WebDriverDimension($width, $height));
		return $original;
	}

	/**
	 * @dataProvider autoRotationProvider
	 */
	public function testAutoRotation(string $sgf, ?int $width, ?int $height, bool $shouldRotate, string $reason)
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => $sgf],
		]);

		$browser = Browser::instance();
		$original = null;

		if ($width !== null)
			$original = $this->resizeWindow($browser, $width, $height);

		try
		{
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$browser->waitForBoard();

			$rotation = $this->getRotation($browser);

			if ($shouldRotate)
				$this->assertNotEquals(-1, $rotation, $reason);
			else
				$this->assertEquals(-1, $rotation, $reason);
		}
		finally
		{
			if ($original !== null)
				$browser->driver->manage()->window()->setSize($original);
		}
	}

	public function autoRotationProvider(): array
	{
		return [
			'tall on landscape → rotates' => [self::TALL_SGF, null, null, true,
				'Tall problem should auto-rotate on landscape screen'],
			'normal on landscape → stays' => [self::NORMAL_SGF, null, null, false,
				'Normal problem should not trigger auto-rotation'],
			'tall on portrait → stays' => [self::TALL_SGF, 400, 1200, false,
				'Tall problem fits portrait screen, should not rotate'],
			'wide on portrait → rotates' => [self::WIDE_SGF, 400, 1200, true,
				'Wide problem should auto-rotate on portrait screen'],
			'wide on landscape → stays' => [self::WIDE_SGF, 1280, 720, false,
				'Wide problem fits landscape screen, should not rotate'],
		];
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
}
