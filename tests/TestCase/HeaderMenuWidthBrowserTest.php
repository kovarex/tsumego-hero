<?php

use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\Attributes\Group;

//The full menu shows only where it fits, the hamburger below that: at no width does the page scroll sideways, and
//where the full menu shows, the account row (or Sign In) starts after its last item instead of over it
#[Group('browser')]
#[RetryOnBrowserFailure]
class HeaderMenuWidthBrowserTest extends IntegrationTestCase
{
	private const WIDTHS = [320, 390, 768, 1000, 1020, 1040, 1060, 1080, 1100, 1120, 1140, 1160, 1180, 1200, 1220, 1240, 1260, 1280, 1300, 1320, 1340, 1360, 1380, 1400, 1920];

	public function testSignedOutHeaderFitsEveryWidth(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->driver->manage()->deleteAllCookies();
		$browser->getAnonymous('/');
		$this->assertHeaderFits($browser, '.site-nav__signin');
	}

	//The widest header: a long name, the icons of the mistake training and the challenges, the flame of a streak
	public function testSignedInHeaderFitsEveryWidth(): void
	{
		new ContextPreparator(['user' => ['display_name' => 'AVeryLongPlayerNameForTheHeader', 'streak_length' => 12, 'streak_best' => 20]]);
		$browser = Browser::instance();
		$browser->get('/');
		$this->assertHeaderFits($browser, '.site-header-account');
	}

	private function assertHeaderFits(Browser $browser, string $accountSelector): void
	{
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			foreach (self::WIDTHS as $width)
			{
				$window->setSize(new WebDriverDimension($width, 900));
				$state = $browser->driver->executeScript(<<<'JS'
					const page = document.documentElement;
					const fullMenu = getComputedStyle(document.querySelector('.nav-toggle-btn')).display === 'none';
					const shown = element => element.getBoundingClientRect().width > 0;
					const items = [...document.querySelectorAll('#newMenu nav > ul > li')].filter(shown);
					const account = [...document.querySelectorAll(arguments[0] + ' *')].filter(shown);
					return {
						scrollWidth: page.scrollWidth,
						clientWidth: page.clientWidth,
						fullMenu: fullMenu,
						menuEnd: items.length ? Math.max(...items.map(item => item.getBoundingClientRect().right)) : 0,
						accountStart: account.length ? Math.min(...account.map(element => element.getBoundingClientRect().left)) : Infinity};
					JS, [$accountSelector]);
				$this->assertSame($state['clientWidth'], $state['scrollWidth'], 'At ' . $width . 'px the page does not scroll sideways');
				if ($state['fullMenu'])
					$this->assertLessThanOrEqual($state['accountStart'], $state['menuEnd'], 'At ' . $width . 'px the account row starts after the menu');
			}
			$window->setSize(new WebDriverDimension(1200, 900));
			$this->assertFalse($browser->driver->executeScript("return getComputedStyle(document.querySelector('.nav-toggle-btn')).display === 'none';"), 'At 1200px the hamburger shows');
			$window->setSize(new WebDriverDimension(1300, 900));
			$this->assertTrue($browser->driver->executeScript("return getComputedStyle(document.querySelector('.nav-toggle-btn')).display === 'none';"), 'At 1300px the full menu shows');
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
