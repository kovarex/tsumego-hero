<?php

use App\Utility\Auth;
use App\Utility\Constants;
use Facebook\WebDriver\WebDriverDimension;

//The page of the challenges: the ones waiting for the player first, each with its way in, then the form to start
//one, folded behind its button, then the list.
#[RetryOnBrowserFailure]
class ChallengesPageLayoutTest extends IntegrationTestCase
{
	private ContextPreparator $context;

	//'challenger' starts the challenges, 'friend' is invited to them; twelve problems of the one rank
	private function prepare(): void
	{
		Auth::logout();
		$this->context = new ContextPreparator([
			'user' => ['name' => 'challenger', 'mode' => Constants::$LEVEL_MODE],
			'other-users' => [['name' => 'friend']],
			'time-mode-ranks' => ['5k'],
			'tsumegos' => range(1, 12),
		]);
		$this->login('challenger');
	}

	//A challenge of the signed-in player with the friend invited
	private function challengeForTheFriend(): int
	{
		$rankId = (int) $this->context->timeModeRanks[0]['id'];
		$this->post('/challenges/create', [
			'minimum_rank' => $rankId,
			'maximum_rank' => $rankId,
			'seconds_per_problem' => 60,
			'problem_count' => 10,
			'players' => 'DN_friend']);
		return (int) $this->fetchTable('Challenge')->find()->orderByDesc('id')->first()->id;
	}

	public function testTheFormIsFoldedUnlessAPlayerIsPickedOrATryFailed(): void
	{
		$this->prepare();
		$this->get('/challenges');
		$this->assertTextContains('<details class="challenges__create" id="challenges-create">', $this->view);

		$this->get('/challenges?opponent=' . ContextPreparator::getUserIdFromName('friend'));
		$this->assertTextContains('<details class="challenges__create" id="challenges-create" open>', $this->view);
		$this->assertTextContains('id="challenge-players" name="players" value="DN_friend"', $this->view);

		// a try that failed comes back with ?new=1
		$this->get('/challenges?new=1');
		$this->assertTextContains('<details class="challenges__create" id="challenges-create" open>', $this->view);
	}

	public function testTheWaitingChallengesComeFirstWithTheirWayIn(): void
	{
		$this->prepare();
		$this->get('/challenges');
		$this->assertTextNotContains('id="challenges-waiting"', $this->view, 'nothing waits yet');
		$this->assertTextNotContains('<h1', $this->view, 'no page title, what waits comes first');

		$challengeId = $this->challengeForTheFriend();
		$this->login('friend');
		$this->get('/challenges');
		$page = $this->view;
		$waiting = strpos($page, 'id="challenges-waiting"');
		$this->assertNotFalse($waiting);
		$this->assertLessThan(strpos($page, 'class="hint challenges__intro"'), $waiting);
		$this->assertLessThan(strpos($page, 'id="challenges-create"'), $waiting);
		$this->assertLessThan(strpos($page, 'id="challenges-list"'), $waiting);
		$this->assertTextContains('Waiting for you (1)', $page);
		$this->assertTextContains('<a class="btn btn--success" href="/challenges/' . $challengeId . '" id="challenge-open-' . $challengeId . '">Open challenge</a>', $page);
		$this->assertTextContains('action="/challenges/decline/' . $challengeId . '"', $page);

		// a declined challenge waits no more, and with none the block isn't there
		$this->post('/challenges/decline/' . $challengeId);
		$this->get('/challenges');
		$this->assertTextNotContains('id="challenges-waiting"', $this->view);
	}

	public function testTheFormOpensAndTheWayInLeadsToTheChallengeInTheBrowser(): void
	{
		$this->prepare();
		$challengeId = $this->challengeForTheFriend();
		$this->login('friend');
		$browser = Browser::instance();
		$browser->get('challenges');

		$this->assertFalse($browser->find('#challenge-name')->isDisplayed(), 'the form is folded');
		$browser->clickId('challenges-new');
		$browser->waitUntilCssSelectorDisplayed('#challenge-name');
		$this->assertSame('close', strtolower($browser->find('#challenges-new')->getText()), 'one label at a time');
		$browser->clickId('challenges-new');
		$this->assertFalse($browser->find('#challenge-name')->isDisplayed(), 'and folds again');

		$tops = $browser->driver->executeScript(<<<'JS'
			const top = selector => document.querySelector(selector).getBoundingClientRect().top;
			return [top('#challenges-waiting'), top('#challenges-new'), top('#challenges-list')];
			JS);
		$this->assertLessThan($tops[1], $tops[0], 'the waiting challenges come before the button');
		$this->assertLessThan($tops[2], $tops[1]);

		$browser->clickId('challenge-open-' . $challengeId);
		$browser->waitUntilCssSelectorDisplayed('#challenge-play');
		$this->assertStringEndsWith('/challenges/' . $challengeId, $browser->driver->getCurrentURL());
		$browser->assertNoJsErrors();
	}

	public function testThePageFitsANarrowPhone(): void
	{
		$this->prepare();
		$this->challengeForTheFriend();
		$this->login('friend');
		$browser = Browser::instance();
		$window = $browser->driver->manage()->window();
		$originalSize = $window->getSize();
		try
		{
			$window->setSize(new WebDriverDimension(320, 700));
			$browser->get('challenges?new=1');
			$layout = $browser->driver->executeScript(<<<'JS'
				const width = document.documentElement.clientWidth;
				const parts = [...document.querySelectorAll('#challenges-waiting *, #challenges-create *')];
				return {
					overflow: document.documentElement.scrollWidth - width,
					outside: parts.filter(part => part.getBoundingClientRect().right > width + 0.5).length,
				};
				JS);
			$this->assertEquals(['outside' => 0, 'overflow' => 0], $layout);
			$browser->assertNoJsErrors();
		}
		finally
		{
			$window->setSize($originalSize);
		}
	}
}
