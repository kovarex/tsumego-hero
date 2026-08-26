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
		new ContextPreparator(['other-users' => $completers]);

		$result = $this->testAction('/achievements/view/98', ['return' => 'view']);

		$this->assertStringContainsString('12 users completed this achievement.', $result);
		$this->assertStringContainsString('Recently completed by User12, User11, User10, User9, User8, User7, User6, User5, User4, User3 and more.', $result);
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
