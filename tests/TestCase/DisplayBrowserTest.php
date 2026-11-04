<?php

//Display (webroot/js/Display.js) shows, hides and fades like the jQuery the scripts were written for
#[RetryOnBrowserFailure]
class DisplayBrowserTest extends IntegrationTestCase
{
	//Runs $script on a blank page that has the elements of $html and the styles of $css; the script ends with done(result)
	private function runOnPage(string $html, string $css, string $script): mixed
	{
		new ContextPreparator(['user' => null]);
		$browser = Browser::instance();
		$browser->get('sites/blank');
		$browser->driver->executeScript('let style = document.createElement("style"); style.textContent = arguments[1]; document.head.appendChild(style);
			let box = document.createElement("div"); box.id = "display-test"; box.innerHTML = arguments[0]; document.body.appendChild(box);', [$html, $css]);
		return $browser->driver->executeAsyncScript('let done = arguments[arguments.length - 1]; (async () => { ' . $script . ' })().catch(error => done("error: " + error));');
	}

	public function testShowGivesTheDisplayOfTheTagToWhatCssOrAnInlineStyleHides(): void
	{
		$result = $this->runOnPage(
			'<div id="cssHidden"></div><span id="inlineHidden" style="display: none"></span><span id="cssHiddenSpan"></span><div id="flex" style="display: flex"></div>',
			'#cssHidden, #cssHiddenSpan { display: none; }',
			'Display.show("#cssHidden"); Display.show("#inlineHidden"); Display.show("#cssHiddenSpan");
			Display.hide("#flex"); let hiddenFlex = getComputedStyle(flex).display; Display.show("#flex");
			done([getComputedStyle(cssHidden).display, inlineHidden.style.display, getComputedStyle(inlineHidden).display,
				getComputedStyle(cssHiddenSpan).display, hiddenFlex, getComputedStyle(flex).display]);');
		$this->assertSame(['block', '', 'inline', 'inline', 'none', 'flex'], $result);
	}

	public function testHideAndShowTakeEveryElementOfASelector(): void
	{
		$result = $this->runOnPage(
			'<div class="toast"></div><div class="toast"></div>',
			'.toast { display: none; }',
			'Display.show(".toast"); let shown = [...document.querySelectorAll(".toast")].map(toast => getComputedStyle(toast).display);
			Display.hide(document.querySelectorAll(".toast")); done([shown, [...document.querySelectorAll(".toast")].map(toast => toast.style.display)]);');
		$this->assertSame([['block', 'block'], ['none', 'none']], $result);
	}

	//The element shows at the start of fadeIn, with its opacity rising from 0, and hides at the end of fadeOut
	public function testFadesShowAtTheStartAndHideAtTheEnd(): void
	{
		$result = $this->runOnPage(
			'<div id="faded"></div>',
			'#faded { display: none; }',
			'let fadeIn = Display.fadeIn("#faded", 200);
			let atStart = [getComputedStyle(faded).display, parseFloat(getComputedStyle(faded).opacity) < 0.2];
			await new Promise(resolve => setTimeout(resolve, 100));
			let midway = parseFloat(faded.style.opacity);
			await fadeIn;
			let shown = [getComputedStyle(faded).display, faded.style.opacity];
			let fadeOut = Display.fadeOut("#faded", 200);
			let whileFading = getComputedStyle(faded).display;
			await fadeOut;
			done([atStart, midway > 0 && midway < 1, shown, whileFading, getComputedStyle(faded).display, faded.style.opacity]);');
		$this->assertSame([['block', true], true, ['block', ''], 'block', 'none', ''], $result);
	}

	//Like jQuery's queue, the fades of an element run one after another: a quick toggle ends as the last one asked
	public function testQuickTogglesRunInTurn(): void
	{
		$result = $this->runOnPage(
			'<div id="toggled"></div><div id="shown"></div>',
			'#toggled { display: none; }',
			'Display.fadeIn("#toggled", 150);
			let fadeOut = Display.fadeOut("#toggled", 150);
			await new Promise(resolve => setTimeout(resolve, 75));
			let duringFadeIn = [getComputedStyle(toggled).display, parseFloat(toggled.style.opacity) < 1];
			await fadeOut;
			let afterInAndOut = getComputedStyle(toggled).display;
			Display.fadeIn("#toggled", 50); Display.fadeOut("#toggled", 50);
			await Display.fadeIn("#toggled", 50);
			let afterInOutIn = getComputedStyle(toggled).display;
			await Display.fadeIn("#shown", 50);
			let shownStays = shown.style.opacity;
			await Display.fadeOut("#toggled", 50);
			let hiddenOnce = getComputedStyle(toggled).display;
			await Display.fadeOut("#toggled", 50);
			done([duringFadeIn, afterInAndOut, afterInOutIn, shownStays, hiddenOnce, getComputedStyle(toggled).display]);');
		$this->assertSame([['block', true], 'none', 'block', '', 'none', 'none'], $result);
	}
}
