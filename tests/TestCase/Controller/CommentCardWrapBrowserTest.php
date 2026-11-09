<?php

use Facebook\WebDriver\WebDriverDimension;

//A comment with a long run of coordinates wraps inside its card, so the card stays in the panel with its date and buttons.
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

	private function assertTheCardsFit(int $width, int $height): void
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
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
