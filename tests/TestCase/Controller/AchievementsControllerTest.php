<?php

use App\Model\Table\AchievementConditionTable;
use App\Model\Table\AchievementTable;
use App\Utility\AchievementId;
use App\Utility\AchievementRarity;
use App\Utility\DailyStreak;
use App\Utility\Rating;

class AchievementsControllerTest extends IntegrationTestCase
{
	//The rows are ordered by rarity, counted once an hour: each test counts its own players
	public function setUp(): void
	{
		parent::setUp();
		AchievementRarity::forget();
	}

	public function testAnonymousSeesAchievementsGrid()
	{
		new ContextPreparator([]);
		$this->logout();

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('Achievements', $result);
		$this->assertStringContainsString('achievement-list', $result);
		$this->assertSame($this->fetchTable('Achievement')->find()->count(), substr_count($result, 'class="achievement-row achievement-row--locked"'), 'a guest sees every one locked');
		$this->assertStringNotContainsString('achievement-row--unlocked', $result);
		$this->assertDoesNotMatchRegularExpression('#unlocked\s*</h2>#', $result);
	}

	public function testLoggedInSeesOwnCompletionCount()
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'achiever',
				'achievement-statuses' => [
					['id' => 1],
					['id' => 2],
				],
			],
		]);
		$this->login('achiever');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('Achievements', $result);
		$this->assertStringNotContainsString('achiever\'s Achievements', $result);
		$this->assertMatchesRegularExpression('#2 / \d+ unlocked#', $result);
		$this->assertStringContainsString('class="achievement-row achievement-row--locked"', $result);
		$this->assertSame(2, substr_count($result, 'class="achievement-row achievement-row--unlocked"'));
	}

	public function testCardsShowProgressOfLockedSetAchievements()
	{
		new ContextPreparator([
			'user' => ['name' => 'achiever', 'achievement-statuses' => [['id' => AchievementId::COMPLETE_SETS_I->value]]],
			'achievement-conditions' => [
				['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 27],
				['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 7],
			],
		]);
		$this->login('achiever');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('27/' . AchievementTable::COMPLETE_SETS_III_SETS_COUNT, $result);
		$this->assertStringContainsString('27/' . AchievementTable::COMPLETE_SETS_V_SETS_COUNT, $result);
		// the card of the unlocked one, up to its closing link, shows its date and no progress
		$unlockedCard = '#data-achievement-id="' . AchievementId::COMPLETE_SETS_I->value . '">(?:(?!achievement-row ).)*';
		$this->assertMatchesRegularExpression($unlockedCard . '<time#s', $result);
		$this->assertDoesNotMatchRegularExpression($unlockedCard . 'progress__label#s', $result, 'Unlocked achievements show their date, not progress');
		$this->assertStringContainsString('7/' . AchievementTable::GOLD_DIGGER_COUNT, $result, 'The other counter achievements show their progress too');
	}

	//The solved count achievements count the problems solved, the value their check compares: a page load
	//unlocks the ones reached, so their cards show the date, and the others show how far the player got.
	public function testCardsShowProblemsSolvedProgress(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'achiever', 'solved' => 3420]]);
		$this->login('achiever');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('>3420/4000<', $result);
		$this->assertStringContainsString('>3420/10000<', $result);
		$this->assertStringNotContainsString('>3000/3000<', $result, 'An unlocked achievement shows its date, not progress');
		$this->assertTrue($this->isUnlocked($context->user['id'], AchievementId::PROBLEMS_3000));
		$this->assertFalse($this->isUnlocked($context->user['id'], AchievementId::PROBLEMS_4000));
	}

	public function testCardsShowNoProblemsSolvedYet(): void
	{
		new ContextPreparator(['user' => ['name' => 'achiever', 'solved' => 0]]);
		$this->login('achiever');

		$this->get('/achievements');

		$this->assertStringContainsString('>0/1000<', $this->view);
		$this->assertStringContainsString('>0/10000<', $this->view);
	}

	//Reaching the goal unlocks it on the player's own page load, the same threshold the bar fills at
	public function testReachingTheProblemsSolvedGoalUnlocksIt(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'achiever', 'solved' => 4000]]);
		$this->login('achiever');

		$this->get('/achievements');

		$this->assertTrue($this->isUnlocked($context->user['id'], AchievementId::PROBLEMS_4000));
		$this->assertStringNotContainsString('>4000/4000<', $this->view);
		$this->assertStringContainsString('>4000/5000<', $this->view);
	}

	//Another player's page shows their progress, not the viewer's; a goal reached but not yet
	//unlocked (no page load of theirs checked it) shows a full bar.
	public function testOtherPlayersCardsShowTheirProgress(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer', 'solved' => 5],
			'other-users' => [['name' => 'target', 'solved' => 12000, 'level' => 47]]]);
		$this->login('viewer');

		$this->get('/achievements/user/' . $context->otherUsers[0]['id']);
		$result = $this->view;

		$this->assertStringContainsString('>1000/1000<', $result);
		$this->assertStringContainsString('>10000/10000<', $result);
		$this->assertStringContainsString('width:100%', $result);
		$this->assertStringContainsString('>47/50<', $result);
		$this->assertStringNotContainsString('>5/1000<', $result);
	}

	public function testAchievementPageShowsProblemsSolvedProgress(): void
	{
		new ContextPreparator(['user' => ['name' => 'achiever', 'solved' => 3420]]);
		$this->login('achiever');

		$this->get('/achievements/view/' . AchievementId::PROBLEMS_5000->value);
		$this->assertStringContainsString('class="progress"', $this->view);
		$this->assertStringContainsString('3420/5000', $this->view);
		$this->assertStringContainsString('width:68%', $this->view);

		// unlocked, so full like the other achievements
		$this->get('/achievements/view/' . AchievementId::PROBLEMS_1000->value);
		$this->assertStringContainsString('1000/1000', $this->view);
		$this->assertStringContainsString('width:100%', $this->view);
	}

	public function testLevelAchievementsShowTheLevel(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'achiever', 'level' => 47]]);
		$this->login('achiever');

		$this->get('/achievements');
		$this->assertStringContainsString('>47/50<', $this->view);
		$this->assertStringContainsString('>47/100<', $this->view);
		$this->assertTrue($this->isUnlocked($context->user['id'], AchievementId::MORE_POWER));

		$this->get('/achievements/view/' . AchievementId::HALF_WAY_TO_TOP->value);
		$this->assertStringContainsString('47/50', $this->view);
		$this->assertStringContainsString('width:94%', $this->view);
	}

	//The goal is the minimal rating of the rank the check compares with, and the rating is rounded down,
	//so a bar is never full before the achievement unlocks
	public function testRatingAchievementsShowTheRating(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'achiever', 'rating' => 1749.6]]);
		$this->login('achiever');
		$threeKyu = (int) Rating::getRankMinimalRatingFromReadableRank('3k');

		$this->get('/achievements');
		$this->assertStringContainsString('>1749/' . $threeKyu . '<', $this->view);
		$this->assertFalse($this->isUnlocked($context->user['id'], AchievementId::RATING_3_KYU));
		$this->assertTrue($this->isUnlocked($context->user['id'], AchievementId::RATING_4_KYU));

		$this->get('/achievements/view/' . AchievementId::RATING_5_DAN->value);
		$this->assertStringContainsString('1749/' . (int) Rating::getRankMinimalRatingFromReadableRank('5d'), $this->view);
	}

	public function testGuestSeesNoProgressOnAchievementPage(): void
	{
		new ContextPreparator([]);
		$this->logout();

		$this->get('/achievements/view/' . AchievementId::PROBLEMS_1000->value);
		$this->assertStringNotContainsString('class="progress"', $this->view);
		$this->get('/achievements/view/' . AchievementId::COMPLETE_SETS_I->value);
		$this->assertStringNotContainsString('class="progress"', $this->view);
	}

	private function isUnlocked(int $userID, AchievementId $achievementID): bool
	{
		return $this->fetchTable('AchievementStatus')->exists(['user_id' => $userID, 'achievement_id' => $achievementID->value]);
	}

	public function testAnonymousSeesNoProgressOnCards()
	{
		new ContextPreparator([]);
		$this->logout();

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringNotContainsString('progress__fill', $result);
	}

	//Every achievement has exactly one card, so the completion total counts each
	//unlocked achievement once - earning the repeatable one again does not change
	//how many achievements are completed.
	public function testRepeatedAchievementsCountOnceInCompletionTotal()
	{
		new ContextPreparator([
			'user' => [
				'name' => 'repeater',
				'achievement-statuses' => [
					['id' => AchievementId::PROBLEMS_1000->value],
					['id' => AchievementId::PROBLEMS_2000->value],
					['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3],
				],
			],
		]);
		$this->login('repeater');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertMatchesRegularExpression('#3 / \d+ unlocked#', $result);
		// ... the number of repetitions is still shown on the row itself
		$this->assertStringContainsString('title="Earned 3 times"', $result);
		$this->assertStringContainsString('>×3<', $result);
	}

	//The achievement detail page explains the extra completions by showing how
	//often the repeatable achievement was earned.
	public function testAchievementDetailShowsNumberOfEarnings()
	{
		new ContextPreparator([
			'user' => [
				'name' => 'repeater',
				'achievement-statuses' => [
					['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 3],
				],
			],
		]);
		$this->login('repeater');

		$this->get('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value);
		$result = $this->view;

		$this->assertStringContainsString('title="Earned 3 times"', $result);
		$this->assertStringContainsString('>3x<', $result);
	}

	//An achievement earned once shows no repeat indicator.
	public function testAchievementEarnedOnceHasNoRepeatIndicator()
	{
		new ContextPreparator([
			'user' => [
				'name' => 'single',
				'achievement-statuses' => [
					['id' => AchievementId::PROBLEMS_1000->value],
				],
			],
		]);
		$this->login('single');

		$this->get('/achievements/view/' . AchievementId::PROBLEMS_1000->value);
		$result = $this->view;

		$this->assertStringNotContainsString('title="Earned', $result);
	}

	public function testViewOtherUserShowsTheirName()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [
				[
					'name' => 'target',
					'achievement-statuses' => [
						['id' => 1],
					],
				],
			],
		]);
		$this->login('viewer');
		$targetUser = $context->otherUsers[0];

		$this->get('/achievements/user/' . $targetUser['id']);
		$result = $this->view;

		$this->assertStringContainsString('target', $result);
		$this->assertMatchesRegularExpression('#1 / \d+ unlocked#', $result);
	}

	public function testViewGoogleUserShowsTheirDisplayName()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'Google Player']],
		]);
		$this->login('viewer');
		$targetUser = $context->otherUsers[0];

		// Google accounts have no login name, only a display name
		$this->fetchTable('User')->updateAll([
			'name' => null,
			'external_id' => '987654321098765432109'], ['id' => $targetUser['id']]);

		$this->get('/achievements/user/' . $targetUser['id']);
		$result = $this->view;

		$this->assertStringContainsString('DN_Google Player', $result);
	}

	public function testViewOtherUserShowsTheirUnlocksNotViewers()
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'viewer',
				'achievement-statuses' => [
					['id' => 1],
					['id' => 2],
				],
			],
			'other-users' => [
				[
					'name' => 'target',
					'achievement-statuses' => [
						['id' => 3],
					],
				],
			],
		]);
		$this->login('viewer');
		$targetUser = $context->otherUsers[0];

		$this->get('/achievements/user/' . $targetUser['id']);
		$result = $this->view;

		$this->assertMatchesRegularExpression('#1 / \d+ unlocked#', $result);
	}

	public function testViewSelfViaUserRoute()
	{
		$context = new ContextPreparator([
			'user' => [
				'name' => 'selfviewer',
				'achievement-statuses' => [
					['id' => 1],
				],
			],
		]);
		$this->login('selfviewer');

		$this->get('/achievements/user/' . $context->user['id']);
		$result = $this->view;

		$this->assertMatchesRegularExpression('#1 / \d+ unlocked#', $result);
		$this->assertStringNotContainsString('selfviewer\'s Achievements', $result);
	}

	public function testNonExistentUserReturns404()
	{
		new ContextPreparator([
			'user' => ['name' => 'viewer'],
		]);
		$this->login('viewer');

		$this->get('/achievements/user/999999');

		$this->assertResponseCode(404);
	}

	public function testProfileLinkPointsToUserAchievements()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [
				[
					'name' => 'target',
					'achievement-statuses' => [
						['id' => 1],
					],
				],
			],
		]);
		$this->login('viewer');
		$targetUser = $context->otherUsers[0];

		$this->get('/users/view/' . $targetUser['id']);
		$result = $this->view;

		$this->assertStringContainsString('/achievements/user/' . $targetUser['id'], $result);
	}

	public function testViewAchievementShowsCompleterNames(): void
	{
		$context = new ContextPreparator([
			'other-users' => [
				['name' => 'Alice', 'achievement-statuses' => [['id' => 98]]],
				['name' => 'Bob',   'achievement-statuses' => [['id' => 98]]],
			],
		]);

		$this->get('/achievements/view/98');
		$result = $this->view;

		$this->assertStringContainsString('DN_Alice', $result, 'Alice should appear as a completer');
		$this->assertStringContainsString('DN_Bob', $result, 'Bob should appear as a completer');
	}

	public function testViewAchievementShowsOnlyTenMostRecentCompleterNames(): void
	{
		$completers = [];
		for ($i = 1; $i <= 12; $i++)
			$completers[] = [
				'name' => 'User' . $i,
				'achievement-statuses' => [['id' => 98, 'created' => date('Y-m-d H:i:s', strtotime("2024-01-01 +{$i} days"))]],
			];
		$context = new ContextPreparator(['other-users' => $completers]);

		$this->get('/achievements/view/98');
		$result = $this->view;

		// The total is implied by the shown completers + "and X others"
		$this->assertStringNotContainsString('users completed this achievement', $result);
		// Newest completer is rendered as a link to their profile (avatar + display_name + rank)
		$newestUser = $context->otherUsers[count($context->otherUsers) - 1];
		$this->assertStringContainsString('<a href="/users/view/' . $newestUser['id'] . '"', $result);
		$this->assertStringContainsString('DN_User12', $result);
		$this->assertStringContainsString('and 2 others.', $result);
	}

	public function testViewAchievementShowsRarityBadgeWithTooltip(): void
	{
		new ContextPreparator([
			'user' => ['achievement-statuses' => [['id' => 98]]],
			'other-users' => [
				['name' => 'Alice', 'achievement-statuses' => [['id' => 98]]],
				['name' => 'Bob'],
				['name' => 'Carol'],
			],
		]);

		$this->get('/achievements/view/98');
		$result = $this->view;

		// 2 of 4 users (50%) completed it - Common rarity, shown as a badge with a tooltip
		$this->assertStringContainsString('rarity rarity--common', $result);
		$this->assertStringContainsString('title="50% of all users"', $result);
		// Rarity badge belongs below the achievement card, not above it
		$this->assertGreaterThan(
			strpos($result, 'achievementWrapper'),
			strpos($result, 'rarity')
		);
	}

	public function testViewAchievementShowsUserPercentage(): void
	{
		new ContextPreparator([
			'user' => ['achievement-statuses' => [['id' => 98]]],
			'other-users' => [
				['name' => 'Alice', 'achievement-statuses' => [['id' => 98]]],
				['name' => 'Bob'],
				['name' => 'Carol'],
			],
		]);

		$this->get('/achievements/view/98');
		$result = $this->view;

		// The percentage lives in the rarity badge tooltip
		$this->assertStringContainsString('title="50% of all users"', $result);
	}

	public function testViewAchievementShowsProgressBarForCounterAchievements(): void
	{
		new ContextPreparator([
			'achievement-conditions' => [
				['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 7],
				['category' => AchievementConditionTable::CATEGORY_POTION, 'value' => 3],
				['category' => AchievementConditionTable::CATEGORY_COMPLETED_SETS, 'value' => 24],
				['category' => AchievementConditionTable::CATEGORY_NO_ERROR_STREAK, 'value' => 41],
				['category' => AchievementConditionTable::CATEGORY_DAN_SOLVE_2D, 'value' => 7],
			],
		]);

		$this->get('/achievements/view/' . AchievementId::GOLD_DIGGER->value);
		$goldDigger = $this->view;
		$this->assertStringContainsString('class="progress"', $goldDigger);
		$this->assertStringContainsString('progress__fill', $goldDigger);
		$this->assertStringContainsString('--percent:70', $goldDigger);
		$this->assertStringContainsString('width:70%', $goldDigger);
		$this->assertStringContainsString('7/10', $goldDigger);

		$this->get('/achievements/view/' . AchievementId::BAD_POTION->value);
		$badPotion = $this->view;
		$this->assertStringContainsString('class="progress"', $badPotion);
		$this->assertStringContainsString('width:20%', $badPotion);
		$this->assertStringContainsString('3/15', $badPotion);

		$this->get('/achievements/view/' . AchievementId::COMPLETE_SETS_III->value);
		$completeSets = $this->view;
		$this->assertStringContainsString('class="progress"', $completeSets);
		$this->assertStringContainsString('width:80%', $completeSets);
		$this->assertStringContainsString('24/30', $completeSets);

		$this->get('/achievements/view/' . AchievementId::NO_ERROR_STREAK_IV->value);
		$errorStreak = $this->view;
		$this->assertStringContainsString('class="progress"', $errorStreak);
		$this->assertStringContainsString('41/50', $errorStreak);

		$this->get('/achievements/view/' . AchievementId::SOLVE_10_2D->value);
		$danSolve = $this->view;
		$this->assertStringContainsString('class="progress"', $danSolve);
		$this->assertStringContainsString('7/10', $danSolve);

		$this->get('/achievements/view/' . AchievementId::SPRINT->value);
		$sprint = $this->view;
		$this->assertStringContainsString('class="progress"', $sprint);
	}

	//The daily streak achievements show the streak running now, on the cards and on their pages
	public function testDailyStreakAchievementsShowTheStreakRunningNow(): void
	{
		$yesterday = new DateTimeImmutable(DailyStreak::today(null, new DateTimeImmutable()))->modify('-1 day')->format('Y-m-d');
		new ContextPreparator(['user' => ['name' => 'achiever', 'streak_length' => 3, 'streak_best' => 9, 'streak_last_day' => $yesterday]]);
		$this->login('achiever');

		$this->get('/achievements');
		$this->assertStringContainsString('3/' . AchievementTable::DAILY_STREAK_TWO_WEEKS_DAYS, $this->view);
		$this->assertStringContainsString('3/' . AchievementTable::DAILY_STREAK_YEAR_DAYS, $this->view);

		$this->get('/achievements/view/' . AchievementId::DAILY_STREAK_MONTH->value);
		$this->assertStringContainsString('class="progress"', $this->view);
		$this->assertStringContainsString('3/' . AchievementTable::DAILY_STREAK_MONTH_DAYS, $this->view);
	}

	public function testViewAchievementShowsNoProgressBarForNonCounterAchievement(): void
	{
		new ContextPreparator([
			'achievement-conditions' => [
				['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 7],
			],
		]);

		$this->get('/achievements/view/' . AchievementId::USER_OF_THE_DAY->value);
		$result = $this->view;

		$this->assertStringNotContainsString('class="progress"', $result);
		$this->assertStringNotContainsString('progress__fill', $result);
	}

	public function testUnlockedAchievementShowsUnlockDate(): void
	{
		new ContextPreparator([
			'user' => [
				'name' => 'achiever',
				'achievement-statuses' => [
					['id' => 1, 'created' => '2024-05-15 12:30:00'],
				],
			],
		]);
		$this->login('achiever');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('datetime="' . date('c', strtotime('2024-05-15 12:30:00')) . '" data-format="date">2024-05-15</time>', $result);
	}
}
