<?php

use App\Utility\Util;

//Problems on paper: a player picks problems the way a teacher picks them for a class and prints
//them on a plain page with the boards drawn as SVG.
class PrintControllerTest extends IntegrationTestCase
{
	private ContextPreparator $context;

	private const string CORNER = '(;SZ[19]AB[ab][bb][cb]AW[ac][bc][cc][dc][db](;B[ba]C[-];W[ca])(;B[ca];W[da];B[ea]C[+]))';

	//A player with a private collection of two problems (0, 1), a collection of the site with one
	//(2), and a private collection of another player (3).
	private function prepare(): void
	{
		$sgf = ['data' => self::CORNER, 'correct_moves' => 'ca', 'first_move_color' => 'B'];
		$this->context = new ContextPreparator([
			'user' => ['name' => 'printer'],
			'other-users' => [['name' => 'stranger']],
			'tsumegos' => [
				['sets' => [['name' => 'Homework', 'num' => 1, 'public' => 0, 'user_id' => 'self']], 'description' => 'Black to live', 'sgf' => $sgf],
				['sets' => [['name' => 'Homework', 'num' => 2, 'public' => 0, 'user_id' => 'self']], 'sgf' => $sgf],
				['sets' => [['name' => 'Site basics', 'num' => 7]], 'description' => 'White to <b>kill</b>', 'sgf' => $sgf],
				['sets' => [['name' => 'Secret', 'num' => 1, 'public' => 0, 'user_id' => 'self']], 'sgf' => $sgf],
			],
		]);
		Util::execute('UPDATE `set` SET user_id = ? WHERE id = ?',
			[ContextPreparator::getUserIdFromName('stranger'), (int) $this->context->tsumegos[3]['sets'][0]['id']]);
		// the preparator gives every problem a description
		Util::execute("UPDATE tsumego SET description = '' WHERE id = ?", [$this->context->tsumegos[1]['id']]);
	}

	private function setConnectionId(int $tsumegoIndex): int
	{
		return (int) $this->context->tsumegos[$tsumegoIndex]['set-connections'][0]['id'];
	}

	/** @param list<int> $tsumegoIndexes */
	private function sheetUrl(array $tsumegoIndexes, string $options = ''): string
	{
		return '/print/sheet?problems=' . implode(',', array_map($this->setConnectionId(...), $tsumegoIndexes)) . $options;
	}

	/** @return list<int> The set connections of the problems on the pages of problems, in their order */
	private function printedSetConnectionIds(): array
	{
		$ids = [];
		foreach ($this->getStringDom()->querySelectorAll('.print-page:not(.print-page--answers) .print-problem') as $problem)
			$ids[] = (int) $problem->getAttribute('data-set-connection-id');
		return $ids;
	}

	public function testTheSheetShowsThePickedProblemsInTheirOrder(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([2, 0, 1]));

