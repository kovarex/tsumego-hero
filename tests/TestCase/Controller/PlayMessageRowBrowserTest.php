<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverDimension;

//Every message of a problem is one of the row of the messages above the board: the comment of a move ("Correct, but
//what about this answer?") looks like Correct! next to it, at the left in the same bold size, on the same grey, with
//Reset and Next at the right, on a computer and on a phone
#[RetryOnBrowserFailure]
class PlayMessageRowBrowserTest extends IntegrationTestCase
{
	//Black (1,1) is correct, with a comment; black (2,1) is correct too
	private const SGF = '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb](;B[aa]C[+Correct, but what about this answer?])(;B[ba]C[+]))';

	//The style of the comment and of Correct!, the grey of the row and where the comment is in it
	private const LAYOUT = <<<'JS'
		const comment = document.getElementById('theComment');
		const status = document.querySelector('#status h2');
		const row = document.querySelector('.play-message');
		const reset = document.getElementById('besogo-reset-button');
		const style = element => {
			const computed = getComputedStyle(element);
			return [computed.fontSize, computed.fontWeight, computed.fontFamily, computed.textAlign];
		};
		const probe = document.createElement('div');
		probe.style.background = 'var(--color-surface-hover)';
		document.body.append(probe);
		const grey = getComputedStyle(probe).backgroundColor;
		probe.remove();
		const text = document.createElement('div');
		text.style.color = 'var(--text-color)';
		document.body.append(text);
		const textColor = getComputedStyle(text).color;
		text.remove();
		return {
			inTheRow: comment.parentElement === row,
			sameStyleAsCorrect: JSON.stringify(style(comment)) === JSON.stringify(style(status)),
			left: getComputedStyle(comment).textAlign,
			colorOfTheText: getComputedStyle(comment).color === textColor,
			greyRow: getComputedStyle(row).backgroundColor === grey,
			afterCorrect: comment.getBoundingClientRect().left > status.getBoundingClientRect().right,
			beforeReset: comment.getBoundingClientRect().right <= reset.getBoundingClientRect().left,
		};
		JS;

	private function playTheCommentedMove(int $width, int $height, string $theme): Browser
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => ['data' => self::SGF, 'first_move_color' => 'B']]]);
		$browser = Browser::instance();
		$browser->driver->manage()->window()->setSize(new WebDriverDimension($width, $height));
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->driver->executeScript('document.documentElement.dataset.theme = arguments[0];', [$theme]);
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Correct!") && document.getElementById("theComment").textContent.length > 0');
		return $browser;
	}

	public static function screens(): array
	{
		return ['computer, light' => [1280, 900, 'light'], 'computer, dark' => [1280, 900, 'dark'],
			'phone, light' => [390, 844, 'light'], 'phone, dark' => [390, 844, 'dark']];
	}

	#[PHPUnit\Framework\Attributes\DataProvider('screens')]
	public function testTheCommentOfACorrectMoveIsAMessageOfTheRow(int $width, int $height, string $theme): void
	{
		$browser = Browser::instance();
		$original = $browser->driver->manage()->window()->getSize();
		try
		{
			$browser = $this->playTheCommentedMove($width, $height, $theme);

			$this->assertSame('Correct, but what about this answer?', $browser->find('#theComment')->getText());
			$this->assertEquals(['inTheRow' => true, 'sameStyleAsCorrect' => true, 'left' => 'left', 'colorOfTheText' => true,
				'greyRow' => true, 'afterCorrect' => true, 'beforeReset' => true], $browser->driver->executeScript(self::LAYOUT));
			$browser->assertNoJsErrors();
		}
		finally
		{
			$browser->driver->manage()->window()->setSize($original);
		}
	}

	//A comment with no Correct! or Incorrect! yet, the comment of the problem shown when it opens, has the grey of the
	//row too
	public function testACommentAloneHasTheGreyOfTheRow(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]C[Go Seigen 4p vs Kubomatsu 6p]AB[cc];B[aa];W[ab];B[ba]C[+])']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$this->assertSame([true, 'left'], $browser->driver->executeScript(<<<'JS'
			const probe = document.createElement('div');
			probe.style.background = 'var(--color-surface-hover)';
			document.body.append(probe);
			const grey = getComputedStyle(probe).backgroundColor;
			probe.remove();
			return [getComputedStyle(document.querySelector('.play-message')).backgroundColor === grey, getComputedStyle(document.getElementById('theComment')).textAlign];
			JS));
	}
}
