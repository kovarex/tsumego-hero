<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverDimension;

//Every message of a problem is one of the row of the messages above the board, on the page with no background of its
//own whatever it says: the goal, Correct!, Incorrect! and the comment of a move ("Correct, but what about this answer?",
//after Correct! or Incorrect! on the same line) are at the left in the same bold size, with Reset and Next at the right,
//on a computer and on a phone; only their colour differs. A short comment leaves the row's height, so the board doesn't
//move; a comment too long for the line wraps under, at the row's left edge, and only then the row grows.
#[RetryOnBrowserFailure]
class PlayMessageRowBrowserTest extends IntegrationTestCase
{
	//Black (1,1) is correct, with a comment; black (2,1) is correct too
	private const SGF = '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb](;B[aa]C[+Correct, but what about this answer?])(;B[ba]C[+]))';

	//The comments of the moves of commented(): a short one of a correct move, a long one of a correct move, which wraps,
	//and one of a wrong move; without the names of the colours, which the board may swap
	private const COMMENTS = [
		'correct' => 'Correct, but what about this answer?',
		'long' => 'A is the best completion before ko. B would also live, but it leaves the opponent an extra threat, and C fails to the throw-in at D, which is the main point of this problem.',
		'incorrect' => 'A looks natural, but the answer at B leaves the group short of liberties.'];

	//Black (1,1) with the comment of the case, correct or not; black (2,1) is correct
	private static function commented(string $case): string
	{
		return '(;GM[1]FF[4]SZ[19]AB[dd]AW[bb](;B[aa]C[' . ($case === 'incorrect' ? '' : '+') . self::COMMENTS[$case] . '])(;B[ba]C[+]))';
	}

	//The style of the comment and of the message above it, the row without a background, where the comment is in it and
	//where the board is
	private const LAYOUT = <<<'JS'
		const comment = document.getElementById('theComment');
		const status = document.querySelector('#status h2');
		const row = document.querySelector('.play-message');
		const reset = document.getElementById('besogo-reset-button');
		const board = document.querySelector('.besogo-board');
		const style = element => {
			const computed = getComputedStyle(element);
			return [computed.fontSize, computed.fontWeight, computed.fontFamily, computed.textAlign];
		};
		const text = document.createElement('div');
		text.style.color = 'var(--text-color)';
		document.body.append(text);
		const textColor = getComputedStyle(text).color;
		text.remove();
		const box = element => element.getBoundingClientRect();
		// the lines of the comment, an inline text after Correct!
		const lines = [...comment.getClientRects()];
		const first = lines[0], last = lines[lines.length - 1];
		return {
			inTheRow: row.contains(comment),
			sameStyleAsCorrect: JSON.stringify(style(comment)) === JSON.stringify(style(status)),
			left: getComputedStyle(comment).textAlign,
			colorOfTheText: getComputedStyle(comment).color === textColor,
			noBackground: getComputedStyle(row).backgroundColor === 'rgba(0, 0, 0, 0)' && getComputedStyle(row).backgroundImage === 'none',
			onTheLineOfCorrect: Math.abs(first.top - box(status).top) < 2 && Math.abs(first.bottom - box(status).bottom) < 2,
			afterCorrect: first.left >= box(status).right,
			// a wrapped comment goes on at the left edge of the messages, where Correct! starts
			wrapsToTheLeftEdge: lines.length === 1 || Math.abs(last.left - box(status).left) < 1,
			lines: lines.length,
			insideTheRow: box(comment).top >= box(row).top - 1 && box(comment).bottom <= box(row).bottom + 1,
			beforeReset: box(comment).right <= box(reset).left,
			boardUnderTheRow: box(row).bottom <= box(board).top + 1,
			// how much taller the row got than with the goal only, before the move, and how far the board moved down
			growth: box(row).height - window.rowBefore.height,
			boardShift: box(board).top - window.rowBefore.boardTop,
		};
		JS;

	private function playTheCommentedMove(int $width, int $height, string $theme, string $case = 'correct'): Browser
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => ['set_order' => 1, 'sgf' => ['data' => self::commented($case), 'first_move_color' => 'B']]]);
		$browser = Browser::instance();
		$browser->driver->manage()->window()->setSize(new WebDriverDimension($width, $height));
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->driver->executeScript('document.documentElement.dataset.theme = arguments[0];', [$theme]);
		$browser->waitForBoard();
		$browser->driver->executeScript('window.rowBefore = {height: document.querySelector(".play-message").getBoundingClientRect().height, boardTop: document.querySelector(".besogo-board").getBoundingClientRect().top};');
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("' . ($case === 'incorrect' ? 'Incorrect' : 'Correct!') . '") && document.getElementById("theComment").textContent.length > 0');
		return $browser;
	}

	public static function screens(): array
	{
		$screens = [];
		foreach (['computer' => [1280, 900], 'phone' => [390, 844]] as $device => [$width, $height])
			foreach (['light', 'dark'] as $theme)
				foreach (array_keys(self::COMMENTS) as $case)
					$screens["$device, $theme, $case"] = [$width, $height, $theme, $case];
		return $screens;
	}

	#[PHPUnit\Framework\Attributes\DataProvider('screens')]
	public function testTheCommentOfAMoveIsAMessageOfTheRow(int $width, int $height, string $theme, string $case): void
	{
		$browser = Browser::instance();
		$original = $browser->driver->manage()->window()->getSize();
		try
		{
			$browser = $this->playTheCommentedMove($width, $height, $theme, $case);

			$this->assertSame(self::COMMENTS[$case], $browser->find('#theComment')->getText());
			$layout = $browser->driver->executeScript(self::LAYOUT);
			$lines = $layout['lines'];
			$growth = $layout['growth'];
			$boardShift = $layout['boardShift'];
			unset($layout['lines'], $layout['growth'], $layout['boardShift']);
			$long = $case === 'long';
			$this->assertEquals(['inTheRow' => true, 'sameStyleAsCorrect' => true, 'left' => 'left', 'colorOfTheText' => true,
				'noBackground' => true, 'onTheLineOfCorrect' => true, 'afterCorrect' => true, 'wrapsToTheLeftEdge' => true,
				'insideTheRow' => true, 'beforeReset' => true, 'boardUnderTheRow' => true], $layout);
			// the board moves down exactly as much as the row grows: nothing covers it, nothing else moves it
			$this->assertEqualsWithDelta($growth, $boardShift, 1, 'the board follows the row');
			if ($long)
			{
				$this->assertGreaterThan(1, $lines, 'the long comment wraps');
				$this->assertGreaterThan(1, $growth, 'the row grows for the long comment');
			}
			// a short comment keeps the height of the row, so the board doesn't move; whether a middling one fits on a
			// phone depends on the font of the browser
			if ($case === 'correct')
				$this->assertEqualsWithDelta(0, $growth, 1, 'a short comment keeps the height of the row');
			if ($width > 1000 && !$long)
				$this->assertSame(1, $lines, 'on a computer, the comment fits the line of Correct!');
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
