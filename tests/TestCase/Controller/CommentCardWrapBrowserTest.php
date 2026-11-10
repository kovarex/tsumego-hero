<?php

use Facebook\WebDriver\WebDriverDimension;

//The panel of the play page with its comments open: a comment with a long run of coordinates wraps inside its card, so
//the card stays in the panel with its date and buttons; everything in the panel shares its left and right edge; and on a
//wide screen the panel grows into the margins of the page while the board column keeps its width.
#[RetryOnBrowserFailure]
class CommentCardWrapBrowserTest extends IntegrationTestCase
{
	private const LONG_COMMENT = 'I\'ve captured the bottom left group but it\'s not accepting it. With W N1, B K1, W M1, B Q1, W N1, B L1, W N1, B R6, W M1, B N2, W O1, B P2, W Q2';

	public function testALongRunOfCoordinatesWrapsInsideTheCardOnAComputer(): void
	{
		$this->assertTheCardsFit(1280, 900);
	}

	public function testALongRunOfCoordinatesWrapsInsideTheCardOnAPhone(): void
	{
		$this->assertTheCardsFit(390, 844);
	}

	public function testThePanelGrowsOnAWideScreenAndTheBoardColumnKeepsItsWidth(): void
	{
		$columns = [];
		foreach ([1280, 1920] as $width)
			$columns[$width] = $this->assertTheCardsFit($width, 1000);
		$this->assertSame(430, $columns[1280]['panel']);
		$this->assertSame(560, $columns[1920]['panel'], 'the most the panel grows');
		$this->assertEqualsWithDelta($columns[1280]['main'], $columns[1920]['main'], 1, 'the board column keeps its width');
	}

	/** @return array{panel: int, main: int} The widths of the panel and of the board column */
	private function assertTheCardsFit(int $width, int $height): array
	{
		// an admin sees the Delete and Make Issue buttons of a comment of another player
		$context = new ContextPreparator(['user' => ['admin' => true], 'other-users' => [['name' => 'commenter']], 'tsumego' => 1]);
		$comments = $this->fetchTable('TsumegoComment');
		foreach ([self::LONG_COMMENT, 'Nice problem, C3 is the key.'] as $message)
			$comments->saveOrFail($comments->newEntity([
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => ContextPreparator::getUserIdFromName('commenter'),
				'message' => $message,
			]));

		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$window->setSize(new WebDriverDimension($width, $height));
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$browser->expandComments();
			$browser->find('.tsumego-comment .go-coord');

			$cards = $browser->driver->executeScript(<<<'JS'
				return [...document.querySelectorAll('.tsumego-comment')].map(card => {
					const box = card.getBoundingClientRect();
					const panel = card.closest('.tsumego-comments__content').getBoundingClientRect();
					const meta = card.querySelector('.sandboxTable2time').getBoundingClientRect();
					const buttons = [...card.querySelectorAll('.sandboxTable2time button')].map(button => button.getBoundingClientRect().right);
					return {
						overflow: card.scrollWidth - card.clientWidth,
						metaOutside: Math.max(0, Math.ceil(Math.max(meta.right, ...buttons) - box.right)),
						cardOutside: Math.max(0, Math.ceil(box.right - panel.right)),
						lines: card.querySelector('.comment__text').getClientRects().length,
					};
				});
				JS);
			$this->assertCount(2, $cards);
			foreach ($cards as $index => $card)
			{
				$this->assertLessThanOrEqual(0, $card['overflow'], 'card ' . $index . ' at ' . $width . ' px: ' . json_encode($card));
				$this->assertSame(0, $card['metaOutside'], 'the date and the buttons of card ' . $index . ' stay in it at ' . $width . ' px');
				$this->assertSame(0, $card['cardOutside'], 'card ' . $index . ' stays in the panel at ' . $width . ' px');
			}
			$this->assertGreaterThan(1, $cards[0]['lines'], 'the long comment wraps onto more lines');
			// a coordinate itself never breaks
			$this->assertSame(0, $browser->driver->executeScript('return [...document.querySelectorAll(".go-coord")].filter(coord => coord.getClientRects().length > 1).length;'));

			// one left edge for the hearts, the hero powers, the actions, the tabs, the filter, the comments and the
			// form, and one right edge for the comments and the form: the panel's padding
			$edges = $browser->driver->executeScript(<<<'JS'
				const shown = selector => [...document.querySelectorAll(selector)].find(element => element.getBoundingClientRect().width > 0);
				const panel = document.querySelector('.play-screen__panel');
				const style = getComputedStyle(panel);
				const box = panel.getBoundingClientRect();
				const parts = {
					hearts: shown('#health .play-player__hearts img'),
					powers: shown('#heroPowersTray > *'),
					actions: shown('.play-actions > *'),
					tabs: shown('.tsumego-comments__tab'),
					filter: shown('.tsumego-comments__anchor-filter > *'),
					comment: shown('.tsumego-comment .sandboxComment'),
					form: shown('.tsumego-comments__form'),
				};
				const result = {
					left: box.left + parseFloat(style.paddingLeft),
					right: box.right - parseFloat(style.paddingRight),
					panel: Math.round(box.width),
					main: Math.round(document.querySelector('.play-screen__main').getBoundingClientRect().width),
					lefts: {}, rights: {},
				};
				for (const [name, element] of Object.entries(parts))
					if (element)
						result.lefts[name] = element.getBoundingClientRect().left;
				for (const name of ['comment', 'form'])
					if (parts[name])
						result.rights[name] = parts[name].getBoundingClientRect().right;
				return result;
				JS);
			// the hero powers show beside the hearts on a wide screen, and behind the ⚡ button on a phone
			// (the browser hands the object back with its keys sorted)
			$parts = array_values(array_diff(array_keys($edges['lefts']), ['powers']));
			sort($parts);
			$this->assertSame(['actions', 'comment', 'filter', 'form', 'hearts', 'tabs'], $parts, 'the parts of the panel at ' . $width . ' px');
			foreach ($edges['lefts'] as $name => $left)
				$this->assertEqualsWithDelta($edges['left'], $left, 1, 'the left edge of the ' . $name . ' at ' . $width . ' px');
			foreach ($edges['rights'] as $name => $right)
				$this->assertEqualsWithDelta($edges['right'], $right, 1, 'the right edge of the ' . $name . ' at ' . $width . ' px');
			$browser->assertNoJsErrors();
			return ['panel' => $edges['panel'], 'main' => $edges['main']];
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
