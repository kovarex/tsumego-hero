<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../Browser.php');
require_once(__DIR__ . '/../../ContextPreparator.php');
use Facebook\WebDriver\WebDriverBy;

class SetViewFilterTest extends TestCaseWithAuth
{
	public function testFilterButtonHiddenWhenNoFiltersActive(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Filter Test Set', 'num' => '1']]],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/view/' . $setId);

		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('none', $parent->getCssValue('display'));
	}

	public function testFilterButtonVisibleWhenRanksActive(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Filter Test Set', 'num' => '1']]],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/view/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k']);
		$browser->getAnonymous('sets/view/' . $setId);

		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('inline-block', $parent->getCssValue('display'));
	}

	public function testFilterButtonVisibleWhenSetsActive(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Filter Test Set', 'num' => '1']]],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/view/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->driver->manage()->addCookie(['name' => 'filtered_sets', 'value' => 'Filter Test Set']);
		$browser->getAnonymous('sets/view/' . $setId);

		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('inline-block', $parent->getCssValue('display'));
	}

	public function testClearFiltersHidesButton(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Filter Test Set', 'num' => '1']]],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/view/' . $setId);
		$browser->driver->manage()->deleteAllCookies();
		$browser->getAnonymous('sets/view/' . $setId);
		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('none', $parent->getCssValue('display'));

		$browser->driver->manage()->addCookie(['name' => 'filtered_ranks', 'value' => '15k']);
		$browser->getAnonymous('sets/view/' . $setId);
		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('inline-block', $parent->getCssValue('display'));

		$browser->driver->manage()->deleteAllCookies();
		$browser->getAnonymous('sets/view/' . $setId);
		$parent = $browser->driver->findElement(WebDriverBy::cssSelector('.showFilters'));
		$this->assertSame('none', $parent->getCssValue('display'));
	}

	public function testPreviewToggleAlwaysVisible(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['sets' => [['name' => 'Filter Test Set', 'num' => '1']]],
		]);
		$setId = $context->tsumegos[0]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->getAnonymous('sets/view/' . $setId);

		$toggle = $browser->driver->findElement(WebDriverBy::cssSelector('.preview-zoom-toggle'));
		$this->assertTrue($toggle->isDisplayed());
	}
}
