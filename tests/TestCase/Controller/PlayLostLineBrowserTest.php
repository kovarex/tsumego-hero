<?php

use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\Constants;
use App\Utility\TimeModeUtil;

//A line that can no longer end correct is sent as a fail the moment it is lost, so leaving
//or refreshing before it is played out doesn't escape the fail (#112). The player is shown
//nothing of it until the line is over.
#[RetryOnBrowserFailure]
class PlayLostLineBrowserTest extends IntegrationTestCase
{
	//Black (1,1), white (1,2), black (2,1) is correct. Black (2,1) is lost at once, but the
	//line goes on: white answers (1,1) and only black (3,1) ends it.
	private const SGF = '(;GM[1]FF[4]ST[2]SZ[19](;B[aa];W[ab];B[ba]C[+])(;B[ba];W[aa];B[ca]))';

	private function prepare(Browser $browser, array $user = []): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => array_merge(['rating' => 1000, 'mode' => Constants::$LEVEL_MODE], $user),
			'tsumego' => ['rating' => 1000, 'set_order' => 1, 'sgf' => self::SGF]]);
		$browser->setCookie('showInAccountWidget', 'rating');
		$browser->get($this->playUrl($context));
		$this->startWatching($browser);
		return $context;
	}

	private function playUrl(ContextPreparator $context): string
	{
		return '/' . $context->tsumegos[0]['set-connections'][0]['id'];
	}

	//Records the results sent and the account bar animations, which are what the player sees of a result
	private function startWatching(Browser $browser): void
	{
		$browser->waitUntilJs('typeof besogo !== "undefined" && besogo.editor && typeof besogo.editor.getCurrent === "function"');
		$browser->driver->executeScript(<<<'JS'
			window.__sentOutcomes = [];
			var originalFetch = window.fetch;
			window.fetch = function (url, init) {
				if (String(url).indexOf('/tsumegos/result') !== -1)
					window.__sentOutcomes.push(JSON.parse(init.body).outcome);
				return originalFetch.apply(window, arguments);
			};
			window.__animations = [];
			if (window.accountWidget) {
				var originalAnimate = accountWidget.animate.bind(accountWidget);
				accountWidget.animate = function (increase) {
					window.__animations.push(increase);
					return originalAnimate(increase);
				};
			}
			JS);
	}

	private function sentOutcomes(Browser $browser): array
	{
		return $browser->driver->executeScript('return window.__sentOutcomes;');
	}

	private function animations(Browser $browser): array
	{
		return $browser->driver->executeScript('return window.__animations;');
	}

	private function emptyHearts(Browser $browser): int
	{
		return count($browser->getElements('img[title="Empty Heart"]'));
	}

	private function shownRating(Browser $browser): float
	{
		return (float) $browser->driver->executeScript('return accountWidget.rating;');
	}

	private function statusText(Browser $browser): string
	{
		return trim($browser->driver->executeScript('return document.getElementById("status").textContent;'));
	}

	//Plays the losing move and waits until its fail is recorded and white answered
	private function enterTheLostLine(Browser $browser): void
	{
		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.__sentOutcomes.length > 0');
		$browser->waitForSubmitResult();
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');
	}

	//The request outlives the page that sent it, so the server may record it a moment after the page is gone
	private function waitForDamage(ContextPreparator $context, int $damage): void
	{
		$deadline = microtime(true) + 10;
		while ((int) $context->reloadUser()['damage'] !== $damage && microtime(true) < $deadline)
			usleep(100 * 1000);
		$this->assertSame($damage, (int) $context->reloadUser()['damage']);
	}

	public function testALostLineIsRecordedAtOnceButShownOnlyWhenItEnds(): void
	{
		$browser = Browser::instance();
		$context = $this->prepare($browser);
		$ratingBefore = $this->shownRating($browser);
		$this->assertSame(0, $this->emptyHearts($browser));

		$this->enterTheLostLine($browser);

		$this->assertSame(['failed'], $this->sentOutcomes($browser), 'The fail is sent the moment the line is lost');
		$this->assertSame(1, (int) $context->reloadUser()['damage']);
		$recordedRating = (float) $context->reloadUser()['rating'];
		$this->assertLessThan(1000, $recordedRating);

		$this->assertSame('', $this->statusText($browser), 'Nothing tells the player the line is lost before it is over');
		$this->assertSame(0, $this->emptyHearts($browser), 'The heart is still shown full');
		$this->assertSame([], $this->animations($browser), 'The account bar doesn\'t move');
		$this->assertSame($ratingBefore, $this->shownRating($browser), 'The account bar still shows the old rating');

		$browser->clickBoard(3, 1);
		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Incorrect")');
		$browser->waitForSubmitResult();

		$this->assertSame(['failed'], $this->sentOutcomes($browser), 'The end of the line doesn\'t send the fail again');
		$this->assertSame(1, $this->emptyHearts($browser), 'The heart is shown lost once the line is over');
		$this->assertSame([false], $this->animations($browser), 'The account bar shows the fail once the line is over');
		$this->assertEqualsWithDelta($recordedRating, $this->shownRating($browser), 0.01, 'The account bar shows the recorded rating');
		$this->assertSame(1, (int) $context->reloadUser()['damage'], 'The fail costs one heart');
	}

	public function testACorrectLineSendsNothingUntilItIsSolved(): void
	{
		$browser = Browser::instance();
		$context = $this->prepare($browser);
		$ratingBefore = $this->shownRating($browser);

		$browser->clickBoard(1, 1);
		$browser->waitUntilJs('besogo.editor.getCurrent().moveNumber >= 2');

		$this->assertSame([], $this->sentOutcomes($browser), 'A line that can still end correct sends nothing');
		$this->assertSame([], $this->animations($browser));
		$this->assertSame(0, (int) $context->reloadUser()['damage']);

		$browser->clickBoard(2, 1);
		$browser->waitUntilJs('window.problemSolved === true');
		$browser->waitForSubmitResult();

		$this->assertSame(['solved_clean'], $this->sentOutcomes($browser));
		$this->assertStringContainsString('Correct!', $this->statusText($browser));
		$this->assertSame([true], $this->animations($browser), 'The account bar shows the solve');
		$this->assertGreaterThan($ratingBefore, $this->shownRating($browser));
		$this->assertEqualsWithDelta((float) $context->reloadUser()['rating'], $this->shownRating($browser), 0.01);
		$this->assertSame(0, $this->emptyHearts($browser));
		$this->assertSame(0, (int) $context->reloadUser()['damage']);
	}

	public function testLeavingOrRefreshingALostLineDoesNotEscapeTheFail(): void
	{
		foreach (['leave', 'refresh'] as $case)
		{
			$browser = Browser::instance();
			$context = $this->prepare($browser);

			// the player goes away right after the losing move, before white even answers
			$browser->clickBoard(2, 1);
			$browser->get($case == 'leave' ? '/sets' : $this->playUrl($context));

			$this->waitForDamage($context, 1);
			$this->assertLessThan(1000, (float) $context->reloadUser()['rating'], $case);

			// the problem opens fresh, and the fail already paid isn't charged again
			$browser->get($this->playUrl($context));
			$this->startWatching($browser);
			$this->assertSame(0, (int) $browser->driver->executeScript('return besogo.editor.getCurrent().moveNumber;'), $case);
			$this->assertSame(1, $this->emptyHearts($browser), $case);
			$this->assertSame(1, (int) $context->reloadUser()['damage'], $case);
		}
	}

	public function testLeavingALostLineInRatingModeStillCostsTheRating(): void
	{
		$context = new ContextPreparator([
			'user' => ['rating' => 1000, 'mode' => Constants::$RATING_MODE],
			'tsumego' => ['rating' => 1000, 'sgf' => self::SGF, 'sets' => [['name' => 'set 1', 'num' => '1']]]]);
		$browser = Browser::instance();
		$browser->get('/ratingMode');
		$this->startWatching($browser);

		$browser->clickBoard(2, 1);
		$browser->get('/ratingMode');

		$deadline = microtime(true) + 10;
		while ((float) $context->reloadUser()['rating'] >= 1000 && microtime(true) < $deadline)
			usleep(100 * 1000);
		$this->assertLessThan(1000, (float) $context->reloadUser()['rating'], 'Refreshing the rating mode doesn\'t escape the fail');
	}

	public function testResetInALostLineShowsTheFailWithoutChargingItAgain(): void
	{
		$browser = Browser::instance();
		$context = $this->prepare($browser);

		$this->enterTheLostLine($browser);
		$this->assertSame(0, $this->emptyHearts($browser));

		$browser->clickId('besogo-reset-button');
		$browser->waitForSubmitResult();

		$this->assertSame(['failed'], $this->sentOutcomes($browser), 'The reset sends nothing more');
		$this->assertSame(1, $this->emptyHearts($browser), 'Giving the line up shows the heart it cost');
		$this->assertSame([false], $this->animations($browser), 'Giving the line up shows the fail on the account bar');
		$this->assertEqualsWithDelta((float) $context->reloadUser()['rating'], $this->shownRating($browser), 0.01);
		$this->assertSame(1, (int) $context->reloadUser()['damage']);
		$this->assertFalse($browser->driver->executeScript('return window.failAlreadyReported;'),
			'The next attempt can be failed again');
	}

	public function testTheClockRunningOutInALostLineDoesNotSendATimeout(): void
	{
		$blitzSeconds = $this->fetchTable('TimeModeCategory')->get(TimeModeCategoryTable::BLITZ)->seconds;
		$context = new ContextPreparator([
			'tsumego' => ['set_order' => 1, 'sgf' => self::SGF],
			'tsumegos' => [['set_order' => 2, 'sgf' => self::SGF]],
			'time-mode-ranks' => ['5k'],
			'time-mode-sessions' => [[
				'category' => TimeModeCategoryTable::BLITZ,
				'rank' => '5k',
				'status' => TimeModeSessionStatusTable::IN_PROGRESS,
				'attempts' => [
					// the attempt has been running for all but the last seconds of Blitz
					['order' => 1, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'started-seconds-ago' => $blitzSeconds - 6],
					['order' => 2, 'status' => TimeModeUtil::$ATTEMPT_RESULT_QUEUED, 'tsumego_id' => 'other:1']]]]]);
		$browser = Browser::instance();
		$browser->get('/timeMode/play');
		$this->startWatching($browser);

		$this->enterTheLostLine($browser);
		$this->assertSame('', $this->statusText($browser));

		$browser->waitUntilJs('document.getElementById("status").textContent.includes("Time\'s up!")');
		$this->assertSame(['failed'], $this->sentOutcomes($browser), 'The lost line was already sent, the clock adds nothing');
		$this->assertSame(
			TimeModeUtil::$ATTEMPT_RESULT_FAILED,
			(int) $this->fetchTable('TimeModeAttempt')->find()->where(['time_mode_session_id' => $context->timeModeSessions[0]->id, 'order' => 1])->first()->time_mode_attempt_status_id);
	}
}
