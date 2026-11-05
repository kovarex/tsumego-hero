<?php

//The popup of a completed achievement (webroot/js/AchievementAlerts.js) fades in, and its close button fades it out
#[RetryOnBrowserFailure]
class AchievementPopupBrowserTest extends IntegrationTestCase
{
	public function testThePopupFadesInAndItsCloseButtonFadesItOut(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->driver->executeScript("showAchievementPopup({id: 7, name: 'Test', description: 'Solve a problem', xp: 100, image: 'a1', color: 'gold'});");

		$browser->waitUntilCssSelectorDisplayed('.toast--achievement');
		$popup = $browser->find('.toast--achievement');
		$this->assertStringContainsString('Test - Solve a problem (100 XP)', $popup->getText());
		$browser->waitUntilJs('getComputedStyle(document.querySelector(".toast--achievement")).opacity === "1"');

		$browser->find('.toast--achievement .toast__close')->click();
		$browser->waitUntilCssSelectorNotDisplayed('.toast--achievement');
		$this->assertSame('none', $browser->driver->executeScript('return document.querySelector(".toast--achievement").style.display;'));
	}
}
