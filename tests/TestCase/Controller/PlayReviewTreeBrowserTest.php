<?php

use Facebook\WebDriver\WebDriverDimension;

#[RetryOnBrowserFailure]
class PlayReviewTreeBrowserTest extends IntegrationTestCase
{
	//A tree too big for its box both ways: 25 first moves, each followed by a 40 move line
	private static function bigTreeSgf(): string
	{
		$letters = 'abcdefghijklmnopqrs';
		$line = '';
		for ($i = 0; $i < 40; $i++)
			$line .= ';' . ($i % 2 === 0 ? 'W' : 'B') . '[' . $letters[$i % 19] . $letters[2 + intdiv($i, 19)] . ']';
		$variations = '';
		for ($i = 0; $i < 25; $i++)
			$variations .= '(;B[' . $letters[$i % 19] . $letters[18 - intdiv($i, 19)] . ']' . $line . ')';
		return '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[jj]' . $variations . ')';
	}

	private function rectangles(Browser $browser): array
	{
		return $browser->driver->executeScript(<<<'JS'
			const rect = (element) => { const box = element.getBoundingClientRect(); return {top: box.top, bottom: box.bottom, left: box.left, right: box.right}; };
			const tree = document.querySelector('.besogo-tree');
			return {
				tree: rect(tree),
				treeScroll: {top: tree.scrollTop, height: tree.scrollHeight, clientHeight: tree.clientHeight, width: tree.scrollWidth, clientWidth: tree.clientWidth, horizontalBar: tree.offsetHeight - tree.clientHeight},
				buttonsShown: document.querySelector('.besogo-control').checkVisibility(),
				viewportHeight: window.innerHeight,
				pageScroll: window.scrollY};
			JS);
	}

	//On a phone the panels sit under the board, so the tree box takes the board's whole width and a small tree fits in it.
	public function testTreeTakesTheWholeWidthOnAPhone(): void
	{
		$sgf = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[jj](;B[aa];W[ab];B[ba];W[bb];B[ca]C[+])(;B[ac];W[ab];B[ba];W[bb])(;B[ad];W[ab];B[ba];W[bb];B[ca];W[cb]))';
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => $sgf]]);
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$window->setSize(new WebDriverDimension(390, 844));
			$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
			$browser->clickId('besogo-review-button');
			$browser->waitUntilJs('document.querySelector(".besogo-tree svg") !== null');
			$sizes = $browser->driver->executeScript(<<<'JS'
				const tree = document.querySelector('.besogo-tree');
				return {tree: tree.getBoundingClientRect().width, board: document.querySelector('#target').getBoundingClientRect().width, scrollWidth: tree.scrollWidth, clientWidth: tree.clientWidth};
				JS);
			$this->assertGreaterThan($sizes['board'] * 0.9, $sizes['tree'], 'The tree box is as wide as the board');
			$this->assertLessThanOrEqual($sizes['clientWidth'], $sizes['scrollWidth'], 'A small tree fits without scrolling');
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}

	//The tree scrolls in its own box both ways, so its horizontal scrollbar is on screen wherever the tree is
	//scrolled to. The play page shows no back/forward buttons under it: the review moves by a click on the
	//tree or on the board.
	public function testBigTreeScrollsInsideItsBoxWithoutButtons(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::bigTreeSgf()]]);
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			foreach ([new WebDriverDimension(1400, 900), new WebDriverDimension(420, 860)] as $size)
			{
				$window->setSize($size);
				$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
				$browser->clickId('besogo-review-button');
				$browser->waitUntilJs('document.querySelector(".besogo-tree svg") !== null');
				$browser->driver->executeScript('document.querySelector(".besogo-tree").scrollIntoView({block: "start"});');
				$before = $this->rectangles($browser);
				$scroll = $before['treeScroll'];
				$this->assertGreaterThan($scroll['clientHeight'], $scroll['height'], 'The tree overflows vertically');
				$this->assertGreaterThan($scroll['clientWidth'], $scroll['width'], 'The tree overflows horizontally');
				$this->assertGreaterThan(0, $scroll['horizontalBar'], 'The tree box has its own horizontal scrollbar');
				$this->assertLessThanOrEqual($before['viewportHeight'], $before['tree']['bottom'], 'The whole tree box, with its horizontal scrollbar, is on screen');
				$this->assertFalse($before['buttonsShown'], 'The review has no back/forward buttons');

				$browser->driver->executeScript('document.querySelector(".besogo-tree").scrollTop = 1000000;');
				$after = $this->rectangles($browser);
				$this->assertGreaterThan(0, $after['treeScroll']['top'], 'The tree box scrolled');
				$this->assertSame($before['pageScroll'], $after['pageScroll'], 'Scrolling the tree does not scroll the page');
				$this->assertEquals($before['tree'], $after['tree'], 'Scrolling the tree does not move its box');
			}
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
