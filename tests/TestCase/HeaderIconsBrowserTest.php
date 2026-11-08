<?php

use Facebook\WebDriver\WebDriverDimension;

//The icons of the mistake training and the challenges in the menu bar
#[RetryOnBrowserFailure]
class HeaderIconsBrowserTest extends IntegrationTestCase
{
	//At 1280 px the icons stand between the menu and the flame, on neither of them
	public function testTheIconsOverlapNeitherTheMenuNorTheFlame(): void
	{
		new ContextPreparator(['user' => ['display_name' => 'LongPlayerName', 'streak_length' => 12, 'streak_best' => 20]]);
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$window->setSize(new WebDriverDimension(1280, 900));
			$browser->get('sites/blank');
			$boxes = $browser->driver->executeScript(<<<'JS'
				const box = element => { const r = element.getBoundingClientRect(); return {left: r.left, right: r.right, top: r.top, bottom: r.bottom}; };
				const items = [...document.querySelectorAll('#newMenu nav > ul > li')].filter(item => item.getBoundingClientRect().width > 0);
				return {icons: box(document.querySelector('.header-icons')), menu: box(items[items.length - 1]), flame: box(document.querySelector('#daily-streak'))};
				JS);
			$icons = $boxes['icons'];
			$this->assertLessThanOrEqual($icons['left'], $boxes['menu']['right'], 'The icons ' . json_encode($icons) . ' start right of the menu ' . json_encode($boxes['menu']));
			$this->assertLessThanOrEqual($boxes['flame']['left'], $icons['right'], 'The icons ' . json_encode($icons) . ' end left of the flame ' . json_encode($boxes['flame']));
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
