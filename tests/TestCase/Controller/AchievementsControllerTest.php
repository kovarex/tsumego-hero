<?php

use App\Utility\AchievementId;

class AchievementsControllerTest extends IntegrationTestCase
{
	public function testAnonymousSeesAchievementsGrid()
	{
		new ContextPreparator([]);
		$this->logout();

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('Achievements', $result);
		$this->assertStringContainsString('achievementWrapper', $result);
		$this->assertGreaterThan(0, substr_count($result, 'acTitle'));
		$this->assertStringNotContainsString('completed', $result);
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
		$this->assertMatchesRegularExpression('/You completed 2 of/', $result);
		$this->assertStringContainsString('achievementColorGray', $result);
	}

	public function testCardsShowProgressOfLockedSetAchievements()
	{
		new ContextPreparator([
			'user' => ['name' => 'achiever', 'achievement-statuses' => [['id' => AchievementId::COMPLETE_SETS_I->value]]],
			'achievement-conditions' => [
				['category' => 'set', 'value' => 27],
				['category' => 'golden', 'value' => 7],
			],
		]);
		$this->login('achiever');

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringContainsString('27/' . Achievement::COMPLETE_SETS_III_SETS_COUNT, $result);
		$this->assertStringContainsString('27/' . Achievement::COMPLETE_SETS_V_SETS_COUNT, $result);
		$this->assertStringNotContainsString(Achievement::COMPLETE_SETS_I_SETS_COUNT . '/' . Achievement::COMPLETE_SETS_I_SETS_COUNT, $result, 'Unlocked achievements show their date, not progress');
		$this->assertStringNotContainsString('7/' . Achievement::GOLD_DIGGER_COUNT, $result, 'Only the set achievements show progress on the cards so far');
	}

	public function testAnonymousSeesNoProgressOnCards()
	{
		new ContextPreparator([]);
		$this->logout();

		$this->get('/achievements');
		$result = $this->view;

		$this->assertStringNotContainsString('progress__fill', $result);
	}

	/**
	 * Every achievement has exactly one card, so the completion total counts each
	 * unlocked achievement once - earning the repeatable one again does not change
	 * how many achievements are completed.
	 */
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

		$this->assertMatchesRegularExpression('/You completed 3 of/', $result);
		// ... the number of repetitions is still shown on the card itself
		$this->assertStringContainsString('title="Earned 3 times"', $result);
		$this->assertStringContainsString('>3x<', $result);
	}

	/**
	 * The achievement detail page explains the extra completions by showing how
	 * often the repeatable achievement was earned.
	 */
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

	/**
	 * An achievement earned once shows no repeat indicator.
	 */
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
		$this->assertMatchesRegularExpression('/target completed 1 of/', $result);
		$this->assertStringNotContainsString('You completed', $result);
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
		$user = ClassRegistry::init('User');
		$user->id = $targetUser['id'];
		$user->saveField('name', null);
		$user->saveField('external_id', '987654321098765432109');

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

		$this->assertMatchesRegularExpression('/target completed 1 of/', $result);
		$this->assertStringNotContainsString('You completed', $result);
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

		$this->assertMatchesRegularExpression('/You completed 1 of/', $result);
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
				['category' => 'golden', 'value' => 7],
				['category' => 'potion', 'value' => 3],
				['category' => 'set', 'value' => 24],
				['category' => 'err', 'value' => 41],
				['category' => 'danSolve2d', 'value' => 7],
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

	public function testViewAchievementShowsNoProgressBarForNonCounterAchievement(): void
	{
		new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'golden', 'value' => 7],
			],
		]);

		$this->get('/achievements/view/1');
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

		$this->assertStringContainsString('2024-05-15 12:30:00', $result);
	}
}
