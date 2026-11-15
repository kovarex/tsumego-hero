<?php

use App\Utility\Auth;
use App\Utility\Constants;

//The small texts the style audit found hard to read reach WCAG AA in the light and the dark theme: 4.5:1, or 3:1 for a
//large or bold text of 18.66px and more. The text is measured against the fills really behind it, a transparent fill
//resolved through its parents and a gradient by each of its colours.
#[RetryOnBrowserFailure]
class TextContrastBrowserTest extends IntegrationTestCase
{
	//The worst contrast of each shown element matching the selector, with its text
	private const string MEASURE = <<<'JS'
		const parse = c => { let m = c.match(/color\(srgb ([^)]+)\)/); if (m) { const p = m[1].split(/[ /]+/).filter(Boolean).map(Number); return [p[0] * 255, p[1] * 255, p[2] * 255, p.length > 3 ? p[3] : 1]; } m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
		const over = (top, bottom) => { const a = top[3] + bottom[3] * (1 - top[3]); return a === 0 ? [0, 0, 0, 0] : [0, 1, 2].map(i => (top[i] * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / a).concat([a]); };
		const canvas = getComputedStyle(document.documentElement).colorScheme.includes('dark') ? [0, 0, 0, 1] : [255, 255, 255, 1];
		//The colours behind an element: one per stop of the nearest gradient, or the solid fills blended
		const fillsOf = el => {
			const layers = [];
			for (let m = el; m && m.nodeType === 1; m = m.parentElement)
			{
				const style = getComputedStyle(m);
				if (/gradient/.test(style.backgroundImage))
				{
					const stops = (style.backgroundImage.match(/(rgba?|color)\([^)]*\)/g) || []).map(parse).filter(Boolean);
					return stops.map(stop => layers.reduceRight((b, l) => over(l, b), over(stop, canvas)));
				}
				const fill = parse(style.backgroundColor);
				if (fill && fill[3] > 0)
					layers.push(fill);
			}
			return [layers.reduceRight((b, l) => over(l, b), canvas)];
		};
		const luminance = c => { const v = c.slice(0, 3).map(x => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
		return [...document.querySelectorAll(arguments[0])].filter(el => el.checkVisibility({visibilityProperty: true}) && el.innerText.trim() !== '').map(el => {
			const style = getComputedStyle(el), large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && parseInt(style.fontWeight, 10) >= 700);
			const ratio = Math.min(...fillsOf(el).map(fill => { const a = luminance(over(parse(style.color), fill)), b = luminance(fill); return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05); }));
			return {text: el.innerText.trim().replace(/\s+/g, ' ').slice(0, 30), ratio: Math.round(ratio * 100) / 100, threshold: large ? 3 : 4.5};
		});
		JS;

	public static function themes(): array
	{
		return ['light' => ['light'], 'dark' => ['dark']];
	}

	#[PHPUnit\Framework\Attributes\DataProvider('themes')]
	public function testTheSmallTextsReadInBothThemes(string $theme): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'admin' => true, 'xp' => 20],
			'other-users' => [['name' => 'first', 'xp' => 300, 'level' => 5], ['name' => 'second', 'xp' => 200, 'level' => 4], ['name' => 'third', 'xp' => 100, 'level' => 3]],
			'tsumegos' => [['set_order' => 1, 'sets' => [['name' => 'Basics', 'num' => 1]]]]]);
		$comments = $this->fetchTable('TsumegoComment');
		$comments->saveOrFail($comments->newEntity(['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => ContextPreparator::getUserIdFromName('first'), 'message' => 'Nice problem.']));
		$browser = Browser::instance();
		$pages = [
			'profile' => ['/users/view/' . $context->user['id'], '.progress__label'],
			'what\'s new' => ['/changelog', '.changelog__category', 'labels'],
			'highscore' => ['/users/highscore', '.highscore-table__total, .data-table__row--medal-bronze td'],
			'collection' => ['/sets/view/' . $context->tsumegos[0]['set-connections'][0]['set_id'], '.set-view__completed, .set-view__accuracy, .set-view__time'],
			'achievement' => ['/achievements/view/1', '.rarity'],
			'play' => ['/' . $context->tsumegos[0]['set-connections'][0]['id'], '.tsumego-comment__date', 'comments']];
		$failures = [];
		foreach ($pages as $name => $page)
			$failures = array_merge($failures, $this->failuresOf($browser, $name, $page[0], $page[1], $theme, $page[2] ?? null));
		Auth::logout();
		$browser->logoff();
		$failures = array_merge($failures, $this->failuresOf($browser, 'sign in', '/users/login', '.signin .hint a', $theme));
		$this->assertSame([], $failures, 'Texts below the contrast of WCAG AA in the ' . $theme . ' theme');
	}

	/** @return list<string> */
	private function failuresOf(Browser $browser, string $name, string $url, string $selector, string $theme, ?string $prepare = null): array
	{
		$browser->get($url);
		if ($prepare === 'comments')
			$browser->expandComments();
		// the entries of What's new are made at deploy, so the test shows one label of each kind in the page's list
		if ($prepare === 'labels')
		{
			$browser->find('[data-changelog-root] .changelog, [data-changelog-root] .changelog__empty');
			$browser->driver->executeScript(<<<'JS'
				document.querySelector('[data-changelog-root]').innerHTML = '<div class="changelog"><section><ul class="changelog__list">'
					+ ['Added', 'Fixed', 'Changed', 'Performance', 'Removed'].map(c => '<li><span class="changelog__category changelog__category--' + c.toLowerCase() + '">' + c + '</span><span class="changelog__text">An entry</span></li>').join('')
					+ '</ul></section></div>';
				JS);
		}
		$browser->driver->executeScript('document.documentElement.dataset.theme = arguments[0];'
			. 'const style = document.createElement("style"); style.textContent = "*,*::before,*::after{transition:none!important;animation:none!important}"; document.head.append(style);', [$theme]);
		// the comments are drawn by a script after the page loads
		$browser->find(trim(explode(',', $selector)[0]));
		$results = $browser->driver->executeScript(self::MEASURE, [$selector]);
		$this->assertNotEmpty($results, $selector . ' on ' . $name);
		$failures = [];
		foreach ($results as $result)
			if ($result['ratio'] < $result['threshold'])
				$failures[] = $name . ': "' . $result['text'] . '" ' . $result['ratio'] . ':1 (needs ' . $result['threshold'] . ')';
		return array_values(array_unique($failures));
	}
}
