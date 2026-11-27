<?php

use Facebook\WebDriver\WebDriverKeys;

//The card of a completed achievement (webroot/js/AchievementAlerts.js): a dialog in the middle of the page, one card at
//a time when several come at once, closed by its buttons, Esc or the backdrop, giving the keys back to the page
#[RetryOnBrowserFailure]
class AchievementPopupBrowserTest extends IntegrationTestCase
{
	private const string CARDS = "[
		{id: 7, name: 'First', description: 'Solve a problem', xp: 100, image: 'ac001', color: 'achievementColorGold'},
		{id: 8, name: 'Second', description: 'Solve two problems', xp: 200, image: 'ac002', color: 'achievementColorBlack'},
		{id: 9, name: 'Third', description: 'Solve it again', xp: 0, image: 'ac003', color: 'achievementColorTeal'}]";

	private function show(int $count): Browser
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->driver->executeScript(self::CARDS . '.slice(0, ' . $count . ').forEach(showAchievementPopup);');
		$browser->waitUntilCssSelectorDisplayed('.achievement-card');
		return $browser;
	}

	private function card(Browser $browser): array
	{
		return $browser->driver->executeScript('const card = document.querySelector(".achievement-card");
			return card ? {name: card.querySelector(".achievement-card__name").textContent, step: card.querySelector(".achievement-card__step").textContent,
				next: card.querySelector(".achievement-card__next").textContent, text: card.innerText, open: card.open} : null;');
	}

	public function testTheCardSitsInTheMiddleOfThePageAndContinueClosesIt(): void
	{
		$browser = $this->show(1);
		$browser->waitUntilJs('getComputedStyle(document.querySelector(".achievement-card")).opacity === "1"');
		$offset = $browser->driver->executeScript('const box = document.querySelector(".achievement-card").getBoundingClientRect();
			return [box.left + box.width / 2 - innerWidth / 2, box.top + box.height / 2 - innerHeight / 2];');
		$this->assertLessThanOrEqual(2, abs($offset[0]), 'The card is centred horizontally');
		$this->assertLessThanOrEqual(2, abs($offset[1]), 'The card is centred vertically');

		$card = $this->card($browser);
		$this->assertTrue($card['open']);
		$this->assertSame('First', $card['name']);
		$this->assertStringContainsString('Solve a problem', $card['text']);
		$this->assertStringContainsString('+100 XP', $card['text']);
		$this->assertSame('', $card['step'], 'One card alone has no "1 of 1"');
		$this->assertSame('Continue', $card['next']);
		$this->assertSame('/achievements/view/7', $browser->driver->executeScript('return document.querySelector(".achievement-card a.btn").getAttribute("href");'));

		$browser->find('.achievement-card__next')->click();
		$browser->waitUntilCssSelectorDoesntExist('.achievement-card');
	}

	public function testSeveralWaitInAQueueAndShowOneAtATime(): void
	{
		$browser = $this->show(3);
		$this->assertSame(1, $browser->driver->executeScript('return document.querySelectorAll(".achievement-card").length;'), 'Never two cards on top of each other');
		$card = $this->card($browser);
		$this->assertSame(['First', '1 of 3', 'Next'], [$card['name'], $card['step'], $card['next']]);

		$browser->find('.achievement-card__next')->click();
		$browser->waitUntilJs('document.querySelector(".achievement-card__name")?.textContent === "Second"');
		$this->assertSame('2 of 3', $this->card($browser)['step']);

		// Esc closes the card shown, and the next comes
		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
		$browser->waitUntilJs('document.querySelector(".achievement-card__name")?.textContent === "Third"');
		$card = $this->card($browser);
		$this->assertSame(['3 of 3', 'Continue'], [$card['step'], $card['next']]);
		$this->assertStringNotContainsString('XP', $card['text'], 'A repeat that grants no XP shows none');

		// a click on the backdrop, outside the card, closes the last one
		$browser->driver->executeScript('const card = document.querySelector(".achievement-card"), box = card.getBoundingClientRect();
			card.dispatchEvent(new MouseEvent("click", {bubbles: true, clientX: box.left - 10, clientY: box.top - 10}));');
		$browser->waitUntilCssSelectorDoesntExist('.achievement-card');
	}

	public function testTheKeysGoBackToThePageWhenTheCardCloses(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		// a focusable spot of the page and a count of the arrows the page gets, like the board's keys
		$browser->driver->executeScript('const spot = document.createElement("button"); spot.id = "spot"; spot.textContent = "spot"; document.body.append(spot); spot.focus();
			window.arrows = 0; document.addEventListener("keydown", event => { if (event.key === "ArrowRight") window.arrows++; });');
		$browser->driver->executeScript(self::CARDS . '.slice(0, 1).forEach(showAchievementPopup);');
		$browser->waitUntilCssSelectorDisplayed('.achievement-card');
		$this->assertSame('achievement-card__next', $browser->driver->executeScript('return document.activeElement.classList[2];'), 'The card takes the focus');

		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
		$this->assertSame(0, $browser->driver->executeScript('return window.arrows;'), 'The page gets no arrow while the card is open');

		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);
		$browser->waitUntilCssSelectorDoesntExist('.achievement-card');
		$this->assertSame('spot', $browser->driver->executeScript('return document.activeElement.id;'), 'The focus is back where it was');
		$browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
		$this->assertSame(1, $browser->driver->executeScript('return window.arrows;'), 'The page gets the arrows again');
	}

	public function testWithReducedMotionTheCardOnlyFades(): void
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		// the browser of the tests can't ask for reduced motion, so the test reads the rules the page has (as JSON:
		// Firefox returns an object keyed by selectors empty)
		$rules = json_decode($browser->driver->executeScript('const found = {};
			const walk = (rules, reduced) => { for (const rule of rules) {
				if (rule instanceof CSSMediaRule) walk(rule.cssRules, reduced || rule.conditionText.includes("prefers-reduced-motion: reduce"));
				else if (rule.cssRules && rule.cssRules.length && !(rule instanceof CSSStyleRule)) walk(rule.cssRules, reduced);
				else if (reduced && rule instanceof CSSStyleRule && rule.selectorText.includes("achievement-card"))
					for (const selector of rule.selectorText.split(",")) found[selector.trim()] = Object.assign(found[selector.trim()] || {}, {animation: rule.style.animation, name: rule.style.animationName, display: rule.style.display});
			} };
			for (const sheet of document.styleSheets) try { walk(sheet.cssRules, false); } catch (e) {}
			return JSON.stringify(found);'), true);
		$this->assertSame('achievement-card-fade', $rules['.achievement-card']['name'] ?? null, 'The card only fades in');
		$this->assertContains($rules['.achievement-card__medal']['animation'] ?? null, ['none', 'auto ease 0s 1 normal none running none'], 'The glow does not pulse');
		$this->assertSame('none', $rules['.achievement-card__medal::after']['display'] ?? null, 'No shine sweeps over the image');
	}
}
