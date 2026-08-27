<?php

class AchievementsControllerTest extends TestCaseWithAuth
{
	public function testAnonymousSeesAchievementsGrid()
	{
		new ContextPreparator([]);
		$this->logout();

		$result = $this->testAction('/achievements', ['return' => 'view']);

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

		$result = $this->testAction('/achievements', ['return' => 'view']);

		$this->assertStringContainsString('Achievements', $result);
		$this->assertStringNotContainsString('achiever\'s Achievements', $result);
		$this->assertMatchesRegularExpression('/You completed 2 of/', $result);
		$this->assertStringContainsString('achievementColorGray', $result);
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

		$result = $this->testAction('/achievements/user/' . $targetUser['id'], ['return' => 'view']);

		$this->assertStringContainsString('target', $result);
		$this->assertMatchesRegularExpression('/target completed 1 of/', $result);
		$this->assertStringNotContainsString('You completed', $result);
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

		$result = $this->testAction('/achievements/user/' . $targetUser['id'], ['return' => 'view']);

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

		$result = $this->testAction('/achievements/user/' . $context->user['id'], ['return' => 'view']);

		$this->assertMatchesRegularExpression('/You completed 1 of/', $result);
		$this->assertStringNotContainsString('selfviewer\'s Achievements', $result);
	}

	public function testNonExistentUserReturns404()
	{
		new ContextPreparator([
			'user' => ['name' => 'viewer'],
		]);
		$this->login('viewer');

		$this->expectException(NotFoundException::class);
		$this->testAction('/achievements/user/999999', ['return' => 'view']);
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

		$result = $this->testAction('/users/view/' . $targetUser['id'], ['return' => 'view']);

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

		$result = $this->testAction('/achievements/view/98', ['return' => 'view']);

		$this->assertStringContainsString('Alice', $result, 'Alice should appear as a completer');
		$this->assertStringContainsString('Bob', $result, 'Bob should appear as a completer');
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

		$result = $this->testAction('/achievements/view/98', ['return' => 'view']);

		// The total is implied by the shown completers + "and X others"
		$this->assertStringNotContainsString('users completed this achievement', $result);
		// Newest completer is rendered as a link to their profile
		$newestUser = $context->otherUsers[count($context->otherUsers) - 1];
		$this->assertStringContainsString('<a href="/users/view/' . $newestUser['id'] . '">User12', $result);
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

		$result = $this->testAction('/achievements/view/98', ['return' => 'view']);

		// 2 of 4 users (50%) completed it - Common rarity, shown as a badge with a tooltip
		$this->assertStringContainsString('rarity rarity--common', $result);
		$this->assertStringContainsString('title="50% of all users"', $result);
		// Rarity badge belongs near the achievement title, not next to the completion count
		$this->assertLessThan(
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

		$result = $this->testAction('/achievements/view/98', ['return' => 'view']);

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

		$goldDigger = $this->testAction('/achievements/view/' . Achievement::GOLD_DIGGER, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $goldDigger);
		$this->assertStringContainsString('progress__fill', $goldDigger);
		$this->assertStringContainsString('--percent:70', $goldDigger);
		$this->assertStringContainsString('width:70%', $goldDigger);
		$this->assertStringContainsString('7/10', $goldDigger);

		$badPotion = $this->testAction('/achievements/view/' . Achievement::BAD_POTION, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $badPotion);
		$this->assertStringContainsString('width:20%', $badPotion);
		$this->assertStringContainsString('3/15', $badPotion);

		$completeSets = $this->testAction('/achievements/view/' . Achievement::COMPLETE_SETS_III, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $completeSets);
		$this->assertStringContainsString('width:80%', $completeSets);
		$this->assertStringContainsString('24/30', $completeSets);

		$errorStreak = $this->testAction('/achievements/view/' . Achievement::NO_ERROR_STREAK_IV, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $errorStreak);
		$this->assertStringContainsString('41/50', $errorStreak);

		$danSolve = $this->testAction('/achievements/view/' . Achievement::SOLVE_10_2D, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $danSolve);
		$this->assertStringContainsString('7/10', $danSolve);

		$sprint = $this->testAction('/achievements/view/' . Achievement::SPRINT, ['return' => 'view']);
		$this->assertStringContainsString('class="progress"', $sprint);
	}

	public function testViewAchievementShowsNoProgressBarForNonCounterAchievement(): void
	{
		new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'golden', 'value' => 7],
			],
		]);

		$result = $this->testAction('/achievements/view/1', ['return' => 'view']);

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

		$result = $this->testAction('/achievements', ['return' => 'view']);

		$this->assertStringContainsString('2024-05-15 12:30:00', $result);
	}
}
