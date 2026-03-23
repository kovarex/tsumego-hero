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

		$this->assertStringContainsString('target\'s Achievements', $result);
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
}
