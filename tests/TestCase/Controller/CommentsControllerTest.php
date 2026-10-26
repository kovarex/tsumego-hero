<?php

use Facebook\WebDriver\WebDriverBy;

/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */

class CommentsControllerTest extends IntegrationTestCase
{
	/** SGF of a solved problem with a three move solution line. */
	private const SOLVED_SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';
	/** SOLVED_SGF in the bottom-right corner: the solution is T1, T2, S1. */
	private const BOTTOM_RIGHT_SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[qq];B[ss];W[sr];B[rs]C[+])';
	/** A problem along the left edge, taller than wide, so the board turns it on its side: the solution is A19, A18, A17. */
	private const TALL_SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[ca][cb][cc][cd][ce][cf][cg][ch][ci][cj];B[aa];W[ab];B[ac]C[+])';

	/** The board as the SGF stores it: the top-left corner and its own colours, so comments show as stored. */
	private const STORED_BOARD = ['board_orientation_setting' => User::BOARD_ORIENTATION_SETTING_ORIGINAL, 'player_color_setting' => User::PLAYER_COLOR_SETTING_ORIGINAL];

	/** Opens a tsumego the admin has already solved, so its comments and the review mode are available. */
	private function openSolvedTsumego(Browser $browser, array $tsumego = [], array $user = []): ContextPreparator
	{
		$context = new ContextPreparator(['user' => ['admin' => true] + $user, 'tsumego' => ['set_order' => 1, 'status' => 'S'] + $tsumego]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		return $context;
	}

	private function enterReviewMode(Browser $browser): void
	{
		$browser->driver->executeScript('besogo.editor.setReviewMode(true); besogo.editor.notifyListeners({treeChange: true, navChange: true, stoneChange: true});');
	}

	/** Turns the board as the orientation buttons do, e.g. ['hFlip' => true]. */
	private function transformBoard(Browser $browser, array $flags): void
	{
		$browser->driver->executeScript('var t = besogo.makeTransformation(); Object.assign(t, arguments[0]); besogo.editor.applyTransformation(t);', [$flags]);
	}

	/** Writes a comment in the comment form and waits until it is saved, returns it as stored. */
	private function submitComment(Browser $browser, string $message, bool $attachPosition = false): array
	{
		$messageField = $browser->find('.tsumego-comments__form textarea');
		$messageField->click();
		$messageField->sendKeys($message);
		if ($attachPosition)
			$browser->findById('attachPositionCheckbox-tsumegoCommentForm')->click();
		$browser->find('.tsumego-comments__form button[type="submit"]')->click();
		$browser->waitUntilJs('document.querySelector(".tsumego-comments__form textarea").value === ""');
		return ClassRegistry::init('TsumegoComment')->find('first', ['order' => 'TsumegoComment.id DESC'])['TsumegoComment'];
	}

	/** Goes to the second move of the solution, the one a comment attaches in these tests. */
	private function goToSecondMove(Browser $browser): void
	{
		$this->enterReviewMode($browser);
		$browser->driver->executeScript('besogo.editor.setCurrent(besogo.editor.getRoot().children[0].children[0]);');
	}

	/** Where the current move is on the board as shown. */
	private function currentMove(Browser $browser): array
	{
		// the browser doesn't keep the key order of the returned object, compare with assertEquals
		return $browser->driver->executeScript('var move = besogo.editor.getCurrent().move; return {x: move.x, y: move.y};');
	}

	/**
	 * Shows a single comment and returns how its coordinates were rendered.
	 *
	 * @return array{colors: array<string, string>, sequences: list<string>, alternatives: list<string>}
	 * colors maps each coordinate to its colour marker ('' when there is none), sequences and
	 * alternatives are the texts of the coordinate groups.
	 */
	private function renderComment(string $message, int $coordCount): array
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF, 'comments' => [['message' => $message]]], self::STORED_BOARD);
		$browser->expandComments();
		$browser->waitUntilJs('document.querySelectorAll(".go-coord").length >= ' . $coordCount);
		return $browser->driver->executeScript('
			var texts = function (selector) { return [].map.call(document.querySelectorAll(selector), function (e) { return e.textContent; }); };
			var colors = {};
			document.querySelectorAll(".go-coord").forEach(function (e) { colors[e.dataset.coord] = e.dataset.color; });
			return {colors: colors, sequences: texts(".go-coord-group--sequence"), alternatives: texts(".go-coord-group--alternative")};');
	}

	public function testAddCommentToSolvedTsumego()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default)

		// The comment form is now directly in the comments section
		$messageField = $browser->find('.tsumego-comments__form textarea');
		$messageField->click();
		$messageField->sendKeys("My first comment");
		$submitButton = $browser->find('.tsumego-comments__form button[type="submit"]');
		$this->assertTrue($submitButton->isDisplayed());
		$this->assertTrue($submitButton->isEnabled());
		$submitButton->click();

		// Wait for React to process the comment submission
		$browser->waitUntilJs('document.querySelector(".tsumego-comments__form textarea").value === ""');

		// Verify comment appears in the comments list
		$browser->get('comments');
		$comment = ClassRegistry::init('TsumegoComment')->find('first');
		$browser->waitUntilCssSelectorExistsWithText('#comment_' . $comment['TsumegoComment']['id'], 'My first comment');

		// verify that deleted comment isn't visible
		$comment['TsumegoComment']['deleted'] = date('Y-m-d H:i:s');
		ClassRegistry::init('TsumegoComment')->save($comment);

		$browser->get('comments');
		$browser->waitUntilCssSelectorDoesntExist('#comment_' . $comment['TsumegoComment']['id']);
	}

	public function testAddCommentWithBoardPositionStoresAnchorWithoutMarker()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF]);
		$browser->expandComments();

		// Navigate the board to a move so a position can be anchored
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 1');
		$this->enterReviewMode($browser);

		$messageField = $browser->find('.tsumego-comments__form textarea');
		$messageField->click();
		$messageField->sendKeys('Check this move');
		$positionToggle = $browser->findById('attachPositionCheckbox-tsumegoCommentForm');
		$positionToggle->click();
		$this->assertTrue($positionToggle->isSelected(), 'Position toggle should be checked after attaching');
		$this->assertSame('Check this move', $messageField->getAttribute('value'), 'Attaching the position must not touch the message');

		$browser->find('.tsumego-comments__form button[type="submit"]')->click();
		$browser->waitUntilJs('document.querySelector(".tsumego-comments__form textarea").value === ""');

		// The position is structured data, the message has no "[current position]" marker
		$comment = ClassRegistry::init('TsumegoComment')->find('first', ['order' => 'TsumegoComment.id DESC']);
		$this->assertNotNull($comment['TsumegoComment']['position']);
		$this->assertSame('Check this move', $comment['TsumegoComment']['message']);

		// The rendered comment shows a "Move N" chip and the review tree node gets a badge
		$this->assertStringContainsString('Move', $browser->find('.tsumego-comment__position-label')->getText());
		$browser->find('.besogo-tree-comment-badge');
	}

	public function testReviewBadgeShowsWithoutOpeningComments()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, [
			'sgf' => self::SOLVED_SGF,
			'comments' => [['message' => 'anchored note', 'position' => '1/1/-1/-1/0/0/1/0/full-board|1/1']]]);
		$this->enterReviewMode($browser);

		// The badge comes from the positions rendered with the page, the comments tab stays closed
		$browser->find('.besogo-tree-comment-badge');
		$this->assertCount(1, $browser->getElements('.besogo-tree-comment-badge'));
	}

	/**
	 * Two anchored comments on two DIFFERENT nodes should produce two tree
	 * badges - not collapse onto a single node (regression for the resolver
	 * walking the whole subtree instead of the actual path).
	 */
	public function testMultipleCommentBadgesOnDifferentNodes()
	{
		$browser = Browser::instance();
		$context = $this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF, 'alternative_response' => 0]);

		// The position strings of move 1 and move 2, as the comment form makes them
		$positions = $browser->driver->executeScript("
			var e = window.besogo.editor;
			var node1 = e.getRoot().children[0];
			return [e.makeCommentPosition(node1), e.makeCommentPosition(node1.children[0])];
		");

		// Seed one anchored comment on each of the two different nodes.
		$commentModel = ClassRegistry::init('TsumegoComment');
		foreach ($positions as $i => $position)
		{
			$commentModel->create();
			$commentModel->save([
				'tsumego_id' => $context->tsumegos[0]['id'],
				'user_id' => $context->user['id'],
				'message' => 'anchor ' . ($i + 1),
				'position' => $position,
			]);
		}

		// Reload so the positions rendered with the page include both anchored comments.
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$this->enterReviewMode($browser);

		$browser->waitUntilJs('document.querySelectorAll(".besogo-tree-comment-badge").length >= 2');
		$this->assertCount(2, $browser->getElements('.besogo-tree-comment-badge'), 'Two anchored comments on different nodes should produce two badges');
	}

	/**
	 * The text is stored in the coordinates and colours of the SGF and shown as the
	 * viewer's board is turned, again whenever it is turned.
	 */
	public function testCommentTextFollowsTheBoardTransformation()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF, 'comments' => [['message' => 'black A19 then w A18']]], self::STORED_BOARD);
		$browser->expandComments();
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'black A19 then w A18');

		$this->transformBoard($browser, ['hFlip' => true]);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'black T19 then w T18');
		$this->transformBoard($browser, ['invertColors' => true]);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'white T19 then b T18');
		$this->assertSame(['T19', 'T18'], $browser->driver->executeScript('return [].map.call(document.querySelectorAll(".go-coord"), function (e) { return e.dataset.coord; });'));
	}

	/** A comment written on a turned board is stored as it refers to the SGF. */
	public function testCommentWrittenOnATurnedBoardIsStoredInSgfCoordinates()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF], self::STORED_BOARD);
		$browser->expandComments();
		// the first move (A19 in the SGF) is shown at T19, played by white
		$this->transformBoard($browser, ['hFlip' => true, 'invertColors' => true]);

		$comment = $this->submitComment($browser, 'white T19 is the key');
		$this->assertSame('black A19 is the key', $comment['message']);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'white T19 is the key');
	}

	public function testAttachedPositionIsStoredInSgfCoordinates()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF], self::STORED_BOARD);
		$browser->expandComments();
		$this->transformBoard($browser, ['hFlip' => true]);
		$this->enterReviewMode($browser);
		$browser->driver->executeScript('besogo.editor.setCurrent(besogo.editor.getRoot().children[0].children[0]);');

		$comment = $this->submitComment($browser, 'Check this move', true);
		// the second move, W[ab], with the first move as its parent and the third as its child
		$this->assertSame('1/2/1/1/2/1/2/1/sgf|1/1+1/2', $comment['position']);
	}

	/** On a problem the board shows as it is stored, the comment and its position are stored as written. */
	public function testCommentOnAProblemShownAsStoredIsStoredAsWritten()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::SOLVED_SGF], self::STORED_BOARD);
		$browser->expandComments();
		$this->goToSecondMove($browser);
		$this->assertEquals(['x' => 1, 'y' => 2], $this->currentMove($browser));

		$comment = $this->submitComment($browser, 'black A19 then w A18', true);
		$this->assertSame('black A19 then w A18', $comment['message']);
		$this->assertSame('1/2/1/1/2/1/2/1/sgf|1/1+1/2', $comment['position']);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'black A19 then w A18');
	}

	/**
	 * A problem stored in another corner is turned to the top-left by the board as it loads,
	 * here also with the colours swapped. The comment is written as the viewer sees it, stored
	 * as it refers to the SGF, and points at the same stones on a board turned another way.
	 */
	public function testCommentOnAProblemTheBoardTurnsIsStoredInSgfCoordinates()
	{
		$browser = Browser::instance();
		$context = $this->openSolvedTsumego($browser, ['sgf' => self::BOTTOM_RIGHT_SGF], self::STORED_BOARD);
		$url = '/' . $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get($url . '?playercolor=white');
		$browser->expandComments();
		// the second move, W[sr] at T2 of the SGF, is shown as black at A18
		$this->goToSecondMove($browser);
		$this->assertEquals(['x' => 1, 'y' => 2], $this->currentMove($browser));

		$comment = $this->submitComment($browser, 'white A19 then b A18', true);
		$this->assertSame('black T1 then w T2', $comment['message']);
		$this->assertSame('19/18/19/19/18/19/2/1/sgf|19/19+19/18', $comment['position']);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'white A19 then b A18');

		// in the colours of the SGF, mirrored into the top-right corner
		$browser->get($url);
		$browser->expandComments();
		$this->transformBoard($browser, ['hFlip' => true]);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'black T19 then w T18');
		$browser->find('.tsumego-comment__position-label')->click();
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber === 2');
		$this->assertEquals(['x' => 19, 'y' => 2], $this->currentMove($browser));
	}

	/** On a board turned as it loads, the coordinates and colours of the comment as shown lead to its moves in the tree, as hovering them does. */
	public function testCommentOnATurnedBoardPointsAtItsMoves()
	{
		$browser = Browser::instance();
		$context = $this->openSolvedTsumego($browser, ['sgf' => self::BOTTOM_RIGHT_SGF, 'comments' => [['message' => 'black T1 then w T2']]], self::STORED_BOARD);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id'] . '?playercolor=white');
		$browser->expandComments();
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'white A19 then b A18');

		$this->assertSame([1, 2], $browser->driver->executeScript('
			var spans = document.querySelectorAll(".go-coord");
			var coords = [].map.call(spans, function (e) { return e.dataset.coord; });
			var colors = [].map.call(spans, function (e) { return e.dataset.color || null; });
			var hover = besogo.editor.resolveSequenceHover(coords, 1, null, colors);
			return hover.found ? hover.nodes.map(function (node) { return node.moveNumber; }) : null;'));
	}

	/** A problem taller than wide is turned on its side by the board, the comment follows the rotation. */
	public function testCommentOnAProblemTheBoardRotatesIsStoredInSgfCoordinates()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => self::TALL_SGF], self::STORED_BOARD);
		$browser->expandComments();
		// the second move, W[ab] at A18 of the SGF, is shown at B1
		$this->goToSecondMove($browser);
		$this->assertEquals(['x' => 2, 'y' => 19], $this->currentMove($browser));

		$comment = $this->submitComment($browser, 'black A1 then w B1', true);
		$this->assertSame('black A19 then w A18', $comment['message']);
		$this->assertSame('1/2/1/1/1/3/2/1/sgf|1/1+1/2', $comment['position']);
		$browser->waitUntilCssSelectorExistsWithText('.comment__text', 'black A1 then w B1');
	}

	public function testPositionButtonShowsTheAnchoredMove()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, [
			'sgf' => self::SOLVED_SGF,
			'comments' => [['message' => 'anchored note', 'position' => '1/2/1/1/2/1/2/1/sgf|1/1+1/2']]], self::STORED_BOARD);
		$browser->expandComments();
		$this->transformBoard($browser, ['vFlip' => true]);

		$browser->find('.tsumego-comment__position-label')->click();
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber === 2');
		// the move of the SGF at A18, shown at A2 on the flipped board (the browser doesn't keep the key order)
		$this->assertEquals(['x' => 1, 'y' => 18, 'localEdit' => false], $browser->driver->executeScript(
			'var current = besogo.editor.getCurrent(); return {x: current.move.x, y: current.move.y, localEdit: current.localEdit};'));
	}

	/** When the tree changed since the comment was written, its moves are played again on the board. */
	public function testPositionButtonPlaysMovesNoLongerInTheTree()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, [
			'sgf' => self::SOLVED_SGF,
			'comments' => [['message' => 'what about this?', 'position' => '2/1/2/2/-1/-1/2/0/sgf|2/2+2/1']]], self::STORED_BOARD);
		$browser->expandComments();

		$browser->find('.tsumego-comment__position-label')->click();
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber === 2');
		// the browser doesn't keep the key order of the returned object
		$this->assertEquals(['x' => 2, 'y' => 1, 'localEdit' => true, 'previous' => ['x' => 2, 'y' => 2]], $browser->driver->executeScript('
			var current = besogo.editor.getCurrent();
			return {x: current.move.x, y: current.move.y, localEdit: current.localEdit, previous: {x: current.parent.move.x, y: current.parent.move.y}};'));
	}

	/**
	 * Coordinate references in comments should be detected case-insensitively
	 * (lowercase "r19" is common), and sequences/alternatives should be grouped.
	 */
	public function testCommentCoordinatesParseLowercaseSequencesAndAlternatives()
	{
		$rendered = $this->renderComment('r19-p19-q19-q18-t19 works. Alternatively F2/G1. w C1, b E2.', 9);

		$this->assertSame(['R19-P19-Q19-Q18-T19', 'C1, b E2'], $rendered['sequences']);
		$this->assertSame(['F2/G1'], $rendered['alternatives']);
		$this->assertSame('w', $rendered['colors']['C1']);
		$this->assertSame('b', $rendered['colors']['E2']);
	}

	public function testCommentCoordinatesParseArrowsAndOr()
	{
		$rendered = $this->renderComment('After T3->S2->Q2. Alternatively C1 or B3.', 5);

		$this->assertSame(['T3->S2->Q2'], $rendered['sequences']);
		$this->assertSame(['C1 or B3'], $rendered['alternatives']);
	}

	/**
	 * A lone coordinate is a one-move sequence: it renders in a sequence group so
	 * hovering it shows the move on the board like any other sequence.
	 */
	public function testCommentSingleCoordinateRendersAsSequenceGroup()
	{
		$rendered = $this->renderComment('White lives with R19.', 1);

		$this->assertSame(['R19'], $rendered['sequences']);
		$this->assertSame([], $rendered['alternatives']);
	}

	/**
	 * A comma followed by the connector word "then" (e.g. "...A16, then A18?")
	 * is a sequence continuation, not a sentence break. The connector keeps its
	 * spaces, so it reads "D17, then A18" rather than "D17, thenA18".
	 */
	public function testCommentCoordinatesKeepCommaThenInSequence()
	{
		$rendered = $this->renderComment('F17,B16,D17, then A18?', 4);

		$this->assertSame(['F17,B16,D17, then A18'], $rendered['sequences']);
	}

	/**
	 * A sequence written with colour markers and short move phrases (e.g.
	 * "B kills with H18") is still one sequence. Compact "w"/"b" markers after a
	 * comma must be recognised as colours, so all five moves form a single group
	 * with the correct black/white labels.
	 */
	public function testCommentCoordinatesGroupColoredMovePhrases()
	{
		$rendered = $this->renderComment('B H19,w K18, b L18, after w J19 B kills with H18!', 5);

		$this->assertSame(['H19,w K18, b L18, after w J19 B kills with H18'], $rendered['sequences']);
		// The browser doesn't keep the key order of the returned object
		$this->assertEquals(['H19' => 'b', 'K18' => 'w', 'L18' => 'b', 'J19' => 'w', 'H18' => 'b'], $rendered['colors']);
	}

	/**
	 * A coordinate label must always map to the same absolute intersection, so
	 * hovering distinct labels like R19 and C19 must highlight different points.
	 * The old "spin" heuristic collapsed them onto one position.
	 */
	public function testCoordHoverMapsLabelsToDistinctIntersections()
	{
		$browser = Browser::instance();
		$this->openSolvedTsumego($browser, ['sgf' => '(;GM[1]FF[4]SZ[19]AB[aa]AP[test:1])']);
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.scaleParameters && besogo.editor');

		$result = $browser->driver->executeScript(
			'var size = besogo.scaleParameters.boardCoordSize;'
			. 'var xyR = besogo.editor.coordLabelToXY("R19");'
			. 'var xyC = besogo.editor.coordLabelToXY("C19");'
			. 'besogo.editor.displayHoverCoord("R19");'
			. 'var g = document.querySelector(".besogo-board svg").querySelector("#nextMoveGroup");'
			. 'function blue(){var c=[].slice.call(g.querySelectorAll("circle")).filter(function(x){return x.getAttribute("fill")==="blue";})[0];return c?[c.getAttribute("cx"),c.getAttribute("cy")]:null;}'
			. 'var r19 = blue();'
			. 'besogo.editor.displayHoverCoord("C19");'
			. 'var c19 = blue();'
			. 'besogo.editor.displayHoverCoord(-1);'
			. 'return {size:size, xyR:xyR, xyC:xyC, r19:r19, c19:c19};'
		);

		$this->assertSame(19, $result['size']);
		$this->assertSame(['x' => 17, 'y' => 1], $result['xyR']);
		$this->assertSame(['x' => 3, 'y' => 1], $result['xyC']);
		$this->assertNotNull($result['r19']);
		$this->assertNotNull($result['c19']);
		$this->assertNotSame($result['r19'], $result['c19'], 'Distinct labels must not collapse onto one intersection');
	}

	public function testDontShowCommentsUntilProblemIsSolved()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'comments' => [['message' => 'spoiler']]]]);
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertFalse($browser->getElements('#commentSpace')[0]->isDisplayed());
		$browser->playWithResult('S');
		// Wait for comment space to become visible after solving
		$browser->waitUntilCssSelectorDisplayed('#commentSpace');
		$this->assertTrue($browser->getElements('#commentSpace')[0]->isDisplayed());
	}

	public function testShowCommentOnVisitingAlreadySolvedTsumego()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'comments' => [['message' => 'spoiler']], 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$this->assertTrue($browser->getElements('#commentSpace')[0]->isDisplayed());
	}

	public function testCommentsLinkToViewableSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'alice']],
		]);

		$tsumego = ClassRegistry::init('Tsumego');
		$tsumego->create();
		$tsumego->save(['description' => 'comment set link', 'author' => '']);

		// Private set connection created first, so it would win the old MIN(id) pick
		$aliceSet = ClassRegistry::init('Set');
		$aliceSet->create();
		$aliceSet->save(['title' => 'alice favorites', 'title2' => '', 'public' => 0, 'user_id' => $context->otherUsers[0]['id'], 'order' => 1]);
		$aliceConnection = ClassRegistry::init('SetConnection');
		$aliceConnection->create();
		$aliceConnection->save(['tsumego_id' => $tsumego->id, 'set_id' => $aliceSet->id, 'num' => 3]);

		$publicSet = ClassRegistry::init('Set');
		$publicSet->create();
		$publicSet->save(['title' => 'public set', 'title2' => '', 'public' => 1, 'order' => 1]);
		$publicConnection = ClassRegistry::init('SetConnection');
		$publicConnection->create();
		$publicConnection->save(['tsumego_id' => $tsumego->id, 'set_id' => $publicSet->id, 'num' => 10]);

		$comment = ClassRegistry::init('TsumegoComment');
		$comment->create();
		$comment->save(['tsumego_id' => $tsumego->id, 'user_id' => $context->user['id'], 'message' => 'hello']);

		$this->get('comments/index');

		$this->assertTextContains('public set', $this->view);
		$this->assertStringNotContainsString('alice favorites', $this->view);
	}

	public function testCommentsToggle()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'comments' => [['message' => 'test comment']], 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Content should be HIDDEN by default (no tab selected)
		$content = $browser->findById('msg2x');
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden by default');

		// Click COMMENTS tab to EXPAND
		$commentsTab = $browser->find('.tsumego-comments__tab[data-filter="open"]');
		$commentsTab->click();
		$browser->waitUntilCssSelectorDisplayed('#msg2x');
		$this->assertTrue($content->isDisplayed(), 'Comments should be visible after clicking COMMENTS tab');
		$this->assertTrue($commentsTab->getAttribute('class') === 'tsumego-comments__tab active' || strpos($commentsTab->getAttribute('class'), 'active') !== false, 'COMMENTS tab should be active');

		// Click same tab again to COLLAPSE
		$commentsTab->click();
		$browser->waitUntilCssSelectorNotDisplayed('#msg2x');
		$this->assertFalse($content->isDisplayed(), 'Comments should be hidden after clicking COMMENTS tab again');
	}

	public function testIssueDisplayed()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Test issue to display', 'created' => '2026-08-15 23:30:00']],
				'status' => 'S']]);
		$browser = Browser::instance();

		// Add cache buster to force fresh page load (React bundle must be fresh)
		$url = '/' . $context->tsumegos[0]['set-connections'][0]['id'] . '?nocache=' . time();
		$browser->get($url);

		// Expand OPEN issues tab (issues are hidden by default until tab clicked)
		$browser->expandComments();

		// Wait for React to render the issue content
		$issueId = $context->issues[0]['id'];
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Issue #' . $issueId . '")');

		// Issue should be displayed with database ID
		$issueId = $context->issues[0]['id'];
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Issue #' . $issueId, $pageSource);
		$this->assertTextContains('Test issue to display', $pageSource);

		// 23:30 UTC is already the next day in the Europe/Prague browser
		$this->assertSame('2026-08-16', $browser->find('.tsumego-issue__date')->getText());
		$this->assertSame('2026-08-16 01:30', $browser->find('.tsumego-issue .tsumego-comment__date')->getText());
	}

	public function testCoordinateHighlightAreStyledAndHaveProperClass()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Play at C3 for the solution']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default)

		// Find the coordinate span
		$coordSpan = $browser->find('.go-coord');
		$this->assertNotNull($coordSpan);
		$this->assertEquals('C3', $coordSpan->getText());
	}

	public function testReplyToIssue()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'issues' => [['message' => 'Original issue']], 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Click Reply button to show the reply form
		$replyButton = $browser->find('.tsumego-issue__reply-btn');
		$replyButton->click();
		// Wait for reply form to appear
		$browser->waitUntilCssSelectorDisplayed('.tsumego-issue__reply-form textarea');

		// Fill in the reply
		$replyField = $browser->find('.tsumego-issue__reply-form textarea');
		$replyField->sendKeys("My reply to this issue");

		// Submit the reply
		$submitButton = $browser->find('.tsumego-issue__reply-form button[type="submit"]');
		$submitButton->click();

		// Wait for response - reply should appear in page source
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("My reply to this issue")');

		// Verify reply appears on the page after reload
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Wait for React to render
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('My reply to this issue', $pageSource);
	}

	public function testDeleteOwnComment()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Comment to delete']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Verify comment is visible
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to delete', $pageSource);

		// Override confirm before JavaScript tries to use it (button uses window.confirm)
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find and click the Delete button
		$deleteButton = $browser->find('.deleteComment');
		$deleteButton->click();

		// Wait for comment to be removed from DOM
		$browser->waitUntilCssSelectorDoesntExist('.tsumego-comment--standalone', 10);

		// Verify comment is no longer visible (reload to confirm it's deleted)
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextNotContains('Comment to delete', $pageSource);
	}

	/**
	 * Test deleting the last comment in an issue removes the entire issue via React.
	 *
	 * When the last comment in an issue is deleted, the issue itself should be
	 * removed from the DOM immediately (not just after page refresh).
	 */
	public function testDeleteLastCommentInIssueRemovesIssue()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'This is the only comment in this issue']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Verify issue and comment are visible
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('This is the only comment in this issue', $pageSource);
		$this->assertTextContains('tsumego-issue', $pageSource); // Issue container class

		// Override confirm for React
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find the delete button for the comment inside the issue
		$deleteButton = $browser->find('.tsumego-issue .deleteComment');
		$deleteButton->click();

		// Wait for issue to be removed from DOM
		$browser->waitUntilCssSelectorDoesntExist('.tsumego-issue', 10);


		// Verify issue container is gone from rendered DOM
		$issueElements = $browser->getElements('.tsumego-issue');
		$this->assertEmpty($issueElements, 'Issue element should be removed from DOM after deleting last comment');
	}

	/**
	 * Test that comment counts update via React when deleting a comment.
	 *
	 * The counts in the section header and tabs should update immediately after deleting.
	 */
	public function testDeleteCommentUpdatesCountsViaReact()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Issue comment 1']],
				'comments' => [['message' => 'Standalone comment 1']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Verify comments section is visible
		$browser->idExists('msg2x');

		// Verify initial counts: 1 issue + 1 standalone = 2 total
		// COMMENTS tab shows comments + open issues, CLOSED ISSUES tab shows closed issues
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('1 COMMENT', $pageSource, 'Should show 1 COMMENT initially');
		$this->assertTextContains('🔴 1 OPEN ISSUE', $pageSource, 'Should show open issue indicator initially');
		// Empty CLOSED ISSUES tab shows no count in parentheses
		$this->assertTextContains('CLOSED ISSUES', $pageSource, 'Should show CLOSED ISSUES tab');
		// CLOSED ISSUES tab is now just empty text "CLOSED ISSUES" when count is 0

		// Override confirm for React
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Delete the standalone comment
		$deleteButton = $browser->find('.tsumego-comment--standalone .deleteComment');
		$deleteButton->click();

		// Wait for standalone comment to be removed from DOM
		$browser->waitUntilCssSelectorDoesntExist('.tsumego-comment--standalone', 10);

		// Verify counts updated: should now show 0 comments + 1 open issue
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('🔴 1 OPEN ISSUE', $pageSource, 'Should show only open issue after deleting comment');
		// CLOSED ISSUES tab shows just "CLOSED ISSUES" when empty (no number prefix)
	}

	/**
	 * Test that closing an issue updates the tab counts via React.
	 *
	 * When an issue is closed on the play page, the COMMENTS and CLOSED ISSUES counts should update.
	 */
	public function testCloseIssueUpdatesOpenCountBadge()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Open issue comment']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Verify comments section is visible
		$browser->idExists('msg2x');

		// Verify initial counts: 0 comments + 1 open issue in COMMENTS tab, 0 in CLOSED ISSUES tab
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('🔴 1 OPEN ISSUE', $pageSource, 'Should show open issue indicator initially');
		// Empty CLOSED ISSUES tab shows no count in parentheses
		$this->assertTextContains('CLOSED ISSUES', $pageSource, 'Should show CLOSED ISSUES tab');
		// CLOSED ISSUES tab shows just "CLOSED ISSUES" when empty

		// Click close button on the issue
		$closeButton = $browser->find('.tsumego-issue button.btn--success');
		$closeButton->click();

		// Wait for issue to become closed
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("1 CLOSED ISSUE")');

		// Verify counts updated: 0 comments, 0 open issues in COMMENTS, 1 in CLOSED ISSUES
		$pageSource = $browser->driver->getPageSource();
		// When count is 0, tab shows "COMMENTS" without count (gray styling)
		$this->assertTextContains('COMMENTS', $pageSource, 'Should show COMMENTS tab after closing');
		$this->assertTextContains('1 CLOSED ISSUE', $pageSource, 'Should show 1 CLOSED ISSUE after closing');
		// The issue status should now show "Closed"
		$this->assertTextContains('Closed', $pageSource, 'Issue should show Closed status');
	}

	/**
	 * Test that creating an issue via "Report as issue" checkbox works via React.
	 *
	 * When user checks "Report as issue" and submits, the issue should appear
	 * without page reload and counts should update.
	 */
	public function testCreateIssueViaCheckbox()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Expand comments section (hidden by default for non-admins)

		// Initially there should be no issue DOM elements
		// Note: We check for .tsumego-issue elements, not "Issue #" text
		// because the page source includes JS template strings with "Issue #"
		$issues = $browser->getElements('.tsumego-issue');
		$this->assertCount(0, $issues, 'Should have no issue elements initially');

		// Check the "Report as issue" checkbox
		$checkbox = $browser->findById('reportIssueCheckbox-tsumegoCommentForm');
		$checkbox->click();

		// Fill in the message
		$textarea = $browser->findById('commentMessage-tsumegoCommentForm');
		$textarea->sendKeys('This is a test issue report');

		// Submit the form
		$submitButton = $browser->findById('submitBtn-tsumegoCommentForm');
		$submitButton->click();

		// Wait for issue to appear in DOM
		$browser->find('.tsumego-issue', 10);

		// Verify issue appears without page reload - check DOM element
		$issues = $browser->getElements('.tsumego-issue');
		$this->assertCount(1, $issues, 'Issue element should appear after submission');

		// Verify the text content
		$pageSource = $browser->driver->getPageSource();
		// Get the created issue ID from context
		$issueId = $context->issues[0]['id'];
		$this->assertTextContains('Issue #' . $issueId, $pageSource, 'Issue should appear after submission');
		$this->assertTextContains('This is a test issue report', $pageSource, 'Issue message should appear');
		$this->assertTextContains('🔴 1 OPEN ISSUE', $pageSource, 'Tab should show open issue indicator');
		// CLOSED ISSUES tab shows just "CLOSED ISSUES" when empty (no number prefix)
	}

	public function testDragHandlesAppearForAdminOnPlayPage()
	{
		// Create tsumego with a comment, already solved
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Comment with drag handle']],
				'status' => 'S']]);

		$browser = Browser::instance();

		// Go to play page via set connection ID (how the app routes)
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();

		// Wait for comments section to be visible
		$browser->find('#commentSpace .tsumego-comment', 5);

		// Check DOM elements directly - drag handles should be present for admin
		$dragHandles = $browser->getElements('.tsumego-comment__drag-handle');
		$this->assertGreaterThan(0, count($dragHandles), 'Drag handles should be present for admin');

		// Also verify data-comment-id attribute exists
		$comments = $browser->getElements('.tsumego-comment[data-comment-id]');
		$this->assertGreaterThan(0, count($comments), 'Comments should have data-comment-id attribute');

		$browser->assertNoErrors();
	}

	public function testDragHandlesDoNotAppearForNonAdmin()
	{
		// Create tsumego with a comment - NON-admin user
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Comment without drag handle']],
				'status' => 'S', // Already solved
			],
		]);

		$browser = Browser::instance();

		// Go to play page
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();

		// Wait for comments section to be visible
		$browser->find('#commentSpace .tsumego-comment', 5);

		// Check DOM elements directly - not page source text which includes JS code
		$dragHandles = $browser->getElements('.tsumego-comment__drag-handle');
		$this->assertCount(0, $dragHandles, 'Drag handles should NOT be present for non-admin');

		// Comment should still exist
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment without drag handle', $pageSource);

		$browser->assertNoErrors();
	}

	public function testCommentsHaveDragHandlesForAdmin()
	{
		// Create tsumego with a comment
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Draggable comment']],
				'status' => 'S',
			],
		]);

		$browser = Browser::instance();

		// Go to play page
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();

		// Wait for comments
		$browser->find('#commentSpace .tsumego-comment', 5);

		// Check that drag handles are present for admin
		$dragHandles = $browser->getElements('.tsumego-comment__drag-handle');
		$this->assertCount(1, $dragHandles, 'Drag handles should be present for admin');

		// Check that the comment has the draggable class
		$draggableComments = $browser->getElements('.tsumego-comment--draggable');
		$this->assertCount(1, $draggableComments, 'Comments should have draggable class for admin');

		$browser->assertNoErrors();
	}

	public function testMakeIssueButton()
	{
		// Create tsumego with only standalone comments (no issues)
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Comment to become issue']],
				'status' => 'S']]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();
		$browser->find('.tsumego-comment--standalone', 5);

		// Verify initial state
		$this->assertCount(0, $browser->getElements('.tsumego-issue'), 'Should have 0 issues initially');
		$this->assertCount(1, $browser->getElements('.tsumego-comment--standalone'), 'Should have 1 standalone');

		// Find and click the Make Issue button (uses .btn--small after button unification)
		$makeIssueBtn = $browser->findBy(WebDriverBy::xpath('//button[contains(normalize-space(.), "Make Issue")]'));
		$this->assertNotNull($makeIssueBtn, 'Make Issue button should exist');
		$makeIssueBtn->click();

		// Wait for issue to appear in DOM
		$browser->find('.tsumego-issue', 10);

		// Verify comment is now in an issue
		$this->assertCount(1, $browser->getElements('.tsumego-issue'), 'Should have 1 issue now');
		$this->assertCount(0, $browser->getElements('.tsumego-comment--standalone'), 'Should have 0 standalone');

		// Verify the comment content is still there
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to become issue', $pageSource);
		// Get the created issue ID from context
		$issueId = $context->issues[0]['id'];
		$this->assertTextContains('Issue #' . $issueId, $pageSource);

		$browser->assertNoErrors();
	}

	public function testMakeIssueButtonOnlyOnStandalone()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Comment in issue']],
				'comments' => [['message' => 'Standalone comment']],
				'status' => 'S',
			],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();
		$browser->find('.tsumego-comment--standalone', 5);

		// Make Issue button should exist on standalone comments (uses .btn--small)
		$standaloneMakeIssue = $browser->getAllBy(WebDriverBy::xpath('.//div[contains(@class, "tsumego-comment--standalone")]//button[contains(normalize-space(.), "Make Issue")]'));
		$this->assertCount(1, $standaloneMakeIssue, 'Standalone comment should have Make Issue button');

		// Make Issue button should NOT exist on issue comments
		$issueMakeIssue = $browser->getAllBy(WebDriverBy::xpath('.//div[contains(@class, "tsumego-issue")]//button[contains(normalize-space(.), "Make Issue")]'));
		$this->assertCount(0, $issueMakeIssue, 'Issue comments should NOT have Make Issue button');

		$browser->assertNoErrors();
	}

	/**
	 * Test that filter state (COMMENTS tab) is preserved when replying to an issue via React.
	 *
	 * When viewing comments tab (open issues + comments), adding a reply to an open issue
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterReplyToOpenIssue()
	{
		// Setup: Create tsumego with one open issue AND one closed issue
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'issues' => [
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS],
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Click COMMENTS tab (shows open issues + comments)

		// Verify: Open issue is visible
		$openIssues = $browser->getElements('.tsumego-issue--opened');
		$this->assertCount(1, $openIssues, 'Should have 1 open issue visible');
		$this->assertTrue($openIssues[0]->isDisplayed(), 'Open issue should be visible');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden');

		// Click Reply on the open issue
		$replyButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-btn');
		$replyButton->click();
		// Wait for reply form to appear
		$browser->waitUntilCssSelectorDisplayed('.tsumego-issue--opened .tsumego-issue__reply-form textarea');

		// Fill in and submit the reply
		$replyField = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form textarea');
		$replyField->sendKeys("Reply to open issue");
		$submitButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]');
		$submitButton->click();
		// Wait for reply to appear in page source
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Reply to open issue")');

		// After React re-render: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after re-render');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after re-render');

		// Open issue should still be visible
		$openIssuesAfter = $browser->getElements('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after re-render');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after re-render');
	}

	/**
	 * Test that filter state is preserved when adding a standalone comment via React.
	 *
	 * When viewing comments tab with closed issues present, adding a new standalone comment
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterAddingStandaloneComment()
	{
		// Setup: Create tsumego with a standalone comment AND a closed issue
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'comments' => [['message' => 'Existing standalone comment']],
				'issues' => [['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS]],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Click COMMENTS tab (shows comments, hides closed issues)

		// Verify: Standalone comment is visible
		$standaloneComments = $browser->getElements('.tsumego-comment--standalone');
		$this->assertCount(1, $standaloneComments, 'Should have 1 standalone comment visible');
		$this->assertTrue($standaloneComments[0]->isDisplayed(), 'Standalone comment should be visible');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden');

		// Add a new standalone comment via the form (use main form, not reply forms)
		$messageField = $browser->findById('commentMessage-tsumegoCommentForm');
		$messageField->click();
		$messageField->sendKeys("New standalone comment");
		$submitButton = $browser->findById('submitBtn-tsumegoCommentForm');
		$submitButton->click();
		// Wait for the new standalone comment to be rendered by React (mutation + refetch cycle)
		$browser->waitUntilJs('document.querySelectorAll(".tsumego-comment--standalone").length >= 2');

		// After React re-render: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after re-render');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after adding comment');

		// New comment should be visible
		$standaloneCommentsAfter = $browser->getElements('.tsumego-comment--standalone');
		$this->assertCount(2, $standaloneCommentsAfter, 'Should have 2 standalone comments after adding');
	}

	/**
	 * Test that filter state is preserved when deleting a comment from an open issue via React.
	 *
	 * When viewing comments tab with closed issues present, deleting a comment from an open issue
	 * should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterDeletingCommentFromOpenIssue()
	{
		// Setup: Create tsumego with an open issue with 2 comments AND a closed issue
		$context = new ContextPreparator([
			'user' => ['admin' => true], // Admin can delete any comment
			'tsumego' => [
				'set_order' => 1,
				'issues' => [
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS]],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Click COMMENTS tab (shows comments, hides closed issues)

		// Add a second comment to the open issue so we can delete one
		$replyButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-btn');
		$replyButton->click();
		// Wait for reply form
		$browser->waitUntilCssSelectorDisplayed('.tsumego-issue--opened .tsumego-issue__reply-form textarea');
		$replyField = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form textarea');
		$replyField->sendKeys("Extra comment to delete");
		$submitButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]');
		$submitButton->click();
		// Wait for the reply to be rendered by React (mutation + refetch cycle)
		$browser->waitUntilJs('document.querySelectorAll(".tsumego-issue--opened .tsumego-comment").length >= 2');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden before delete');

		// Override confirm BEFORE deleting
		$browser->driver->executeScript("window.confirm = function() { return true; };");

		// Find and click delete on one of the comments in the open issue
		$deleteButtons = $browser->getElements('.tsumego-issue--opened .deleteComment');
		$deleteCountBefore = count($deleteButtons);
		$this->assertGreaterThan(0, $deleteCountBefore, 'Should have delete buttons in open issue');
		$deleteButtons[0]->click();
		// Wait for delete to process (comment count should decrease)
		$browser->waitUntilJs('document.querySelectorAll(".tsumego-issue--opened .deleteComment").length < ' . $deleteCountBefore);

		// After React re-render: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after delete');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after deleting comment');

		// Open issue should still be visible
		$openIssuesAfter = $browser->getElements('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after delete');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after delete');
	}

	/**
	 * Test that filter state is preserved when moving a comment out of an issue via drag-and-drop.
	 *
	 * When viewing comments tab with closed issues present, moving a comment out of an open issue
	 * (to standalone) should NOT cause closed issues to become visible - the filter must be preserved.
	 */
	public function testFilterPreservedAfterMovingCommentOutOfIssue()
	{
		// Setup: Create tsumego with an open issue with 2 comments AND a closed issue
		$context = new ContextPreparator([
			'user' => ['admin' => true], // Admin can drag-and-drop
			'tsumego' => [
				'set_order' => 1,
				'issues' => [
					['message' => 'Closed issue message', 'status' => TsumegoIssue::$CLOSED_STATUS],
					['message' => 'Open issue message', 'status' => TsumegoIssue::$OPENED_STATUS],
				],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments(); // Click COMMENTS tab (shows comments, hides closed issues)

		// Add a second comment to the open issue so we can move one
		$replyButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-btn');
		$replyButton->click();
		// Wait for reply form
		$browser->waitUntilCssSelectorDisplayed('.tsumego-issue--opened .tsumego-issue__reply-form textarea');
		$replyField = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form textarea');
		$replyField->sendKeys("Comment to move");
		$submitButton = $browser->find('.tsumego-issue--opened .tsumego-issue__reply-form button[type="submit"]');
		$submitButton->click();
		// Wait for the reply to be rendered by React (mutation + refetch cycle)
		$browser->waitUntilJs('document.querySelectorAll(".tsumego-issue--opened .tsumego-comment").length >= 2');

		// Get the comment ID to move
		$commentElements = $browser->getElements('.tsumego-issue--opened .tsumego-comment');
		$this->assertGreaterThan(0, count($commentElements), 'Should have comments in open issue');
		$commentId = $commentElements[0]->getAttribute('data-comment-id');

		// Verify: Closed issue is NOT visible (filtered out)
		$closedIssues = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssues, 'Should have 1 closed issue in DOM');
		$this->assertFalse($closedIssues[0]->isDisplayed(), 'Closed issue should be hidden before move');

		// Call the move-comment API and trigger React Query refetch (React-compatible approach)
		$browser->driver->executeScript("
			var formData = new URLSearchParams();
			formData.append('data[Comment][tsumego_issue_id]', 'standalone');
			fetch('/tsumego-issues/move-comment/' + " . $commentId . ", {
				method: 'POST',
				body: formData,
				headers: {'X-Requested-With': 'XMLHttpRequest'}
			}).then(function(response) {
				return response.json();
			}).then(function() {
				// Trigger React Query refetch so React re-renders with updated data
				if (window.__invalidateComments) window.__invalidateComments();
			});
		");
		// Wait for React to refetch and re-render - moved comment appears as standalone
		$browser->waitUntilJs('document.querySelectorAll(".tsumego-comment--standalone").length > 0');

		// After React re-render: Closed issue should STILL be hidden
		$closedIssuesAfter = $browser->getElements('.tsumego-issue--closed');
		$this->assertCount(1, $closedIssuesAfter, 'Should still have 1 closed issue in DOM after move');
		$this->assertFalse($closedIssuesAfter[0]->isDisplayed(), 'Closed issue should STILL be hidden after moving comment');

		// Open issue should still be visible
		$openIssuesAfter = $browser->getElements('.tsumego-issue--opened');
		$this->assertCount(1, $openIssuesAfter, 'Should still have 1 open issue after move');
		$this->assertTrue($openIssuesAfter[0]->isDisplayed(), 'Open issue should still be visible after move');
	}

	public function testCommentFormHiddenOnClosedIssuesTab()
	{
		$context = new ContextPreparator([
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Closed issue', 'status' => TsumegoIssue::$CLOSED_STATUS]],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Click COMMENTS tab - form should be visible
		$commentsTab = $browser->find('.tsumego-comments__tab[data-filter="open"]');
		$commentsTab->click();
		// Wait for form to become visible
		$browser->waitUntilCssSelectorDisplayed('#tsumegoCommentForm');

		$form = $browser->findById('tsumegoCommentForm');
		$formWrapper = $form->findElement(WebDriverBy::xpath('./..'));
		$this->assertTrue($formWrapper->isDisplayed(), 'Comment form should be visible on COMMENTS tab');

		// Click CLOSED ISSUES tab - form should be hidden
		$closedTab = $browser->find('.tsumego-comments__tab[data-filter="closed"]');
		$closedTab->click();
		$browser->waitUntilCssSelectorNotDisplayed('#tsumegoCommentForm');

		$this->assertFalse($formWrapper->isDisplayed(), 'Comment form should be hidden on CLOSED ISSUES tab');

		// Click COMMENTS tab again - form should be visible again
		$commentsTab->click();
		$browser->waitUntilCssSelectorDisplayed('#tsumegoCommentForm');

		$this->assertTrue($formWrapper->isDisplayed(), 'Comment form should be visible when back on COMMENTS tab');
	}

	/**
	 * Test that a newly added comment can be dragged (Make Issue button works).
	 *
	 * After adding a comment via React, the new comment should have a working Make Issue button.
	 */
	public function testNewlyAddedCommentCanBeDragged()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true], // Admin can drag
			'tsumego' => ['set_order' => 1, 'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();

		// Add a new comment
		$messageField = $browser->findById('commentMessage-tsumegoCommentForm');
		$messageField->click();
		$messageField->sendKeys("Comment to make into issue");
		$submitButton = $browser->findById('submitBtn-tsumegoCommentForm');
		$submitButton->click();
		// Wait for the comment to appear in the DOM
		$browser->find('.tsumego-comment--standalone', 5);
		$standaloneComments = $browser->getElements('.tsumego-comment--standalone');
		$this->assertCount(1, $standaloneComments, 'Should have 1 standalone comment');

		// Find and click the Make Issue button (uses .btn--small after button unification)
		$makeIssueBtn = $browser->findBy(WebDriverBy::xpath('//button[contains(normalize-space(.), "Make Issue")]'));
		$this->assertTrue($makeIssueBtn->isDisplayed(), 'Make Issue button should be visible');
		$this->assertTrue($makeIssueBtn->isEnabled(), 'Make Issue button should be enabled');
		$makeIssueBtn->click();

		// Wait for issue to appear in DOM
		$browser->find('.tsumego-issue', 10);

		// Verify comment is now in an issue
		$issues = $browser->getElements('.tsumego-issue');
		$this->assertCount(1, $issues, 'Should have 1 issue now');
		$standaloneCommentsAfter = $browser->getElements('.tsumego-comment--standalone');
		$this->assertCount(0, $standaloneCommentsAfter, 'Should have 0 standalone comments after making issue');

		// Verify the comment content is still there
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Comment to make into issue', $pageSource);
		// Get the created issue ID from context
		$issueId = $context->issues[0]['id'];
		$this->assertTextContains('Issue #' . $issueId, $pageSource);
	}

	// Test that comments longer than 2048 characters are rejected with proper validation message.
	public function testCommentLengthValidation()
	{
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => ['set_order' => 1, 'status' => 'S']]);

		$TsumegoComment = ClassRegistry::init('TsumegoComment');

		// Test: comment within limit (2048 chars) should succeed
		$validComment = str_repeat('a', 2048);
		$TsumegoComment->create();
		$result = $TsumegoComment->save([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'message' => $validComment,
			'user_id' => $context->user['id']]);
		$this->assertTrue($result !== false, 'Valid comment (2048 chars) should save successfully');

		// Test: comment over limit (2049 chars) should fail
		$invalidComment = str_repeat('b', 2049);
		$TsumegoComment->create();
		$result = $TsumegoComment->save([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'message' => $invalidComment,
			'user_id' => $context->user['id']]);
		$this->assertFalse($result, 'Invalid comment (2049 chars) should fail validation');

		// Verify the validation error message
		$validationErrors = $TsumegoComment->validationErrors;
		$this->assertArrayHasKey('message', $validationErrors);
		// Check for the error message substring (it might be an array with multiple errors)
		$messageErrors = $validationErrors['message'];
		if (is_array($messageErrors))
		{
			$errorFound = false;
			foreach ($messageErrors as $error)
				if (strpos($error, 'too long') !== false)
				{
					$errorFound = true;
					break;
				}
			$this->assertTrue($errorFound, 'Expected "too long" error message in validation errors: ' . print_r($messageErrors, true));
		}
		else
			$this->assertStringContainsString('too long', $messageErrors);
	}

	public function testDiscussionPageNoDuplicateCommentsForMultiSetTsumego()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sets' => [
					['name' => 'Set A', 'num' => 1],
					['name' => 'Set B', 'num' => 1]
				],
				'status' => 'S',
				'comments' => [['message' => 'UNIQUE_TEST_COMMENT_XYZ']]
			]
		]);

		$this->get('comments');

		// Comment appears in both "All Comments" and "Your Comments" sections (= 2 times)
		$this->assertSame(2, substr_count($this->view, 'UNIQUE_TEST_COMMENT_XYZ'));
	}

	/**
	 * No new set has a subtitle, and the list still names the set of each comment.
	 */
	public function testCommentListNamesASetWithoutSubtitle()
	{
		new ContextPreparator([
			'tsumego' => [
				'sets' => [['name' => 'Set without subtitle', 'num' => 7]],
				'status' => 'S',
				'comments' => [['message' => 'A comment']]]]);

		$this->get('comments');

		$this->assertTextContains('Set without subtitle - 7', $this->view);
	}

	/**
	 * Test that issue author's Go rank is displayed correctly on the play page.
	 */
	public function testIssueAuthorRankOnPlayPage()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'rating' => 1500],
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Issue to check rank']],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->expandComments();

		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Issue to check rank")');

		$pageSource = $browser->driver->getPageSource();
		// Rating 1500 should display as 6k rank
		$this->assertTextContains('6k', $pageSource);
	}

	/**
	 * Test reopening a closed issue from the play page.
	 */
	public function testReopenClosedIssue()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumego' => [
				'set_order' => 1,
				'issues' => [['message' => 'Issue to reopen', 'status' => TsumegoIssue::$CLOSED_STATUS]],
				'status' => 'S']]);
		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		// Click CLOSED ISSUES tab to see the closed issue
		$browser->find('.tsumego-comments__tab[data-filter="closed"]', 5)->click();

		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Issue to reopen")');

		// Click Reopen button
		$reopenButton = $browser->find('.tsumego-issue button.btn--warning');
		$reopenButton->click();

		// Wait for status to change to Open
		$browser->waitUntilJs('document.documentElement.outerHTML.includes("Opened")');

		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('Opened', $pageSource);
	}
}
