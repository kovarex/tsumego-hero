<?php

use App\Utility\AchievementId;
use App\Utility\AchievementRarity;
use App\Utility\UserProfile;

//The achievements on the profile (profile_achievements.php): the ones the player unlocked, the rarest first, each with
//the share of the active players (not deleted, at least one problem solved) who have it.
class ProfileAchievementsTest extends IntegrationTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		AchievementRarity::forget();
	}

	public function tearDown(): void
	{
		AchievementRarity::forget();
		parent::tearDown();
	}

	//Four active players: the player with Emerald, Sapphire and Gold Digger, the first other with Emerald and Gold Digger, the
	//second with Emerald, the third with none. A player who solved nothing has Sapphire and does not count.
	private function rarityFixture(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['name' => 'player', 'solved' => 5, 'achievement-statuses' => [
				['id' => AchievementId::EMERALD->value, 'created' => '2026-01-01 10:00:00'],
				['id' => AchievementId::SAPPHIRE->value, 'created' => '2026-02-01 10:00:00'],
				['id' => AchievementId::GOLD_DIGGER->value, 'created' => '2026-03-01 10:00:00']]],
			'other-users' => [
				['name' => 'first', 'solved' => 1, 'achievement-statuses' => [['id' => AchievementId::EMERALD->value], ['id' => AchievementId::GOLD_DIGGER->value]]],
				['name' => 'second', 'solved' => 1, 'achievement-statuses' => [['id' => AchievementId::EMERALD->value]]],
				['name' => 'third', 'solved' => 1],
				['name' => 'idle', 'solved' => 0, 'achievement-statuses' => [['id' => AchievementId::SAPPHIRE->value]]]]]);
	}

	//The ids of the achievement rows of the page, in their order
	private function rowIDs(): array
	{
		preg_match_all('/class="profile-achievement" data-achievement-id="(\d+)"/', $this->view, $matches);
		return array_map('intval', $matches[1]);
	}

	public function testThePercentIsTheShareOfTheActivePlayers(): void
	{
		$this->rarityFixture();

		$counts = AchievementRarity::counts();
		$this->assertSame(4, $counts['players']);
		$this->assertSame(3, $counts['holders'][AchievementId::EMERALD->value]);
		$this->assertSame(1, $counts['holders'][AchievementId::SAPPHIRE->value], 'the player who solved nothing does not count');
		$this->assertSame(2, $counts['holders'][AchievementId::GOLD_DIGGER->value]);
		$this->assertEqualsWithDelta(75.0, AchievementRarity::percent(AchievementId::EMERALD->value), 0.001);
		$this->assertEqualsWithDelta(25.0, AchievementRarity::percent(AchievementId::SAPPHIRE->value), 0.001);
		$this->assertEqualsWithDelta(0.0, AchievementRarity::percent(AchievementId::DIAMOND->value), 0.001);
		$this->assertEqualsWithDelta(25.0, AchievementRarity::percent(AchievementId::DIAMOND->value, true), 0.001, 'one the player has counts them');
	}

	//The counts are kept: a later unlock shows in them only once they are counted again
	public function testTheCountsAreKeptUntilForgotten(): void
	{
		$context = $this->rarityFixture();
		$this->assertSame(1, AchievementRarity::counts()['holders'][AchievementId::SAPPHIRE->value]);

		$context->prepareAchievementStatuses($context->otherUsers[0], [['id' => AchievementId::SAPPHIRE->value]]);
		$this->assertSame(1, AchievementRarity::counts()['holders'][AchievementId::SAPPHIRE->value]);
		AchievementRarity::forget();
		$this->assertSame(2, AchievementRarity::counts()['holders'][AchievementId::SAPPHIRE->value]);
	}

	public function testTheProfileListsTheUnlockedAchievementsRarestFirst(): void
	{
		$context = $this->rarityFixture();
		$this->login('player');

		$this->get('/users/view/' . $context->user['id']);

		$this->assertSame([AchievementId::SAPPHIRE->value, AchievementId::GOLD_DIGGER->value, AchievementId::EMERALD->value], $this->rowIDs());
		$this->assertMatchesRegularExpression('#Achievements\s*<span class="profile-achievements__count">3 / \d+</span>#', $this->view);
		$this->assertStringContainsString('25% of players', $this->view);
		$this->assertStringContainsString('50% of players', $this->view);
		$this->assertStringContainsString('75% of players', $this->view);
		$this->assertStringContainsString('style="width:25%"', $this->view);
		$this->assertStringContainsString('datetime="' . date('c', strtotime('2026-02-01 10:00:00')) . '"', $this->view);
		$this->assertStringContainsString('href="/achievements/user/', $this->view);
		$this->assertStringNotContainsString('profile-achievements-toggle', $this->view, 'three rows need no Show all');
	}

	//Another player's profile shows their achievements, not the viewer's
	public function testAnotherPlayersProfileShowsTheirs(): void
	{
		$context = $this->rarityFixture();
		$this->login('player');

		$this->get('/users/view/' . $context->otherUsers[0]['id']);

		$this->assertSame([AchievementId::GOLD_DIGGER->value, AchievementId::EMERALD->value], $this->rowIDs());
		$this->assertStringContainsString('href="/achievements/view/' . AchievementId::GOLD_DIGGER->value . '/' . $context->otherUsers[0]['id'] . '"', $this->view);
	}

	public function testAPlayerWithoutAchievementsSeesNone(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player', 'solved' => 1]]);
		$this->login('player');

		$this->get('/users/view/' . $context->user['id']);

		$this->assertSame([], $this->rowIDs());
		$this->assertStringContainsString('No achievements unlocked yet.', $this->view);
	}

	public function testARepeatedAchievementShowsHowOftenItWasEarned(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'player', 'solved' => 1, 'achievement-statuses' => [
			['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3]]]]);
		$this->login('player');

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('title="Earned 3 times">×3</span>', $this->view);
	}

	//Past the rarest ones, the rows wait behind Show all
	public function testOnlyTheRarestShowBeforeShowAll(): void
	{
		$ids = [AchievementId::EMERALD, AchievementId::SAPPHIRE, AchievementId::RUBY, AchievementId::DIAMOND, AchievementId::USER_OF_THE_DAY,
			AchievementId::FAVORITES, AchievementId::SPRINT, AchievementId::GOLD_DIGGER, AchievementId::BAD_POTION, AchievementId::SOLVE_1D,
			AchievementId::SOLVE_2D, AchievementId::SOLVE_3D];
		$context = new ContextPreparator(['user' => ['name' => 'player', 'solved' => 1, 'achievement-statuses' => array_map(fn(AchievementId $id) => ['id' => $id->value], $ids)]]);
		$this->login('player');

		$this->get('/users/view/' . $context->user['id']);

		$this->assertCount(12, $this->rowIDs());
		$this->assertSame(count($ids) - UserProfile::SHOWN_ACHIEVEMENT_COUNT, substr_count($this->view, ' hidden data-profile-achievement-more'));
		$this->assertStringContainsString('aria-expanded="false"', $this->view);
		$this->assertStringContainsString('>Show all 12</button>', $this->view);
	}

	public function testAGuestSeesTheList(): void
	{
		$context = $this->rarityFixture();
		$this->logout();

		$this->get('/users/view/' . $context->user['id']);

		$this->assertSame([AchievementId::SAPPHIRE->value, AchievementId::GOLD_DIGGER->value, AchievementId::EMERALD->value], $this->rowIDs());
	}
}