		$this->assertResponseOk();
		$this->assertSame([$this->setConnectionId(2), $this->setConnectionId(0), $this->setConnectionId(1)], $this->printedSetConnectionIds());
		$dom = $this->getStringDom();
		$this->assertSame(3, $dom->querySelectorAll('.print-problem svg.print-board')->length);
		$this->assertSame(['1.', '2.', '3.'], array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.print-problem__number'))));
		// the description is the goal, without its markup; without one the goal is who plays first
		$this->assertSame(['White to kill', 'Black to live', 'Black to play'], array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.print-problem__goal'))));
		$this->assertSame(['Site basics #7', 'Homework #1', 'Homework #2'], array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.print-problem__origin'))));
	}

	//Without the collection and the goal each problem keeps its number, so the answers still match
	public function testTheCollectionAndTheGoalCanBeLeftOut(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([2, 0], '&answers=1&origin=0&goal=0'));

		$dom = $this->getStringDom();
		$this->assertSame(0, $dom->querySelectorAll('.print-problem__goal')->length);
		$this->assertSame(0, $dom->querySelectorAll('.print-problem__origin')->length);
		$this->assertSame(['1.', '2.', '1.', '2.'], array_map(fn($node) => $node->textContent, iterator_to_array($dom->querySelectorAll('.print-problem__number'))));
		$this->assertSame(2, $dom->querySelectorAll('.print-page:not(.print-page--answers) svg.print-board')->length);

		// either one alone
		$this->get($this->sheetUrl([2, 0], '&goal=0'));
		$this->assertSame(0, $this->getStringDom()->querySelectorAll('.print-problem__goal')->length);
		$this->assertSame(2, $this->getStringDom()->querySelectorAll('.print-problem__origin')->length);
		$this->get($this->sheetUrl([2, 0], '&origin=0'));
		$this->assertSame(2, $this->getStringDom()->querySelectorAll('.print-problem__goal')->length);
		$this->assertSame(0, $this->getStringDom()->querySelectorAll('.print-problem__origin')->length);
	}

	//A plain page for the printer: no header, no menu, the Print button, and the styles for paper
	public function testTheSheetIsAPlainPage(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([0]));

		$this->assertStringNotContainsString('site-nav', $this->view);
		$this->assertStringNotContainsString('app-theme', $this->view);
		$this->assertStringContainsString('@media print', $this->view);
		$this->assertStringContainsString('@page', $this->view);
		$this->assertStringContainsString('onclick="window.print();"', $this->view);
		$this->assertNotNull($this->getStringDom()->querySelector('#print-button'));
	}

	public function testTheBoardsAreDrawnAsSvg(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([0]));

