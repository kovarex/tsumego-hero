<?php

use App\Model\Table\SemeaiTypeTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeAttemptStatusTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Model\Table\TsumegoVariantTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Rating;
use App\Utility\TimeModeUtil;
use App\Utility\Util;
use Facebook\WebDriver\Exception\TimeoutException;

#[RetryOnBrowserFailure]
class PlayGameplayBrowserTest extends IntegrationTestCase
{
	//SGF with a 19x19 board and 3 clearly positioned setup stones.
	private const SGF_19 = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc][dd]AW[ee];B[aa];W[ab];B[ba]C[+])';

	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	private function waitForBoard(Browser $browser): void
	{
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor && typeof besogo.editor.getCurrent === "function"');
	}

	private function commentSpaceDisplay(Browser $browser): string
	{
		$display = $browser->driver->executeScript(
			"var el = document.getElementById('commentSpace'); return el ? getComputedStyle(el).display : 'missing';"
		);
		return is_string($display) ? $display : 'missing';
	}

	//Read the current node's stones as [x, y, color] triplets in *logical*
	//board coordinates. Child nodes inherit getStone/getSize via
	//Object.create(this), so this is reliable on the play page.
	private function logicalStones(Browser $browser): array
	{
		$json = $browser->driver->executeScript(
			'var node = besogo.editor.getCurrent();'
			. 'var size = node.getSize();'
			. 'var stones = [];'
			. 'for (var y = 1; y <= size.y; y++) {'
			. '  for (var x = 1; x <= size.x; x++) {'
			. '    var c = node.getStone(x, y);'
			. '    if (c) stones.push([x, y, c]);'
			. '  }'
			. '}'
			. 'return stones;'
		);

		return (array) $json;
	}

	public function testBoardRendersExpectedSizeAndSetupStones(): void
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'renderUser',
				// Pin the player color and board orientation so the board is never
				// swapped or rotated (deterministic stone coordinates and colors).
				'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL,
				'board_orientation_setting' => UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL,
			],
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		// Wait until the SGF has actually been parsed and the setup stones placed.
		$browser->waitUntilJs('besogo.editor.getCurrent().getStone(3, 3) !== 0');

		$size = $browser->driver->executeScript('return besogo.editor.getCurrent().getSize();');

		// The puzzle board must be 19x19 (the board the SGF declares).
		$this->assertSame(19, (int) $size['x'], 'Board width should be 19');
		$this->assertSame(19, (int) $size['y'], 'Board height should be 19');

		// The three setup stones must be present at their authored coordinates.
		$stones = $this->logicalStones($browser);
		$this->assertCount(3, $stones, 'Three setup stones should be rendered');

		$keyed = [];
		foreach ($stones as $stone)
			$keyed[$stone[0] . ',' . $stone[1]] = $stone[2];

		// cc -> (3,3) black(-1), dd -> (4,4) black(-1), ee -> (5,5) white(1)
		$this->assertSame(-1, $keyed['3,3'], 'Black stone at cc');
		$this->assertSame(-1, $keyed['4,4'], 'Black stone at dd');
		$this->assertSame(1, $keyed['5,5'], 'White stone at ee');
	}

	public function testThePassButtonOnlyShowsWhenTheProblemAllowsIt(): void
	{
		foreach ([1 => 'a problem that can be passed', 0 => 'a problem that cannot'] as $pass => $case)
		{
			$context = new ContextPreparator([
				'tsumego' => ['set_order' => 1, 'pass' => $pass, 'sgf' => self::SGF_19]]);
			$browser = Browser::instance();
			$browser->get($this->playUrl($context));
			$this->waitForBoard($browser);

			$this->assertSame((bool) $pass, $browser->idExists('besogo-pass-button'), 'Pass button on ' . $case);
			if (!$pass)
				continue;

			$moveBefore = (int) $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');
			$browser->clickId('besogo-pass-button');
			$moveAfter = (int) $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');
			$this->assertGreaterThan($moveBefore, $moveAfter, 'Passing advances the move number');
			$this->assertSame(0, $browser->driver->executeScript('return window.boardLockValue;'),
				'Passing does not lock the board');
		}
	}

	//Next leads to the following problem; there is no Back, the problem buttons lead back
	public function testTheNavigationButtonsLeadToTheNeighbouringPuzzles(): void
	{
		$context = new ContextPreparator(['tsumegos' => [1, 2, 3]]);
		$browser = Browser::instance();
		$urls = array_map(fn($tsumego) => '/' . $tsumego['set-connections'][0]['id'], $context->tsumegos);

		$browser->get($urls[1]);
		$this->waitForBoard($browser);

		$this->assertFalse($browser->idExists('besogo-back-button'), 'There is no Back button');
		$this->assertSame($urls[2],
			$browser->find('#besogo-next-button')->getAttribute('href'),
			'The next button points at the following problem');

		$this->assertTrue($browser->find('#besogo-next-button')->isDisplayed(), 'Next is with Reset and Review in every state');
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.href.includes("' . $urls[2] . '")');
		$this->waitForBoard($browser);
		$this->assertStringContainsString($urls[2], $browser->driver->getCurrentURL(), 'The next button moves on');
	}

	public function testMultipleChoiceAnswerClickShowsSolved(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'mcUser'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF_19,
				'variants' => [[
					'tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE,
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'correct_answer' => '3',
				]],
			],
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$browser->waitUntilJs('document.getElementById("besogo-multipleChoice3") !== null');

		// correct_answer=3, so choosing the third answer is correct.
		$browser->clickId('besogo-multipleChoice3');

		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Correct")');

		$this->assertStringContainsString('Correct', $browser->find('#status')->getText(),
			'Choosing the correct answer should show a Correct banner');
	}

	//The explanation of a multiple choice question shows once it is answered correctly
	public function testMultipleChoiceCorrectAnswerShowsItsExplanation(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'mcExplanationUser'],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => self::SGF_19,
				'variants' => [[
					'tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE,
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'correct_answer' => '2',
					'explanation' => 'White has no second eye'
				]]
			]
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$browser->waitUntilJs('document.getElementById("besogo-multipleChoice2") !== null');
		$browser->clickId('besogo-multipleChoice2');

		$browser->waitUntilJs('document.getElementById("multipleChoiceText").innerText.includes("White has no second eye")');
		$this->assertStringContainsString('Correct', $browser->find('#status')->getText());
	}

	//Answering a semeai shows the explanation made from the problem's liberties
	public function testSemeaiAnswerShowsTheExplanation(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'semeaiAnswerUser'],
			'tsumego' => [
				'set_order' => 1,
				'semeai' => [
					'semeai_type_id' => SemeaiTypeTable::TYPE_NO_EYES_FEW_INSIDE_LIBERTIES,
					'minimum_liberties' => 0,
					'maximum_liberties' => 2,
					'liberty_count' => 6,
					'variance' => 2],
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[hm][im][gn][jn][go][jo][gp][jp][eq][gq][jq][fr][jr][gs][js]AB[jm][km][in][ln][io][lo][ip][lp];B[aa];W[ab];B[ba]C[+])'
			]
		]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$browser->waitUntilJs('document.getElementById("besogo-multipleChoice1") !== null');
		$browser->clickId('besogo-multipleChoice1');

		$browser->waitUntilJs('document.getElementById("multipleChoiceText").innerText.includes("Semeai Type 1")');
		$this->assertMatchesRegularExpression('/Black has \d+ libert/', $browser->find('#multipleChoiceText')->getText());
	}

	//After a mistake Reset keeps its look but gets a red outline and the keyboard focus, so Enter tries again
	public function testResetIsFocusedAndOutlinedAfterAMistake(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$resetLook = 'const reset = document.getElementById("besogo-reset-button"); const style = getComputedStyle(reset);'
			. ' return [document.activeElement === reset, style.borderTopColor, style.backgroundColor];';
		[$focused, $plainBorder, $plainBackground] = $browser->driver->executeScript($resetLook);
		$this->assertFalse($focused);

		$browser->playWithResult('F');
		[$focused, $border, $background] = $browser->driver->executeScript($resetLook);
		$this->assertTrue($focused, 'Reset has the focus after a mistake');
		$this->assertNotSame($plainBorder, $border, 'Reset is outlined after a mistake');
		$this->assertSame($plainBackground, $background, 'Reset keeps its colour after a mistake');

		$browser->driver->getKeyboard()->sendKeys(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$browser->waitUntilJs('!document.querySelector(".play-message").classList.contains("play-message--failed")');
		$this->assertSame($plainBorder, $browser->driver->executeScript($resetLook)[1], 'The outline goes with the reset');
	}

	public function testPostSolveMistakesAreNotPenalized(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalRating = $context->user['rating'];
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Prove competence
		$browser->playWithResult('S');
		$this->assertTrue(
			$browser->driver->executeScript('return problemSolved;'));

		// Post-solve exploration (reset + mistake) - must be harmless
		$browser->clickId('besogo-reset-button');
		$browser->playWithResult('F');

		$this->assertGreaterThan($originalRating,
			(float) $context->reloadUser()['rating'],
			'Rating must increase from solve; post-solve errors must not drag it down');

		$this->assertSame($originalDamage,
			(int) $context->user['damage'],
			'Hearts must not be lost for mistakes made after solving');

		$attempts = $this->fetchTable('TsumegoAttempt')->find()
			->where(['tsumego_id' => $context->tsumegos[0]['id'], 'user_id' => $context->user['id']])->all()->toList();
		$this->assertCount(1, $attempts,
			'Must produce exactly one attempt record');
		$this->assertTrue($attempts[0]->solved,
			'Attempt must be marked solved');
		$this->assertSame(0, $attempts[0]->misplays,
			'Post-solve mistakes must not appear in attempt history');
	}

	public function testResetAfterSolveDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Solve the puzzle
		$browser->driver->executeScript("displayResult('S');");
		$browser->waitForSubmitResult();

		// Reset the puzzle
		$browser->clickId('besogo-reset-button');

		// Verify no damage - puzzle was already solved, noXP guard protects
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting after solve should not cause damage');
	}

	public function testResetAfterFailDoesntCauseDuplicateDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail once
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'First fail should cause damage');

		// Reset - should NOT cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset after fail should not cause duplicate damage');
		$this->assertFalse($browser->driver->executeScript('return window.failAlreadyReported;'),
			'Giving up leaves the problem open for another attempt');
	}

	public function testResetAtStartDoesntCauseDamage(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Reset immediately without making any moves
		$browser->clickId('besogo-reset-button');

		// Verify no damage
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Resetting at start should not cause damage');
	}

	public function testMultipleFailsThenResetThenSolve(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1]]);
		$originalDamage = (int) $context->user['damage'];

		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		// Fail twice (second fail blocked by failAlreadyReported)
		$browser->playWithResult('F');
		$browser->playWithResult('F');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Only first fail should cause damage');

		// Reset - should not cause additional damage
		$browser->clickId('besogo-reset-button');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Reset should not cause additional damage');

		// Opening the problem again reports nothing on its own and costs nothing
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);
		$this->assertSame(true, $browser->driver->executeScript('return window._submitResultPromise === null;'),
			'Opening the problem reports nothing on its own');
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Opening the problem again does not cause damage');

		// Solve
		$browser->playWithResult('S');

		// Verify final state
		$this->assertSame($originalDamage + 1, (int) $context->reloadUser()['damage'],
			'Damage should only be from the first fail');
		$status = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => $context->user['id'], 'tsumego_id' => $context->tsumegos[0]['id']])->first();
		$this->assertSame('S', $status->status,
			'Status should be solved');
	}

	public function testSolveShowsCorrectAndUpdatesXP(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');

		$this->assertStringContainsString('Correct!', $browser->findById('status')->getText());
		$this->assertTrue($browser->driver->executeScript('return window.problemSolved;'));
		$this->assertTrue($browser->driver->executeScript('return window.noXP;'));
		$this->assertSame(1, $browser->driver->executeScript('return window.boardLockValue;'));

		// Account widget should reflect server state
		$this->assertGreaterThan(0, $browser->driver->executeScript('return window.accountWidget.xp;'));
		$this->assertNotNull($browser->driver->executeScript('return window.accountWidget.rating;'));

		// and so should the XP display
		$this->assertTrue($browser->driver->executeScript('return window.xpStatus !== undefined;'));
		$this->assertNotEmpty($this->xpDisplayText($browser),
			'The XP display shows the XP gained');
	}

	public function testFailShowsIncorrectAndLosesHeart(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		$this->assertStringContainsString('Incorrect', $browser->findById('status')->getText());
		$this->assertTrue($browser->driver->executeScript('return window.failAlreadyReported;'));
		$this->assertTrue($browser->driver->executeScript('return window.hadMisplay;'));
		$this->assertSame(1, $context->reloadUser()['damage']);
	}

	public function testTheAccountBarGlowsWhenWhatItShowsChanges(): void
	{
		// a fail costs rating, but no XP
		foreach ([['rating', 'S', true], ['rating', 'F', true], ['level', 'S', true], ['level', 'F', false]] as [$show, $result, $glows])
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$browser = Browser::instance();
			$browser->setCookie('showInAccountWidget', $show);
			$browser->get($this->playUrl($context));
			$this->waitForBoard($browser);

			$browser->driver->executeScript("displayResult('" . $result . "')");

			$this->assertSame($glows ? 'inline-block' : 'none',
				$browser->driver->executeScript("return document.getElementById('xp-increase-fx').style.display;"),
				$show . ' bar, result ' . $result);
		}
	}

	public function testARepeatedFailDoesNotMoveTheRatingAgain(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$rating = 'return Math.round(window.accountWidget.rating * 1000) / 1000;';
		$before = $browser->driver->executeScript($rating);

		$browser->driver->executeScript("displayResult('F')");
		$browser->waitForSubmitResult();
		$afterRecordedFail = $browser->driver->executeScript($rating);

		$browser->driver->executeScript("displayResult('F')");
		$browser->driver->executeScript("displayResult('F')");
		$afterRepeats = $browser->driver->executeScript($rating);

		$this->assertNotSame($before, $afterRecordedFail, 'A recorded fail must move the rating');
		$this->assertSame($afterRecordedFail, $afterRepeats,
			'The server is told about a fail once per problem, so the repeats must not move the rating again');
	}

	public function testFailsPastTheMaximumInARowDontMoveTheRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000],
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'attempt' => ['misplays' => Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW - 1]]]);
		$browser = Browser::instance();
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		$rating = 'return Math.round(window.accountWidget.rating * 1000) / 1000;';
		$ratingLoss = "return document.getElementById('ratingLossShort').textContent;";
		$before = $browser->driver->executeScript($rating);
		$this->assertNotSame('0', $browser->driver->executeScript($ratingLoss));

		// the last fail in a row that still counts
		$browser->playWithResult('F');
		$afterLastRatedFail = $browser->driver->executeScript($rating);
		$this->assertNotSame($before, $afterLastRatedFail);
		$this->assertSame('0', $browser->driver->executeScript($ratingLoss), 'The next fail costs no rating');

		$browser->clickId('besogo-reset-button');
		$browser->waitUntilJs("document.getElementById('xp-increase-fx').style.display == 'none'");
		$browser->driver->executeScript("displayResult('F')");
		$this->assertSame('none', $browser->driver->executeScript("return document.getElementById('xp-increase-fx').style.display;"),
			'The bar has no change to show');
		$browser->waitForSubmitResult();
		$this->assertSame($afterLastRatedFail, $browser->driver->executeScript($rating));
		$this->assertEqualsWithDelta($afterLastRatedFail, (float) $context->reloadUser()['rating'], 0.001);

		// the page knows the fails from before it was opened
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);
		$this->assertSame('0', $browser->driver->executeScript($ratingLoss));
	}

	public function testOnlyASolvedProblemCanBeReviewedAndCommented(): void
	{
		foreach (['S' => true, 'F' => false] as $result => $reveals)
		{
			$context = new ContextPreparator(['tsumego' => 1]);
			$browser = Browser::instance();
			$browser->get($this->playUrl($context));
			$this->waitForBoard($browser);
			$this->assertSame('none', $this->commentSpaceDisplay($browser), 'Comments are hidden while playing');

			$browser->playWithResult($result);

			$this->assertCount($reveals ? 1 : 0,
				$browser->getElements('#besogo-review-button'),
				'The review button after a ' . $result);
			$this->assertSame($reveals ? 'block' : 'none', $this->commentSpaceDisplay($browser),
				'The comments after a ' . $result);
		}
	}

	//Toggling review mode should NOT reset the board to the start position.
	//If the user navigated to a move, entering/leaving review keeps them there.
	public function testReviewTogglePreservesBoardPosition(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => self::SGF_19]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));
		$this->waitForBoard($browser);

		// Navigate to the first move so the board is not at the root position.
		// Use setCurrent (not clickBoard) so the puzzle's auto-response cannot
		// advance the board independently of the review toggle.
		$browser->driver->executeScript('var root = besogo.editor.getRoot(); if (root.children && root.children.length) besogo.editor.setCurrent(root.children[0]);');
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 1');
		$moveBefore = $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');

		$browser->clickId('besogo-review-button');

		$moveAfter = $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;');
		$this->assertSame($moveBefore, $moveAfter, 'Review toggle should not reset the board to the start position');
	}

	public function testSolvedPuzzleCannotBeFailedOrReSolved(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('S');
		$originalDamage = (int) $context->reloadUser()['damage'];
		$originalXp = $context->reloadUser()['xp'];

		// Try to fail - should be harmless
		$browser->playWithResult('F');
		$this->assertSame($originalDamage, (int) $context->reloadUser()['damage'],
			'Fail on solved puzzle should not cause damage');

		// Try to solve again - should be harmless
		$browser->playWithResult('S');
		$this->assertSame($originalXp, $context->reloadUser()['xp'],
			'Re-solving should not grant more XP');
	}

	public function testPotionTriggerRestoresHeartsAndShowsAlert(): void
	{
		$maxHealth = Util::getHealthBasedOnLevel(50);
		$context = new ContextPreparator([
			'user' => ['level' => 50, 'damage' => $maxHealth + 200],
			'tsumego' => ['set_order' => 1]]);
		$browser = Browser::instance();
		$browser->get($this->playUrl($context));

		$browser->playWithResult('F');

		// Potion should trigger: hearts restored, alert visible, but the mistake stays
		$this->assertTrue($browser->driver->executeScript('return window.hadMisplay;'),
			'The potion refills the hearts and keeps the mistake');
		$this->assertSame($maxHealth, $browser->driver->executeScript('return window.currentHealth;'),
			'Potion should refill the hearts');
		$this->assertTrue($browser->driver->executeScript(
			'return document.getElementById("potionAlerts").style.display !== "none";'),
			'Potion alert should be visible');
	}

	public function testSolveByClicking()
	{
		foreach ([false, true] as $isGuest)
		{
			$browser = Browser::instance();
			$context = new ContextPreparator([
				'user' => $isGuest ? ['name' => 'testuser'] : ['mode' => Constants::$LEVEL_MODE],
				'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])']]);

			if ($isGuest)
			{
				$this->logout();
				$this->assertFalse(Auth::isLoggedIn(), 'Should not be logged in for guest test');
			}
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$browser->clickBoard(1, 1);
			// Wait for white's auto-response (move number advances to 2)
			$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
			$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			$browser->clickBoard(2, 1);
			$browser->waitUntilJs('window.problemSolved === true');
			$this->assertSame(true, $browser->driver->executeScript('return window.problemSolved;'));
		}
	}

	public function testResetAddsFailWhenSomethingWasPlayed()
	{
		foreach (['', 'no-move', 'already-solved'] as $testCase)
		{
			$context = new ContextPreparator([
				'user' => ['rating' => 1000, 'mode' => Constants::$LEVEL_MODE],
				'tsumego' => [
					'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])',
					'rating' => 1000,
					'set_order' => 1,
					'status' => ($testCase == 'already-solved' ? 'S' : 'V')]]);
			$browser = Browser::instance();
			$browser->setCookie('showInAccountWidget', 'rating');
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

			if ($testCase != 'no-move')
			{
				// click one move
				$browser->clickBoard(1, 1);
				// Wait for white's auto-response (move number advances to 2)
				$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
				if ($testCase != 'already-solved')
					$this->assertSame(false, $browser->driver->executeScript('return window.problemSolved;'));
			}

			// reset without the result being shown
			$browser->clickId('besogo-reset-button');

			$expectedRatingChange = ($testCase == '') ? Rating::calculateRatingChange(1000, 1000, 0, Constants::$PLAYER_RATING_CALCULATION_MODIFIER) : 0;
			$expectedRating = round(1000 + $expectedRatingChange);
			$displayedRating = $browser->driver->executeScript('return window.accountWidget ? accountWidget.rating : null;');
			$this->assertEquals($expectedRating, round($displayedRating));

			// changes are applied after refresh
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
			$this->assertSame(($testCase == '') ? 1 : 0, $context->reloadUser()['damage']);
			$this->assertLessThan(0.1, abs($context->reloadUser()['rating'] - (1000 + $expectedRatingChange)));
		}
	}

	//When user fails a problem, the board should NOT lock.
	//User should be able to continue clicking (though they won't solve it after first failure).
	public function testBoardDoesntLockAfterFailAllowsContinuedAttempts()
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa];W[ab];B[ca]C[+])']]);

		$browser = Browser::instance();
		$tsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get($tsumegoUrl);

		// Wait for board to initialize (window.besogo exists)
		$browser->waitUntilJs('typeof window.besogo !== "undefined"');

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$browser->waitUntilJs('document.getElementById("status").innerHTML.includes("Incorrect")');

		// Verify board shows failure state
		$statusAfterWrong = $browser->driver->executeScript("return document.getElementById('status').innerHTML;");
		$this->assertStringContainsString("Incorrect", $statusAfterWrong, "Should show 'Incorrect' after wrong move");

		// Verify board is NOT locked (boardLockValue should be 0)
		$boardLockValue = $browser->driver->executeScript("return window.boardLockValue;");
		$this->assertEquals(0, $boardLockValue, "Board should NOT be locked after wrong move");

		// Verify user can still click (board stays interactive)
		// We don't expect to solve the puzzle after failure, just verify clicks still work
		$browser->clickBoard(1, 1);

		// Brief wait to ensure click was processed
		$browser->waitUntilJs('document.readyState === "complete"');

		// Verify still on same problem (didn't reset or advance)
		$this->assertStringContainsString($tsumegoUrl, $browser->driver->getCurrentURL(), "Should stay on same problem");

		// refresh on the tsumego and check just one health was removed
		$browser->get($tsumegoUrl);
		$this->assertSame(1, $context->reloadUser()['damage']);
	}

	public function testBoardStatusIsProperlyUpdatedAfterFailResetAndFail()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa];W[ab];B[ca]C[+])']]);

		$tsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$browser->get($tsumegoUrl);

		// Wait for board to initialize (window.besogo exists)
		$browser->waitUntilJs('typeof window.besogo !== "undefined"');

		// Make wrong move (correct is 1,1)
		$browser->clickBoard(2, 1);

		// Wait for status to show "Incorrect"
		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Incorrect")');

		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->waitForSubmitResult();
		$browser->clickId('besogo-reset-button');
		$this->assertStringContainsString("", $browser->find('#status')->getText());
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('document.getElementById("status").innerText.includes("Incorrect")');
		$this->assertStringContainsString("Incorrect", $browser->find('#status')->getText());
		$browser->waitForSubmitResult();
		$browser->get($tsumegoUrl);
		$this->assertSame(2, $context->reloadUser()['damage']); // 2 errors done
	}

	// When user solves a problem, clicking the board should navigate to next problem.
	public function testClickingBoardAfterSuccessNavigatesToNextPuzzle()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])'],
				['set_order' => 2, 'sgf' => '(;GM[1]FF[4]ST[2]SZ[19];B[aa]C[+])']]]);

		$browser = Browser::instance();
		$firstTsumegoUrl = $context->tsumegos[0]['set-connections'][0]['id'];
		$secondTsumegoUrl = $context->tsumegos[1]['set-connections'][0]['id'];
		$browser->get($firstTsumegoUrl);

		// Solve the puzzle by making correct moves (this SGF requires 2 clicks)
		$browser->clickBoard(1, 1); // First move
		// Wait for white's auto-response (move number advances to 2)
		$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(2, 1); // Second move that solves it
		$browser->waitUntilJs('window.problemSolved === true');

		// Verify puzzle is solved
		$problemSolved = $browser->driver->executeScript("return window.problemSolved;");
		$this->assertTrue($problemSolved, "problemSolved should be true");

		// Verify boardLockValue is set
		$boardLockValue = $browser->driver->executeScript("return window.boardLockValue;");
		$this->assertEquals(1, $boardLockValue, "Board should be locked after success");

		// Click on board to navigate to next puzzle (use position near existing stones)
		$browser->clickBoard(1, 2); // Click near the solved area
		// Wait for navigation to next puzzle
		$browser->waitUntilJs('location.href.includes("' . $secondTsumegoUrl . '")');

		// Verify we navigated to the next puzzle
		$currentUrl = $browser->driver->getCurrentURL();
		$this->assertStringContainsString($secondTsumegoUrl, $currentUrl, "Should navigate to next puzzle");
	}

	//When in tags query mode but the lastSet cookie contains a value that
	//isn't a valid tag name, the play page should fall back to topics
	//view instead of crashing.
	public function testPlayPageFallsBackToTopicsWhenLastSetCookieIsNotAValidTag(): void
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumego' => ['sets' => [['name' => 'test set', 'num' => 1]]],
		]);
		$browser = Browser::instance();
		$browser->setCookie('lastSet', 'nonexistent-tag');
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$playTitle = $browser->getElements('#playTitle');
		$this->assertCount(1, $playTitle, 'Play page should render with playTitle element');
	}

	public function testRootCommentIsDisplayedOnInitialLoad()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'tsumego' => [
				'set_order' => 1,
				'sgf' => '(;GM[1]FF[4]SZ[19]C[Go Seigen 4p vs Kubomatsu 6p]AB[cc];B[aa];W[ab];B[ba]C[+])',
			],
		]);
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$theComment = $browser->findById('theComment');
		$this->assertSame('Go Seigen 4p vs Kubomatsu 6p', $theComment->getText());
		$this->assertTrue($theComment->isDisplayed(), 'Root comment should be visible on initial page load');
		//In the row of the messages above the board, at its left like any message of the row (PlayMessageRowBrowserTest)
		$this->assertTrue($browser->driver->executeScript('return document.getElementById("theComment").parentElement.classList.contains("play-message");'), 'The comment is in the row of the messages');
		$this->assertSame('left', $browser->driver->executeScript('return getComputedStyle(document.getElementById("theComment")).textAlign;'), 'The comment is at the left');

		$xpDisplayDiv = $browser->findById('xpDisplayDiv');
		$this->assertTrue($xpDisplayDiv->isDisplayed(), 'xpDisplayDiv must be visible on initial page load');
	}

	public function testHeartsShowTheDamageTaken()
	{
		// damage taken => the hearts the player sees
		foreach ([0 => 'all full', 1 => 'one empty', 10000 => 'all empty'] as $damage => $case)
		{
			$context = new ContextPreparator(['user' => ['damage' => $damage], 'tsumego' => 1]);
			$browser = Browser::instance();
			$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

			$health = Util::getHealthBasedOnLevel(Auth::getUser()['level']);
			$empty = min($damage, $health);
			$this->assertCount($empty, $browser->getElements('#health img.play-player__heart--empty'), $case . ' with ' . $damage . ' damage');
			$this->assertCount($health - $empty, $browser->getElements('#health img:not(.play-player__heart--empty)'), $case . ' with ' . $damage . ' damage');
		}
	}

	public function testFavoritesHeartTogglesAddAndRemove(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

		// Heart is rendered for logged-in users
		$this->assertTrue($browser->idExists('favButton'));

		// First click adds the tsumego to the lazily-created Favorites set.
		// Wait until the client has registered the new Favorites set, which only
		// happens after the add request completes - this also guarantees the next
		// click toggles it back off instead of re-adding (409).
		$browser->clickId('favButton');
		$this->waitForClientFavoritesState($browser);

		$favoritesSet = $this->fetchTable('Set')->find()->where(['user_id' => $context->user['id'], 'title' => 'Favorites'])->first();
		$this->assertNotEmpty($favoritesSet, 'First heart click should create the Favorites set');
		$this->assertSame(1, $this->fetchTable('SetConnection')->find()->where(['set_id' => $favoritesSet->id, 'tsumego_id' => $tsumegoId])->count(), 'First heart click should add the tsumego to Favorites');

		// Second click removes it again
		$browser->clickId('favButton');
		$this->waitForConnectionCount($browser, $favoritesSet->id, $tsumegoId, 0,
			'Second heart click should remove the tsumego from Favorites');
	}

	private function waitForClientFavoritesState(Browser $browser): void
	{
		try
		{
			$browser->waitUntilJs('!!window.userSets && window.userSets.some(s => s.contains)', 5);
		}
		catch (TimeoutException $e)
		{
			$this->fail('First heart click should add the tsumego to Favorites');
		}
	}

	private function waitForConnectionCount(Browser $browser, int $setId, int $tsumegoId, int $expected, string $message): void
	{
		try
		{
			$browser->waitUntil(function () use ($setId, $tsumegoId, $expected) {
				return $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId, 'tsumego_id' => $tsumegoId])->count() === $expected;
			}, 5, $message);
		}
		catch (TimeoutException $e)
		{
			$this->fail($message);
		}
	}

	public function testCommentCoordinatesHaveHoverSpans()
	{		// Create a tsumego with a comment containing coordinates
		// Admin so comments are visible
		$context = new ContextPreparator(['user' => ['admin' => true], 'tsumego' => 1]);

		// Add a comment with coordinates
		$tsumegoCommentTable = $this->fetchTable('TsumegoComment');
		$tsumegoCommentTable->saveOrFail($tsumegoCommentTable->newEntity([
			'tsumego_id' => $context->tsumegos[0]['id'],
			'user_id' => $context->user['id'],
			'message' => 'Try playing at R19 or S18, they both work.',
		]));

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);

		$browser->expandComments();

		// Check that coordinate spans exist in the HTML
		$pageSource = $browser->driver->getPageSource();
		$this->assertTextContains('go-coord', $pageSource);

	}

	public function testFailingWithLastHeartLocksBoardAndShowsLockMessage()
	{
		// Create a tsumego with a comment containing coordinates
		$context = new ContextPreparator([
			'user' => ['premium' => true, 'health' => 0], // 0 hearts left - fail should lock
			'tsumego' => ['set_order' => 1, 'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])']]);
		$context->unlockAchievementsWithoutEffect(); // avoiding premium achievement increasing level and health
		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id']);
		$browser->playWithResult('F');
		$this->assertTextContains("This problem is locked until", $browser->driver->getPageSource());
		$this->assertSame(true, $browser->driver->executeScript("return window.tryAgainTomorrow;"));
		$this->assertSame(1, $browser->driver->executeScript("return window.boardLockValue;"));
		$this->checkPlayNavigationButtons($browser, 1, $context, function ($index) {
			return 0;
		}, function ($index) {
			return 1;
		}, 0, 'F');
	}

	//Browser test: clicking the color-orientation button inverts the board and
	//swaps the multiple-choice answer text so it matches the visual stones.
	public function testOrientationButtonSwapsVariantAnswers(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play. What is the result?',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc]AW[dd];B[aa];W[ab];B[ba]C[+])',
				'variants' => [[
					'tsumego_variant_type_id' => TsumegoVariantTable::TYPE_MULTIPLE_CHOICE,
					'answer1' => 'White is dead',
					'answer2' => 'Ko',
					'answer3' => 'Seki in sente',
					'answer4' => 'Seki in gote',
					'correct_answer' => '3',
				]],
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("besogo-multipleChoice1") !== null');

		$this->assertSame('White is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('Black is dead', $browser->find('#besogo-multipleChoice1')->getAttribute('value'));
		$this->assertSame('White', $browser->find('.play-goal__stone')->getAttribute('aria-label'));
		$this->assertSame('to play. What is the result?', $browser->find('#descriptionText')->getText());
	}

	//Browser test: the orientation button swaps the semeai "Black is dead" /
	//"White is dead" buttons so they match the inverted board.
	public function testOrientationButtonSwapsSemeaiButtons(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'semeai' => [
					'semeai_type_id' => SemeaiTypeTable::TYPE_NO_EYES_FEW_INSIDE_LIBERTIES,
					'minimum_liberties' => 0,
					'maximum_liberties' => 2,
					'liberty_count' => 6,
					'variance' => 2],
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AW[hm][im][gn][jn][go][jo][gp][jp][eq][gq][jq][fr][jr][gs][js]AB[jm][km][in][ln][io][lo][ip][lp];B[aa];W[ab];B[ba]C[+])',
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("besogo-multipleChoice1") !== null');

		$button1 = $browser->find('#besogo-multipleChoice1');
		$button2 = $browser->find('#besogo-multipleChoice2');
		$this->assertSame('Black is dead', $button1->getAttribute('value'));
		$this->assertSame('White is dead', $button2->getAttribute('value'));

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");

		$this->assertSame('White is dead', $button1->getAttribute('value'));
		$this->assertSame('Black is dead', $button2->getAttribute('value'));
	}

	//Browser test: the orientation button swaps the SGF comment text so it matches the inverted board.
	public function testOrientationButtonSwapsSgfComment(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to play.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]C[Black to play. Save the black group.];B[aa];W[ab];B[ba]C[+])',
			],
		]);

		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("theComment") !== null');

		$comment = $browser->find('#theComment');
		$this->assertStringContainsString('Black to play', $comment->getText());

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertStringContainsString('White to play', $comment->getText());
	}

	//Browser test: the hint follows the board's colours, both when the page opens inverted and when the player swaps them.
	public function testHintFollowsColorSwap(): void
	{
		$context = new ContextPreparator([
			'user' => ['player_color_setting' => UserTable::PLAYER_COLOR_SETTING_ORIGINAL],
			'tsumego' => [
				'set_order' => 1,
				'description' => 'Black to kill.',
				'hint' => 'Kill before White can.',
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19];B[aa];W[ab];B[ba]C[+])',
			],
		]);

		$browser = Browser::instance();
		$browser->get($context->tsumegos[0]['set-connections'][0]['id'] . '?playercolor=white');
		$browser->waitUntilJs('typeof besogo !== "undefined" && document.getElementById("colorOrientation") !== null');

		$this->assertSame('White', $browser->find('.play-goal__stone')->getAttribute('aria-label'), 'The color to move is a stone');
		$this->assertSame('to kill.', $browser->find('#descriptionText')->getText());
		$this->assertSame('(Kill before Black can.)', $browser->find('#hintText')->getText());

		$browser->driver->executeScript("document.getElementById('colorOrientation').click();");
		$this->assertSame('Black', $browser->find('.play-goal__stone')->getAttribute('aria-label'));
		$this->assertSame('to kill.', $browser->find('#descriptionText')->getText());
		$this->assertSame('(Kill before White can.)', $browser->find('#hintText')->getText());
	}

	//Keeps the requests that record a solve inside the browser, so the problem that was
	//just played is still unrecorded while the player moves on, the way a slow request does.
	private function holdSolveResultsInTheBrowser(Browser $browser): void
	{
		$browser->driver->executeScript(
			'window.__realFetch = window.__realFetch || window.fetch;'
			. 'window.__solveRequests = [];'
			. 'window.fetch = function (url) {'
			. '  if (String(url).indexOf("/tsumegos/result") !== 0)'
			. '    return window.__realFetch.apply(this, arguments);'
			. '  window.__solveRequests.push(url);'
			. '  return new Promise(function () {});'
			. '};'
		);
	}

	//Plays the current problem without its result ever reaching the server.
	private function solveWhileTheSolveIsStillOnItsWay(Browser $browser): void
	{
		$this->holdSolveResultsInTheBrowser($browser);
		$browser->driver->executeScript("displayResult('S')");
		$browser->waitUntilJs('window.problemSolved === true && window.__solveRequests.length === 1');
		$browser->dismissAchievementPopups();
	}

	//A time mode session the player is on the first problem of, with a result on it when a
	//status is given, so the player is on the problem that has not been recorded yet.
	private function startTimeMode(Browser $browser, ?int $firstAttemptStatus = null, int $problemCount = 2, array $firstAttemptFields = []): ContextPreparator
	{
		$attempts = [array_merge(['order' => 1, 'status' => $firstAttemptStatus ?? TimeModeAttemptStatusTable::QUEUED], $firstAttemptFields)];
		for ($order = 2; $order <= $problemCount; $order++)
			$attempts[] = ['order' => $order, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:' . ($order - 1)];

		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
			'tsumegos' => array_map(fn($order) => ['set_order' => $order, 'sgf' => self::SGF_19], range(2, $problemCount)),
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => $attempts]]]);
		$browser->get('/timeMode/play');
		$this->waitForBoard($browser);
		return $context;
	}

	//Which review button the board shows: active, or inactive while Review is not available
	private function reviewButton(Browser $browser): string
	{
		return (string) $browser->driver->executeScript('return document.getElementById("besogo-review-button") ? "active" : (document.getElementById("besogo-review-button-inactive") ? "inactive" : "none");');
	}

	//In a run of time mode Review isn't available before the problem is answered, and is after it, solved or not,
	//whether the player solved the problem before outside the run or never did
	public function testTimeModeReviewIsAvailableOnceTheProblemIsAnswered(): void
	{
		foreach ([null, 'S'] as $statusBefore)
			foreach (['S', 'F'] as $answer)
			{
				$tsumego = ['set_order' => 1, 'sgf' => self::SGF_19];
				if ($statusBefore !== null)
					$tsumego['status'] = $statusBefore;
				new ContextPreparator([
					'tsumego' => $tsumego,
					'tsumegos' => [['set_order' => 2, 'sgf' => self::SGF_19]],
					'time-mode-ranks' => ['5k'],
					'time-mode-sessions' => [[
						'category' => TimeModeCategoryTable::BLITZ,
						'rank' => '5k',
						'status' => TimeModeSessionStatusTable::IN_PROGRESS,
						'attempts' => [
							['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED],
							['order' => 2, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:1']]]]]);
				$browser = Browser::instance();
				$browser->get('/timeMode/play');
				$this->waitForBoard($browser);
				$case = 'solved before: ' . ($statusBefore ?? 'no') . ', answered ' . $answer;
				$this->assertSame('inactive', $this->reviewButton($browser), $case . ', before the answer');
				$browser->driver->executeScript("displayResult('" . $answer . "')");
				$browser->waitForSubmitResult();
				$this->assertSame('active', $this->reviewButton($browser), $case . ', after the answer');
			}
	}

	//A problem made by the player is no exception in a run
	public function testTimeModeReviewOfTheirOwnProblemWaitsForTheAnswer(): void
	{
		$browser = Browser::instance();
		$context = $this->startTimeMode($browser);
		Util::execute('UPDATE tsumego SET author_user_id = ?', [$context->user['id']]);
		$browser->get('/timeMode/play');
		$this->waitForBoard($browser);
		$this->assertSame('inactive', $this->reviewButton($browser));
	}

	//The page submits a timeout when its clock runs out.
	public function testTimeModeTimerSubmitsATimeoutWhenTheClockRunsOut(): void
	{
		$browser = Browser::instance();
		$blitzSeconds = $this->fetchTable('TimeModeCategory')->get(TimeModeCategoryTable::BLITZ)->seconds;
		// the attempt has been running for all but the last seconds of Blitz
		$context = $this->startTimeMode($browser, null, 2, ['started-seconds-ago' => $blitzSeconds - 3]);

		// watch the request the page makes when its clock runs out
		$browser->driver->executeScript('window._sent = []; var orig = window.fetch; window.fetch = function (url) { if (String(url).indexOf("/tsumegos/result") !== -1) window._sent.push(JSON.parse(arguments[1].body)); return orig.apply(window, arguments); };');
		$browser->waitUntilJs('window._sent.length > 0');
		// the server stores the timeout when it answers, not when the page sends it
		$browser->waitForSubmitResult();

		$this->assertSame('timed_out', $browser->driver->executeScript('return window._sent[0].outcome;'));
		$this->assertSame("Time's up!", trim($browser->findById('status')->getText()));
		$this->assertSame('active', $this->reviewButton($browser), 'a problem out of time can be reviewed');
		$this->assertSame(
			TimeModeAttemptStatusTable::TIMEOUT,
			(int) $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first()->time_mode_attempt_status_id);
	}

	public function testTimeModeCountsTheProblemBeingSolvedWhileTheSolveIsStillOnItsWay(): void
	{
		$browser = Browser::instance();
		$this->startTimeMode($browser);

		$this->solveWhileTheSolveIsStillOnItsWay($browser);
		$started = microtime(true);
		$browser->clickBoard(1, 1);

		$browser->waitUntilJs('location.pathname === "/timeMode/play/2"');
		$this->assertLessThan(5, microtime(true) - $started,
			'The next problem must open without waiting for the solve');

		$this->waitForBoard($browser);
		$title = $browser->driver->executeScript('return document.getElementById("playTitle").textContent;');
		$this->assertStringContainsString('2 of 2', $title,
			'The position must count the problem being solved, not the recorded ones');
	}

	//The bonus seconds of the moves played must not eat the time the player spent before
	//their first move, otherwise a quick solve would always score the maximum.
	public function testTheTimeBeforeTheFirstMoveIsNotEatenByTheBonusSeconds(): void
	{
		$browser = Browser::instance();
		$context = $this->startTimeMode($browser);

		// a move can be played before the player does anything, which is what the bonus is for
		$browser->driver->executeScript('addTimeForMovePlayed();');

		$browser->waitUntilJs('elapsedSeconds() >= 2');
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');

		$browser->driver->executeScript('addTimeForMovePlayed(); addTimeForMovePlayed();');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.problemSolved === true');
		$browser->waitForSubmitResult();

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first();
		$this->assertGreaterThanOrEqual(2, (float) $attempt->seconds,
			'A solve is worth at least the time it took to play the first move');
		$this->assertLessThan(99, (float) $attempt->points,
			'The bonus seconds cannot buy the maximum score');
	}

	//The measured time comes from the clock, not from the timer that draws the countdown, so a
	//tab in the background that hardly ever ticks cannot slow the game down.
	public function testTheRecordedTimeDoesNotDependOnTheTimer(): void
	{
		$browser = Browser::instance();
		$context = $this->startTimeMode($browser);

		// A throttled tab gets far fewer ticks, here none at all
		$browser->driver->executeScript('clearInterval(timeModeTimer.timeModeTimer);');
		$browser->waitUntilJs('elapsedSeconds() >= 2');
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('window.besogo && besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.problemSolved === true');
		$browser->waitForSubmitResult();

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first();
		$this->assertGreaterThanOrEqual(2, (float) $attempt->seconds,
			'The two seconds without a single tick are part of the recorded time');
	}

	public function testTimeModeResultPageWaitsForTheLastSolve(): void
	{
		$browser = Browser::instance();
		$context = $this->startTimeMode($browser, TimeModeAttemptStatusTable::SOLVED);
		$sessionID = $context->timeModeSessions[0]->id;

		// only the last problem is left and it has not been recorded yet
		$browser->get('/timeMode/result/' . $sessionID);
		$browser->waitUntilJs('document.body.textContent.indexOf("Finishing your session") !== -1');

		// the result of the last problem arrives
		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => 2])->first();
		$attempt->time_mode_attempt_status_id = TimeModeAttemptStatusTable::SOLVED;
		$attempt->seconds = '10';
		$attempt->points = '50';
		$this->fetchTable('TimeModeAttempt')->saveOrFail($attempt);

		// the page asks again on its own
		$browser->waitUntilJs('document.body.textContent.indexOf("Time Mode Results") !== -1');
		$this->assertStringContainsString('passed(2/2)', $browser->pageText(),
			'The result appears once the last result has been recorded');
	}

	//The result of the last problem never arrives, so the page that waits for it
	//keeps asking. The waiting has to end and the session has to be scored.
	public function testASessionIsScoredWhenTheLastResultNeverArrives(): void
	{
		$categorySeconds = TimeModeUtil::CATEGORY_SLOW_SPEED_SECONDS;
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
			'tsumegos' => [['set_order' => 2, 'sgf' => self::SGF_19]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::SLOW,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeAttemptStatusTable::SOLVED],
					// the last problem was left behind and its result never arrived
					['order' => 2, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:1',
						'started-seconds-ago' => $categorySeconds + 1]]]]]);
		$browser = Browser::instance();

		// past the last problem, so there is nothing to play, only the result to wait for
		$browser->driver->executeScript('sessionStorage.removeItem("timeModeWaitStarted");');
		$browser->get('/timeMode/play/3');
		$browser->waitUntilJs('document.body && document.body.textContent.indexOf("Finishing your session") !== -1');

		// the player has been waiting almost the whole limit already
		$browser->driver->executeScript('sessionStorage.setItem("timeModeWaitStarted", Date.now() - '
			. (TimeModeUtil::WAITING_FOR_RESULT_SECONDS * 1000 - 500) . ');');
		$browser->get('/timeMode/play/3');

		$browser->waitUntilJs('document.body && document.body.textContent.indexOf("Time Mode Results") !== -1');
		$this->assertStringContainsString('failed(1/2)', $browser->pageText(),
			'The session is scored instead of waiting for a result that is not coming');
	}

	public function testTheNextProblemOpensWhileTheSolveIsStillOnItsWay(): void
	{
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
			'tsumegos' => [['set_order' => 2, 'sgf' => self::SGF_19]]]);
		$browser = Browser::instance();
		$browser->get((string) $context->tsumegos[0]['set-connections'][0]['id']);
		$this->waitForBoard($browser);

		$this->solveWhileTheSolveIsStillOnItsWay($browser);
		$started = microtime(true);
		$browser->clickBoard(1, 1);

		$browser->waitUntilJs('location.pathname === "/' . $context->tsumegos[1]['set-connections'][0]['id'] . '"');
		$this->assertLessThan(5, microtime(true) - $started,
			'Following a problem link must not be held up by the solve request');
	}

	//A player plays a whole session the way the pages present it: solves a problem,
	//misplays one, gives up on one, leaves one behind until its time is up, and reads
	//the score of all of them.
	public function testPlayingAWholeTimeModeSessionRecordsEveryOutcome(): void
	{
		$categorySeconds = TimeModeUtil::CATEGORY_SLOW_SPEED_SECONDS;
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
			'tsumegos' => [
				['set_order' => 2, 'sgf' => self::SGF_19],
				['set_order' => 3, 'sgf' => self::SGF_19],
				['set_order' => 4, 'sgf' => self::SGF_19]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::SLOW,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED],
					['order' => 2, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:1'],
					['order' => 3, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:2'],
					// the last problem was presented and left behind, so its time ran out
					['order' => 4, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:3',
						'started-seconds-ago' => $categorySeconds + 1]]]]]);
		$sessionID = $context->timeModeSessions[0]->id;
		$browser = Browser::instance();

		$playTitle = fn() => (string) $browser->driver->executeScript('return document.getElementById("playTitle").textContent;');
		$status = fn() => (string) $browser->driver->executeScript('return document.getElementById("status").textContent;');
		$attempt = fn(int $order) => $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => $order])->first();

		$browser->get('/timeMode/play');
		$this->waitForBoard($browser);
		$this->assertStringContainsString('1 of 4', $playTitle());

		// the first problem: black plays the two moves of the solution
		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.problemSolved === true');
		$browser->waitForSubmitResult();
		$browser->dismissAchievementPopups();
		$this->assertStringContainsString('Correct', $status());
		$this->assertSame(TimeModeAttemptStatusTable::SOLVED, (int) $attempt(1)->time_mode_attempt_status_id);
		$this->assertGreaterThan(0, (int) $attempt(1)->points, 'A solved problem scores');

		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "/timeMode/play/2"');
		$this->waitForBoard($browser);
		$this->assertStringContainsString('2 of 4', $playTitle());

		// the second problem: a move that is not the solution ends it
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.indexOf("Incorrect") !== -1');
		$browser->waitForSubmitResult();
		$this->assertSame(TimeModeAttemptStatusTable::FAILED, (int) $attempt(2)->time_mode_attempt_status_id);
		$this->assertSame(0, (int) $attempt(2)->points, 'A misplayed problem scores nothing');

		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "/timeMode/play/3"');
		$this->waitForBoard($browser);
		$this->assertStringContainsString('3 of 4', $playTitle());

		// the third problem is given up on, which brings up the last one that ran out of time
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "/timeMode/result/' . $sessionID . '"');
		$this->assertSame(TimeModeAttemptStatusTable::SKIPPED, (int) $attempt(3)->time_mode_attempt_status_id);
		$this->assertSame(0, (int) $attempt(3)->points, 'A problem given up on scores nothing');
		$this->assertSame(TimeModeAttemptStatusTable::TIMEOUT, (int) $attempt(4)->time_mode_attempt_status_id);
		$this->assertSame($categorySeconds, (int) $attempt(4)->seconds,
			'A problem left behind is recorded with the time it was given');
		$this->assertSame(0, (int) $attempt(4)->points, 'A problem that ran out of time scores nothing');

		foreach ([1, 2, 3, 4] as $order)
		{
			$this->assertGreaterThanOrEqual(0, (int) $attempt($order)['seconds'],
				'The recorded time of problem ' . $order . ' is never negative');
			$this->assertLessThanOrEqual($categorySeconds, (int) $attempt($order)['seconds'],
				'The recorded time of problem ' . $order . ' never exceeds the time the problem is given');
		}

		// one solved of four problems is not enough to pass, and the score is out of four
		$score = $browser->pageText();
		$this->assertStringContainsString('failed(1/4)', $score,
			'The score counts the problems the session had, not a full session');
		foreach (['solved', 'failed', 'skipped', 'timeout'] as $outcome)
			$this->assertStringContainsString($outcome, $score, 'The score tells how problem ' . $outcome);
		$this->assertStringNotContainsString('You unlocked', $score,
			'A session that did not pass unlocks nothing');

		// the session is closed with the outcome and the score the page showed
		$session = $this->fetchTable('TimeModeSession')->get($sessionID);
		$this->assertSame(TimeModeSessionStatusTable::FAILED, $session->time_mode_session_status_id);
		$this->assertSame((int) $attempt(1)->points, (int) $session->points,
			'The score of the session is what its problems were worth');
	}

	//A player who sits on a problem without playing it: its countdown runs out while
	//the page is open, and the problem is given up on by itself.
	public function testAProblemWhoseCountdownRunsOutIsGivenUpOnByItself(): void
	{
		$categorySeconds = TimeModeUtil::CATEGORY_SLOW_SPEED_SECONDS;
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF_19],
			'tsumegos' => [['set_order' => 2, 'sgf' => self::SGF_19]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::SLOW,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					['order' => 1, 'status' => TimeModeAttemptStatusTable::QUEUED],
					['order' => 2, 'status' => TimeModeAttemptStatusTable::QUEUED, 'tsumego_id' => 'other:1']]]]]);
		$sessionID = $context->timeModeSessions[0]->id;
		$browser = Browser::instance();

		// the player comes to the problem with only a moment of its time left: the clock is
		// handed back here, because getting the browser up takes longer than that moment
		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => 1])->first();
		$attempt->started = new DateTimeImmutable(date('Y-m-d H:i:s', time() - ($categorySeconds - 5)));
		$this->fetchTable('TimeModeAttempt')->saveOrFail($attempt);

		$browser->get('/timeMode/play');
		$this->waitForBoard($browser);
		$tcount = (float) $browser->driver->executeScript('return timeModeTimer.remaining;');
		$this->assertLessThan(30, $tcount, 'The problem is served with the little time it has left');

		// the player watches the countdown go to zero
		$browser->waitUntilJs('document.getElementById("status").textContent.indexOf("Time\'s up!") !== -1', 15);
		$browser->waitForSubmitResult();

		$attempt = $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $sessionID, 'order' => 1])->first();
		$this->assertSame(TimeModeAttemptStatusTable::TIMEOUT, $attempt->time_mode_attempt_status_id);
		$this->assertSame($categorySeconds, (int) $attempt->seconds,
			'Running out of time is recorded with the time the problem was given');
		$this->assertSame(0, (int) $attempt->points);

		// and the player moves on to the next problem
		$browser->clickId('besogo-next-button');
		$browser->waitUntilJs('location.pathname === "/timeMode/play/2"');
		$this->waitForBoard($browser);
	}

	public function testOpenIsTheFirstLinkOfMore(): void
	{
		$links = 'return Array.from(document.querySelectorAll("#editSection > p > a")).map(link => link.textContent.trim());';
		foreach ([
			[['admin' => true], ['Open', 'Find Similar Problems', 'History', 'Rating', 'Request Solution']],
			[['admin' => false, 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE], ['Make Proposal', 'Find Similar Problems']]] as [$user, $expected])
		{
			$context = new ContextPreparator(['user' => $user, 'tsumego' => ['set_order' => 1, 'status' => 'S', 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc];B[aa]C[+])']]);
			$browser = Browser::instance();
			$browser->get('/' . $context->setConnections[0]['id']);
			$browser->openPlayEditSection();
			$this->assertSame($expected, $browser->driver->executeScript($links));
		}
	}
}
