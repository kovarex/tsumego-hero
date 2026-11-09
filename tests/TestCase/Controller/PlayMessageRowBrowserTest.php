<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverDimension;

//Every message of a problem is one of the row of the messages above the board, on the page with no background of its
//own whatever it says: the goal, Correct!, Incorrect! and the comment of a move ("Correct, but what about this answer?") are at the left
//in the same bold size, with Reset and Next at the right, on a computer and on a phone; only their colour differs
#[RetryOnBrowserFailure]
class PlayMessageRowBrowserTest extends IntegrationTestCase
{
	//Black (1,1) is correct, with a comment; black (2,1) is correct too
	private const SGF = '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb](;B[aa]C[+Correct, but what about this answer?])(;B[ba]C[+]))';

	//The style of the comment and of Correct!, the row without a background and where the comment is in it
	private const LAYOUT = <<<'JS'
		const comment = document.getElementById('theComment');
		const status = document.querySelector('#status h2');
		const row = document.querySelector('.play-message');
		const reset = document.getElementById('besogo-reset-button');
		const style = element => {
			const computed = getComputedStyle(element);
			return [computed.fontSize, computed.fontWeight, computed.fontFamily, computed.textAlign];
		};
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
			noBackground: getComputedStyle(row).backgroundColor === 'rgba(0, 0, 0, 0)' && getComputedStyle(row).backgroundImage === 'none',
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
				'noBackground' => true, 'afterCorrect' => true, 'beforeReset' => true], $browser->driver->executeScript(self::LAYOUT));
			$browser->assertNoJsErrors();
		}
		finally
		{
			$browser->driver->manage()->window()->setSize($original);
		}
	}

	//A comment with no Correct! or Incorrect! yet, the comment of the problem shown when it opens, has no background
	//either
	public function testACommentAloneHasNoBackground(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]SZ[19]C[Go Seigen 4p vs Kubomatsu 6p]AB[cc];B[aa];W[ab];B[ba]C[+])']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$this->assertSame([true, 'left'], $browser->driver->executeScript(<<<'JS'
			return [getComputedStyle(document.querySelector('.play-message')).backgroundColor === 'rgba(0, 0, 0, 0)', getComputedStyle(document.getElementById('theComment')).textAlign];
			JS));
	}

	//What the row shows: the style of its message, that it has no background, its height, where Reset is, and the colour
	//of the message
	//against the colour it should have
	private const STATE = <<<'JS'
		const message = document.querySelector('#status h2') ?? document.querySelector('#titleDescription');
		const row = document.querySelector('.play-message');
		const resolve = value => {
			const probe = document.createElement('div');
			probe.style.color = value;
			document.body.append(probe);
			const color = getComputedStyle(probe).color;
			probe.remove();
			return color;
		};
		const computed = getComputedStyle(message);
		const reset = document.querySelector('#besogo-reset-button, #besogo-reset-button-inactive').getBoundingClientRect();
		return {
			text: message.textContent.trim(),
			font: [computed.fontSize, computed.fontWeight, computed.fontFamily, computed.textAlign].join('|'),
			noBackground: getComputedStyle(row).backgroundColor === 'rgba(0, 0, 0, 0)',
			height: Math.round(row.getBoundingClientRect().height),
			reset: Math.round(reset.left) + ',' + Math.round(reset.top),
			color: computed.color === resolve(arguments[0]),
		};
		JS;

	//The goal, Incorrect! and Correct! are the same row without a background, in the same bold size at the left, with Reset in the same
	//place; only the colour of the text differs
	public function testTheGoalIncorrectAndCorrectAreTheSameRow(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => ['data' => '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb];B[ba]C[+])', 'first_move_color' => 'B'], 'description' => 'Black to capture']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		$goal = $browser->driver->executeScript(self::STATE, ['var(--text-color)']);

		$browser->clickBoard(5, 5);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$incorrect = $browser->driver->executeScript(self::STATE, ['var(--play-message-incorrect)']);

		$browser->clickId('besogo-reset-button');
		$browser->waitUntilJs('document.getElementById("status").textContent === ""');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Correct!")');
		$correct = $browser->driver->executeScript(self::STATE, ['var(--play-message-correct)']);

		$this->assertSame(['to capture', 'Incorrect!', 'Correct!'], [$goal['text'], $incorrect['text'], $correct['text']]);
		foreach (['font', 'height', 'reset'] as $key)
			$this->assertSame([$goal[$key], $goal[$key]], [$incorrect[$key], $correct[$key]], "the same $key");
		$this->assertSame([true, true, true], [$goal['noBackground'], $incorrect['noBackground'], $correct['noBackground']], 'no background of the row');
		$this->assertSame([true, true, true], [$goal['color'], $incorrect['color'], $correct['color']], 'the colour of each');
	}

	//Black (3,1) is answered by white at (3,2) or (1,2), both of which black has to answer: (3,3) and (1,1)
	private const TWO_ANSWERS = '(;GM[1]FF[4]SZ[19]AW[bb]AB[dd];B[ca](;W[cb];B[cc]C[+])(;W[ab];B[aa]C[+]))';

	//The answer to the white reply on the board: to the first of the SGF (3,2) at (3,3), to the second (1,2) at (1,1). The
	//board may show the problem turned, so the reply is told by its place in the tree, not by its coordinates.
	private static function answerTo(Browser $browser): array
	{
		$index = $browser->driver->executeScript('const node = besogo.editor.getCurrent(); return node.parent.children.indexOf(node);');
		return $index === 0 ? [3, 3] : [1, 1];
	}

	private static function verdict(Browser $browser): array
	{
		return $browser->driver->executeScript(<<<'JS'
			const next = document.getElementById('besogo-next-button');
			return {
				status: document.getElementById('status').textContent,
				comment: document.getElementById('theComment').textContent,
				solved: document.querySelector('.play-message').classList.contains('play-message--solved'),
				greenNext: next !== null && getComputedStyle(next).backgroundColor === (() => { const probe = document.createElement('div'); probe.style.background = 'var(--color-green-dark)'; document.body.append(probe); const color = getComputedStyle(probe).backgroundColor; probe.remove(); return color; })(),
				greenGlow: [...document.querySelectorAll('.besogo-board')].some(board => getComputedStyle(board).boxShadow.includes('67, 255, 40')),
			};
			JS);
	}

	//A problem with two answers to give is not solved by the first: the row says only "Correct, but what about this
	//answer?" in the colour of the text, without Correct!, and Next isn't green. The second answer solves it.
	public function testCorrectIsSaidOnlyWhenEveryAnswerIsGiven(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => ['data' => self::TWO_ANSWERS, 'first_move_color' => 'B']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();

		$browser->clickBoard(3, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(...self::answerTo($browser));
		$browser->waitUntilJs('document.getElementById("theComment").textContent.includes("what about this answer")');
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber == 2');
		$first = self::verdict($browser);
		$this->assertSame('', $first['status'], 'no Correct! yet');
		$this->assertSame('Correct, but what about this answer?', trim($first['comment']));
		$this->assertFalse($first['solved']);
		$this->assertFalse($first['greenNext'], 'Next is not green yet');
		$this->assertFalse($first['greenGlow'], 'the board does not glow green yet');
		$this->assertSame(true, $browser->driver->executeScript('const probe = document.createElement("div"); probe.style.color = "var(--text-color)"; document.body.append(probe); const color = getComputedStyle(probe).color; probe.remove(); return getComputedStyle(document.getElementById("theComment")).color === color;'), 'the comment in the colour of the text');

		$browser->clickBoard(...self::answerTo($browser));
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Correct!")');
		$second = self::verdict($browser);
		$this->assertTrue($second['solved']);
		$this->assertTrue($second['greenNext'], 'Next is green');
		$this->assertTrue($second['greenGlow'], 'the board glows green');
	}

	//Correct! and Incorrect! reach the 4.5:1 of WCAG AA on the background the row shows through, in both themes
	public function testTheColoursOfTheMessagesAreReadableOnThePage(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => ['data' => self::SGF, 'first_move_color' => 'B']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->waitForBoard();
		foreach (['light', 'dark'] as $theme)
		{
			$ratios = $browser->driver->executeScript(<<<'JS'
				document.documentElement.dataset.theme = arguments[0];
				const rgb = color => color.match(/[\d.]+/g).slice(0, 3).map(Number);
				const luminance = color => {
					const [r, g, b] = rgb(color).map(value => value / 255).map(value => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
					return 0.2126 * r + 0.7152 * g + 0.0722 * b;
				};
				// the first background behind the row that isn't transparent
				let element = document.querySelector('.play-message');
				let background = getComputedStyle(element).backgroundColor;
				while (element && (background === 'rgba(0, 0, 0, 0)' || background === 'transparent'))
				{
					element = element.parentElement;
					background = element ? getComputedStyle(element).backgroundColor : 'rgb(255, 255, 255)';
				}
				const ratio = token => {
					const probe = document.createElement('div');
					probe.style.color = token;
					document.body.append(probe);
					const color = getComputedStyle(probe).color;
					probe.remove();
					const [a, b] = [luminance(color), luminance(background)].sort((x, y) => y - x);
					return Math.round((a + 0.05) / (b + 0.05) * 100) / 100;
				};
				return {background, correct: ratio('var(--play-message-correct)'), incorrect: ratio('var(--play-message-incorrect)'), text: ratio('var(--text-color)')};
				JS, [$theme]);
			foreach (['correct', 'incorrect', 'text'] as $message)
				$this->assertGreaterThanOrEqual(4.5, $ratios[$message], "$message in the $theme theme, on {$ratios['background']}");
		}
	}
}
