<?php

use App\Model\Entity\Achievement;
use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementChecker;
use App\Utility\AchievementId;
use App\Utility\Auth;
use App\Utility\PlayOutcome;

//A card earned in a solve comes with the answer to the solve, which a player clicking Next right away never gets.
//The card waits for the player until the browser says it was shown (/achievements/seen), so the next page shows it,
//and no page shows a card again once it was.
class AchievementPopupTest extends AchievementTestCase
{
	public function testACardOfASolveWhoseAnswerWasLostShowsOnTheNextPageOnce(): void
	{
		$context = new ContextPreparator(['user' => ['solved' => 999], 'tsumego' => 1]);
		$_COOKIE['disable-achievements'] = false;

		// the answer carries the card, but the player left before it came
		$this->solve($context);

		$this->get('/sites/blank');
		$cards = $this->cardsOf(AchievementId::PROBLEMS_1000);
		$this->assertCount(1, $cards, 'The next page shows the card the lost answer carried');
		$this->assertSame('1000 Problems', $cards[0]->name);
		$this->assertTextContains('"id":' . AchievementId::PROBLEMS_1000->value, $this->view, 'The layout renders the card');

		// the page showed it and says so
		$this->post('/achievements/seen', ['ids' => [$cards[0]->popup_id]]);
		$this->assertResponseOk();

		$this->get('/sites/blank');
		$this->assertSame([], $this->cardsOf(AchievementId::PROBLEMS_1000), 'A card shown once is not shown again');
	}

	//The card the answer did bring is reported by the page that showed it, so the next page doesn't show it again
	public function testACardShownFromTheAnswerIsNotShownAgain(): void
	{
		$context = new ContextPreparator(['user' => ['solved' => 999], 'tsumego' => 1]);
		$_COOKIE['disable-achievements'] = false;

		$answer = $this->solve($context);
		$popupIDs = array_column(array_filter($answer['achievement_updates'] ?? [],
			fn(array $card) => $card['id'] === AchievementId::PROBLEMS_1000->value), 'popup_id');
		$this->assertCount(1, $popupIDs, 'The answer carries the card with the row it waits in');

		$this->post('/achievements/seen', ['ids' => $popupIDs]);
		$this->get('/sites/blank');
		$this->assertSame([], $this->cardsOf(AchievementId::PROBLEMS_1000));
	}

	//A repeat card (Superior Accuracy ×2) waits with its count and without XP, as the solve showed it
	public function testARepeatCardWaitsWithItsCount(): void
	{
		$context = new ContextPreparator();
		$_COOKIE['disable-achievements'] = false;

		$firstSet = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($firstSet)->finalize();
		$this->get('/sites/blank');
		$first = $this->cardsOf(AchievementId::SUPERIOR_ACCURACY);
		$this->assertCount(1, $first);
		$this->assertSame('Superior Accuracy', $first[0]->name);
		$this->post('/achievements/seen', ['ids' => [$first[0]->popup_id]]);

		// the solve finishing the second collection, its answer lost
		$secondSet = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($secondSet)->finalize();

		$this->get('/sites/blank');
		$repeat = $this->cardsOf(AchievementId::SUPERIOR_ACCURACY);
		$this->assertCount(1, $repeat, 'The repeat card waits for the next page');
		$this->assertSame('Superior Accuracy ×2', $repeat[0]->name, 'It keeps the count it was earned with');
		$this->assertSame(0, $repeat[0]->xp, 'A repeat shows no XP');
		$this->assertTextContains('"name":"Superior Accuracy ×2"', $this->view);

		$this->post('/achievements/seen', ['ids' => [$repeat[0]->popup_id]]);
		$this->get('/sites/blank');
		$this->assertSame([], $this->cardsOf(AchievementId::SUPERIOR_ACCURACY), 'A repeat card shown once is not shown again');
	}

	//A card the checks of the page itself queue shows once on it, not once from the check and once as waiting
	public function testACardOfThePageItselfShowsOnce(): void
	{
		new ContextPreparator(['user' => ['solved' => 1000]]);
		$_COOKIE['disable-achievements'] = false;

		$this->get('/sites/blank');
		$this->assertCount(1, $this->cardsOf(AchievementId::PROBLEMS_1000));
	}

	//A player can only report their own cards
	public function testCardsOfAnotherPlayerCantBeReportedSeen(): void
	{
		$context = new ContextPreparator(['user' => ['solved' => 1000], 'other-users' => [['name' => 'other']]]);
		$_COOKIE['disable-achievements'] = false;
		$this->get('/sites/blank');
		$cards = $this->cardsOf(AchievementId::PROBLEMS_1000);
		$this->assertCount(1, $cards);

		$_COOKIE['hackedLoggedInUserID'] = $context->otherUsers[0]['id'];
		$this->post('/achievements/seen', ['ids' => [$cards[0]->popup_id]]);
		$_COOKIE['hackedLoggedInUserID'] = $context->user['id'];
		Auth::init();

		$this->get('/sites/blank');
		$this->assertCount(1, $this->cardsOf(AchievementId::PROBLEMS_1000), 'The card still waits for its player');
	}

	//A player who turned the cards off has none waiting
	public function testCardsTurnedOffDontWait(): void
	{
		$context = new ContextPreparator(['user' => ['solved' => 999], 'tsumego' => 1]);
		$this->solve($context);

		$this->get('/sites/blank');
		$this->assertSame([], AchievementChecker::pendingPopups($context->user['id']));
	}

	/** @return list<Achievement> */
	private function cardsOf(AchievementId $achievementID): array
	{
		return array_values(array_filter($this->viewVariable('achievementUpdates') ?? [],
			fn(Achievement $card) => $card->id === $achievementID->value));
	}

	private function solve(ContextPreparator $context): array
	{
		$this->post('/tsumegos/result', [
			'tsumego_id' => $context->tsumegos[0]['id'],
			'seconds' => 0,
			'outcome' => PlayOutcome::SolvedClean->value,
		]);
		$this->assertResponseOk();
		return json_decode($this->view, true) ?? [];
	}

	//A collection of 100 problems finished at 100% accuracy.
	private function createFinishedCollection(int $userId): int
	{
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$achievementConditionTable = $this->fetchTable('AchievementCondition');
		$achievementConditionTable->saveOrFail($achievementConditionTable->newEntity([
			'user_id' => $userId,
			'set_id' => $setId,
			'achievement_condition_category_id' => AchievementConditionTable::CATEGORY_SET_ACCURACY,
			'value' => 100]));
		return $setId;
	}
}
