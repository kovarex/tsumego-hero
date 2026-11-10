<?php

use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\Attributes\Group;

//The row of problem buttons of the play page shows as many neighbours of the open problem as its width holds:
//5 on each side from a 1280px window, then 3, then 1, always on one line and inside the row
#[Group('browser')]
#[RetryOnBrowserFailure]
class ProblemNavFitBrowserTest extends IntegrationTestCase
{
	private const WIDTHS = [320, 360, 390, 480, 600, 768, 900, 1000, 1024, 1100, 1200, 1240, 1280, 1366, 1600, 1920];

	public function testTheRowFitsEveryWidthAroundAProblemInTheMiddle(): void
	{
		$context = $this->setOfThirty();
		$this->checkEveryWidth($context->tsumegos[14]['set-connections'][0]['id'], true);
	}

	//At the start of a set the row fills its room with the problems after the open one
	public function testTheRowFitsEveryWidthAtTheFirstProblem(): void
	{
		$context = $this->setOfThirty();
		$this->checkEveryWidth($context->tsumegos[0]['set-connections'][0]['id'], false);
	}

	private function setOfThirty(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['name' => 'rowFitUser'],
			'tsumegos' => array_map(fn($number) => ['sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]AW[dd])', 'sets' => [['name' => 'row set', 'num' => $number]]], range(1, 30))]);
	}

	private function checkEveryWidth(int $setConnectionID, bool $inTheMiddle): void
	{
		$browser = Browser::instance();
		$browser->get('/' . $setConnectionID);
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			foreach (self::WIDTHS as $width)
			{
				$this->setViewportWidth($browser, $width);
				$row = $browser->driver->executeScript(<<<'JS'
					const row = document.querySelector('.problem-nav__inner');
					const box = row.getBoundingClientRect();
					const items = [...row.querySelectorAll('.problem-nav__item')];
					const current = items.findIndex(item => item.classList.contains('problem-nav__item--current'));
					const shown = items.map((item, index) => ({item, index})).filter(({item}) => getComputedStyle(item).display !== 'none');
					const boxes = shown.map(({item}) => item.getBoundingClientRect());
					return {
						viewport: innerWidth,
						count: shown.length,
						before: shown.filter(({index}) => index > 0 && index < current).length,
						after: shown.filter(({index}) => index > current && index < items.length - 1).length,
						lines: new Set(boxes.map(button => Math.round(button.top))).size,
						inside: boxes.every(button => button.left >= box.left - 0.5 && button.right <= box.right + 0.5),
						pageScrolls: document.documentElement.scrollWidth > document.documentElement.clientWidth};
					JS);
				$at = 'At ' . $row['viewport'] . 'px ';
				$this->assertSame(1, $row['lines'], $at . 'the buttons are on one line');
				$this->assertTrue($row['inside'], $at . 'every button is inside the row');
				$this->assertFalse($row['pageScrolls'], $at . 'the page does not scroll sideways');
				$this->assertContains($row['count'], [5, 9, 13], $at . 'the row is a full row of 5, 9 or 13');
				if ($row['viewport'] >= 1280)
				{
					$this->assertSame(13, $row['count'], $at . 'all 13 buttons show');
					if ($inTheMiddle)
						$this->assertSame([5, 5], [$row['before'], $row['after']], $at . '5 on each side of the open problem');
				}
			}
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}

	//The window is wider than the page by its frame and scrollbar: the viewport is what the row is measured in
	private function setViewportWidth(Browser $browser, int $width): void
	{
		$window = $browser->driver->manage()->window();
		$window->setSize(new WebDriverDimension($width, 900));
		$chrome = $width - (int) $browser->driver->executeScript('return innerWidth;');
		if ($chrome > 0)
			$window->setSize(new WebDriverDimension($width + $chrome, 900));
	}
}
