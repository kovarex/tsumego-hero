<?php

use App\Utility\AchievementId;
use App\Utility\AchievementRarity;

//The achievements page (Achievements/index.php): every achievement a row, the most common first by the share of the
//active players (not deleted, at least one problem solved) who have it; the locked ones greyed out, with their progress.
class AchievementsListTest extends IntegrationTestCase
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

	//Four active players: the player with Emerald, Sapphire and Gold Digger, the first other with Emerald and Gold
	//Digger, the second with Emerald, the third with none. A player who solved nothing has Sapphire and does not count.
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

	//The rows of the page in their order, as achievement id => locked or unlocked
	private function rows(): array
	{
		preg_match_all('/class="achievement-row achievement-row--(locked|unlocked)" data-achievement-id="(\d+)"/', $this->view, $matches);
		return array_combine(array_map('intval', $matches[2]), $matches[1]);
	}

	//The markup of the row of an achievement, up to the next row
	private function row(AchievementId $achievement): string
	{
		$this->assertMatchesRegularExpression('#data-achievement-id="' . $achievement->value . '">#', $this->view);
		preg_match('#data-achievement-id="' . $achievement->value . '">((?:(?!achievement-row ).)*)#s', $this->view, $match);
		return $match[1];
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

	public function testEveryAchievementIsListedTheMostCommonFirst(): void
	{
		$this->rarityFixture();
		$this->login('player');

		$this->get('/achievements');

		$rows = $this->rows();
		$this->assertCount($this->fetchTable('Achievement')->find()->count(), $rows, 'locked ones too, with no Show all');
		$this->assertSame([AchievementId::EMERALD->value, AchievementId::GOLD_DIGGER->value, AchievementId::SAPPHIRE->value], array_slice(array_keys($rows), 0, 3));
		$this->assertStringNotContainsString('Show all', $this->view);
		$this->assertMatchesRegularExpression('#3 / \d+ unlocked\s*</h2>#', $this->view);
		$this->assertStringContainsString('75% of players', $this->row(AchievementId::EMERALD));
		$this->assertStringContainsString('50% of players', $this->row(AchievementId::GOLD_DIGGER));
		$this->assertStringContainsString('25% of players', $this->row(AchievementId::SAPPHIRE));
		$this->assertStringContainsString('style="width:25%"', $this->row(AchievementId::SAPPHIRE));
	}

	//An unlocked row has its day; a locked one is marked locked, has no day, and shows its progress where it has a counter
	public function testLockedAndUnlockedRows(): void
	{
		$this->rarityFixture();
		$this->login('player');

		$this->get('/achievements');

		$rows = $this->rows();
		$this->assertSame('unlocked', $rows[AchievementId::SAPPHIRE->value]);
		$this->assertSame('locked', $rows[AchievementId::RUBY->value]);
		$this->assertStringContainsString('datetime="' . date('c', strtotime('2026-02-01 10:00:00')) . '"', $this->row(AchievementId::SAPPHIRE));
		$this->assertStringNotContainsString('<time', $this->row(AchievementId::RUBY));
		$this->assertStringContainsString('<div class="progress__label">5/1000</div>', $this->row(AchievementId::PROBLEMS_1000));
		$this->assertStringNotContainsString('<time', $this->row(AchievementId::PROBLEMS_1000));
		$this->assertStringNotContainsString('progress__label', $this->row(AchievementId::RUBY), 'no counter, no bar');
		$this->assertStringContainsString('href="/achievements/view/' . AchievementId::RUBY->value . '/', $this->row(AchievementId::RUBY));
	}

	//A guest sees every row locked and no progress, in the same order
	public function testAGuestSeesEveryRowLocked(): void
	{
		$this->rarityFixture();
		$this->logout();

		$this->get('/achievements');

		$rows = $this->rows();
		$this->assertSame([AchievementId::EMERALD->value, AchievementId::GOLD_DIGGER->value, AchievementId::SAPPHIRE->value], array_slice(array_keys($rows), 0, 3));
		$this->assertSame(['locked'], array_values(array_unique($rows)));
		$this->assertStringNotContainsString('progress__label', $this->view);
		$this->assertStringNotContainsString('<time', $this->view);
	}

	//Another player's page shows their unlocks, not the viewer's
	public function testAnotherPlayersPageShowsTheirs(): void
	{
		$context = $this->rarityFixture();
		$this->login('player');

		$this->get('/achievements/user/' . $context->otherUsers[0]['id']);

		$rows = $this->rows();
		$this->assertSame('unlocked', $rows[AchievementId::EMERALD->value]);
		$this->assertSame('unlocked', $rows[AchievementId::GOLD_DIGGER->value]);
		$this->assertSame('locked', $rows[AchievementId::SAPPHIRE->value]);
		$this->assertStringContainsString('href="/achievements/view/' . AchievementId::GOLD_DIGGER->value . '/' . $context->otherUsers[0]['id'] . '"', $this->view);
	}

	public function testARepeatedAchievementShowsHowOftenItWasEarned(): void
	{
		new ContextPreparator(['user' => ['name' => 'player', 'solved' => 1, 'achievement-statuses' => [
			['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3]]]]);
		$this->login('player');

		$this->get('/achievements');

		$this->assertStringContainsString('title="Earned 3 times">×3</span>', $this->row(AchievementId::SUPERIOR_ACCURACY));
	}

	//The profile shows no achievements and no hero powers: the achievements tab has them
	public function testTheProfileHasNoAchievementsOrHeroPowers(): void
	{
		$context = $this->rarityFixture();
		$this->login('player');

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringNotContainsString('data-achievement-id', $this->view);
		$this->assertStringNotContainsString('profile-achievements', $this->view);
		$this->assertStringNotContainsString('hero-powers', $this->view);
		$this->assertStringNotContainsString('Hero Powers:', $this->view);
	}
}