		$svg = $this->getStringDom()->querySelector('.print-problem svg.print-board');
		$this->assertSame('0 0 126 86', $svg->getAttribute('viewBox'));
		$this->assertSame(3, $svg->querySelectorAll('.print-board__stone--black')->length);
		$this->assertSame(5, $svg->querySelectorAll('.print-board__stone--white')->length);
	}

	//Six to a page by default; 4, 9 or 12 when asked, anything else is the default
	public function testTheProblemsAreSplitIntoPages(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([0, 1, 2]));
		$this->assertSame(1, $this->getStringDom()->querySelectorAll('.print-page')->length);
		$this->assertStringContainsString('--columns: 2; --rows: 3;', $this->view);

		$this->get($this->sheetUrl([0, 1, 2], '&perPage=4'));
		$this->assertStringContainsString('--columns: 2; --rows: 2;', $this->view);

		$this->get($this->sheetUrl([0, 1, 2], '&perPage=12'));
		$this->assertStringContainsString('--columns: 3; --rows: 4;', $this->view);

		$this->get($this->sheetUrl([0, 1, 2], '&perPage=5'));
		$this->assertStringContainsString('--columns: 2; --rows: 3;', $this->view);
	}

	public function testTheAnswerKeyIsOffByDefault(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([0, 1]));
		$this->assertNull($this->getStringDom()->querySelector('.print-page--answers'));
		$this->assertNull($this->getStringDom()->querySelector('.print-problem__notes'));

		$this->get($this->sheetUrl([0, 1], '&answers=1&notes=1'));
		$answers = $this->getStringDom()->querySelector('.print-page--answers');
		$this->assertSame('Answers', trim($answers->querySelector('h2')->textContent));
		$this->assertSame(2, $answers->querySelectorAll('svg.print-board')->length);
		// the first correct line, numbered from 1
		$this->assertSame(['1', '2', '3'], array_map(fn($node) => $node->textContent, iterator_to_array($answers->querySelector('svg')->querySelectorAll('text'))));
		$this->assertSame(2, $this->getStringDom()->querySelectorAll('.print-problem__notes')->length);
	}

	public function testAProblemWithoutACorrectMoveSaysSoInTheAnswers(): void
	{
		$this->prepare();
		Util::execute('UPDATE sgf SET correct_moves = NULL WHERE tsumego_id = ?', [$this->context->tsumegos[0]['id']]);

		$this->get($this->sheetUrl([0], '&answers=1'));

		$this->assertSame('No solution recorded.', $this->getStringDom()->querySelector('.print-page--answers .print-problem__no-answer')->textContent);
	}

	//The same rule as the picker of the classes: the player's own collections and the ones of the
	//site, not the private ones of another player
	public function testAPrivateCollectionOfAnotherPlayerIsNotPrinted(): void
	{
		$this->prepare();

		$this->get($this->sheetUrl([3, 0]));

		$this->assertSame([$this->setConnectionId(0)], $this->printedSetConnectionIds());
	}

	public function testAProblemInTheTrashIsNotPrinted(): void
	{
		$this->prepare();
		Util::execute('UPDATE tsumego SET deleted = NOW() WHERE id = ?', [$this->context->tsumegos[1]['id']]);

		$this->get($this->sheetUrl([1, 0, 0]));

		$this->assertSame([$this->setConnectionId(0)], $this->printedSetConnectionIds());
	}

	public function testPrintingNeedsAnAccount(): void
	{
		$this->prepare();
		$this->logout();

		$this->get($this->sheetUrl([0]));
		$this->assertRedirect('/users/login');

		$this->get('/print');
		$this->assertRedirect('/users/login');
	}

	//The print page starts with the problems of its address, such as the ones of an assignment
	public function testThePrintPageStartsWithTheProblemsOfTheAddress(): void
	{
		$this->prepare();

		$this->get('/print?problems=' . $this->setConnectionId(1) . ',' . $this->setConnectionId(3) . ',x');

		$this->assertResponseOk();
		$this->assertSame(1, preg_match('/data-print-selection-root data-props="([^"]+)"/', $this->view, $match));
		$props = json_decode(html_entity_decode($match[1]), true);
		$this->assertSame([$this->setConnectionId(1)], array_column($props['problems'], 'setConnectionId'));
		$this->assertSame([4, 6, 9, 12], $props['problemsPerPageChoices']);
		$this->assertSame(6, $props['defaultProblemsPerPage']);
	}

	//The player picks problems in the popup of the classes, chooses the answer key, and the sheet
	//opens with them
	public function testThePlayerPicksProblemsAndPrintsThem(): void
	{
		$this->prepare();
		$browser = Browser::instance();

		$browser->get('/print');
		$browser->clickId('add-problems');
		$browser->clickCssSelect('.classes__picker-card[data-collection-id="' . $this->context->tsumegos[0]['sets'][0]['id'] . '"]');
		$browser->clickCssSelect('.classes__picker a[data-tsumego-id="' . $this->context->tsumegos[1]['id'] . '"]');
		$browser->clickCssSelect('.classes__picker a[data-tsumego-id="' . $this->context->tsumegos[0]['id'] . '"]');
		$browser->waitUntilCssSelectorExistsWithText('.classes__picker [data-picked-count]', '2 of 2 picked');
		$browser->clickCssSelect('.classes__picker-footer button');
		$browser->waitUntilCssSelectorExistsWithText('#print-problems .classes__count', '2');
		$browser->clickCssSelect('input[name="answers"]');
		$browser->clickCssSelect('input[name="goal"]');
		$browser->find('select[name="per_page"] option[value="4"]')->click();

		$link = $browser->find('#open-print-sheet');
		$this->assertSame('_blank', $link->getAttribute('target'));
		$sheetUrl = $link->getAttribute('href');
		// in the order of their numbers, as an assignment has them
		$this->assertStringEndsWith($this->sheetUrl([0, 1], '&perPage=4&answers=1&goal=0'), $sheetUrl);

		$browser->get(parse_url($sheetUrl, PHP_URL_PATH) . '?' . parse_url($sheetUrl, PHP_URL_QUERY));
		$browser->waitUntilCssSelectorDisplayed('#print-button');
		$this->assertCount(2, $browser->getElements('.print-page:not(.print-page--answers) svg.print-board'));
		$this->assertCount(2, $browser->getElements('.print-page--answers svg.print-board'));
		$this->assertCount(0, $browser->getElements('.site-nav'));
		$this->assertCount(0, $browser->getElements('.print-problem__goal'));
		$this->assertCount(4, $browser->getElements('.print-problem__origin'));
		$browser->assertNoJsErrors();
	}
}
