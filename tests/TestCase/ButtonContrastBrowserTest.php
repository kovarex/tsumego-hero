<?php

use App\Utility\Auth;
use App\Utility\Constants;

//Every button and toggle reads at WCAG AA, at rest and under the pointer, in the light and the dark theme: 4.5:1 for its
//text, 3:1 for a large or bold label of 18.66px and more. The colours are taken from the label itself and from the fills
//really behind it, a transparent fill resolved through its parents. A disabled one is exempt, as in WCAG.
#[RetryOnBrowserFailure]
class ButtonContrastBrowserTest extends IntegrationTestCase
{
	private const string BUTTONS = '.btn, button, input[type=submit], input[type=button], summary, .subnav__link, .dropdown__tile, .pagination__link, .chart__legend-item';

	//Tags the buttons shown with data-contrast and returns how many there are
	private const string PREPARE = <<<'JS'
		document.querySelectorAll('[data-contrast]').forEach(el => el.removeAttribute('data-contrast'));
		const list = [...new Set(document.querySelectorAll(arguments[0]))].filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0
			&& el.checkVisibility({visibilityProperty: true}) && (el.innerText || el.value || '').trim() !== ''; });
		list.forEach((el, i) => el.setAttribute('data-contrast', i));
		return list.length;
		JS;

	//The worst contrast of the text of a button against what is behind it: null when it is disabled
	private const string MEASURE = <<<'JS'
		const el = document.querySelector('[data-contrast="' + arguments[0] + '"]');
		if (el.disabled || el.getAttribute('aria-disabled') === 'true' || el.classList.contains('btn--disabled') || /-inactive$/.test(el.id) || el.classList.contains('play-actions__button--disabled'))
			return null;
		const parse = c => { let m = c.match(/color\(srgb ([^)]+)\)/); if (m) { const p = m[1].split(/[ /]+/).filter(Boolean).map(Number); return [p[0] * 255, p[1] * 255, p[2] * 255, p.length > 3 ? p[3] : 1]; } m = c.match(/rgba?\(([^)]+)\)/); if (!m) return [0, 0, 0, 0]; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
		const over = (top, bottom) => { const a = top[3] + bottom[3] * (1 - top[3]); return a === 0 ? [0, 0, 0, 0] : [0, 1, 2].map(i => (top[i] * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / a).concat([a]); };
		const canvas = getComputedStyle(document.documentElement).colorScheme.includes('dark') ? [0, 0, 0, 1] : [255, 255, 255, 1];
		const fillOf = n => { const layers = []; for (let m = n; m && m.nodeType === 1; m = m.parentElement) layers.push(parse(getComputedStyle(m).backgroundColor)); return layers.reverse().reduce((b, l) => over(l, b), canvas); };
		let opacity = 1;
		for (let m = el; m && m.nodeType === 1; m = m.parentElement) opacity *= Number(getComputedStyle(m).opacity);
		const luminance = c => { const v = c.slice(0, 3).map(x => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
		const holders = [el, ...el.querySelectorAll('*')].filter(n => (n === el && (el.value || '').trim() !== '') || [...n.childNodes].some(c => c.nodeType === 3 && c.textContent.trim() !== ''))
			.filter(n => n.checkVisibility({visibilityProperty: true}));
		let worst = null;
		for (const n of holders.length ? holders : [el])
		{
			const fill = fillOf(n), drawn = over(parse(getComputedStyle(n).color), fill), text = [0, 1, 2].map(i => drawn[i] * opacity + fill[i] * (1 - opacity));
			const a = luminance(text), b = luminance(fill), ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
			const style = getComputedStyle(n), large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && parseInt(style.fontWeight, 10) >= 700);
			if (!worst || ratio / (large ? 3 : 4.5) < worst.ratio / worst.threshold)
				worst = {ratio: Math.round(ratio * 100) / 100, threshold: large ? 3 : 4.5};
		}
		worst.label = (el.innerText || el.value || '').trim().replace(/\s+/g, ' ').slice(0, 30);
		return worst;
		JS;

	public static function themes(): array
	{
		return ['light' => ['light'], 'dark' => ['dark']];
	}

	#[PHPUnit\Framework\Attributes\DataProvider('themes')]
	public function testEveryButtonReadsAtRestAndUnderThePointer(string $theme): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true],
			'tsumegos' => [['set_order' => 1, 'sets' => [['name' => 'Basics', 'num' => 1]], 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19]AW[bb]AB[dd];B[ca]C[+])', 'first_move_color' => 'B']],
				['set_order' => 2, 'sets' => [['name' => 'Basics', 'num' => 2]]]]]);
		$browser = Browser::instance();
		$failures = [];
		$pages = [
			'collections, filtered by difficulty' => ['/sets', 'difficulty'],
			'collections, filtered by tag' => ['/sets', 'tags'],
			'play' => ['/' . $context->tsumegos[0]['set-connections'][0]['id'], null],
			'settings' => ['/users/settings', null],
			'highscore' => ['/users/highscore', null],
			'friends' => ['/friends', null],
			'challenges' => ['/challenges', null]];
		foreach ($pages as $name => [$url, $query])
			$failures = array_merge($failures, $this->failuresOf($browser, $name, $url, $query, $theme));
		Auth::logout();
		$browser->logoff();
		$failures = array_merge($failures, $this->failuresOf($browser, 'sign in', '/users/login', null, $theme));
		$this->assertSame([], $failures, 'Buttons below the contrast of WCAG AA in the ' . $theme . ' theme');
	}

	/** @return list<string> */
	private function failuresOf(Browser $browser, string $name, string $url, ?string $query, string $theme): array
	{
		$browser->setCookie('query', $query ?? 'clear');
		$browser->get($url);
		$browser->driver->executeScript('document.documentElement.dataset.theme = arguments[0];'
			. 'const style = document.createElement("style"); style.textContent = "*,*::before,*::after{transition:none!important;animation:none!important}"; document.head.append(style);', [$theme]);
		$count = (int) $browser->driver->executeScript(self::PREPARE, [self::BUTTONS]);
		$failures = [];
		for ($i = 0; $i < $count; $i++)
		{
			$element = $browser->driver->findElement(Facebook\WebDriver\WebDriverBy::cssSelector('[data-contrast="' . $i . '"]'));
			$browser->driver->executeScript('if (document.activeElement) document.activeElement.blur();');
			$rest = $browser->driver->executeScript(self::MEASURE, [$i]);
			$browser->hover($element);
			$hover = $browser->driver->executeScript(self::MEASURE, [$i]);
			foreach (['at rest' => $rest, 'under the pointer' => $hover] as $state => $result)
				if ($result !== null && $result['ratio'] < $result['threshold'])
					$failures[] = $name . ': "' . $result['label'] . '" ' . $state . ', ' . $result['ratio'] . ':1 (needs ' . $result['threshold'] . ')';
		}
		$this->assertGreaterThan(0, $count, 'buttons on ' . $name);
		return array_values(array_unique($failures));
	}
}
