<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');
require_once(__DIR__ . '/../../ContextPreparator.php');

class RecentAchievementsTest extends TestCaseWithAuth
{
	public function testGetRecentReturnsResults(): void
	{
		$context = new ContextPreparator();
		$AchievementStatus = ClassRegistry::init('AchievementStatus');

		$AchievementStatus->create();
		$AchievementStatus->save([
			'AchievementStatus' => [
				'user_id' => $context->user['id'],
				'achievement_id' => 20,
				'created' => date('Y-m-d H:i:s'),
			],
		]);

		$result = $AchievementStatus->getRecent(7);
		$this->assertNotEmpty($result);
	}

	public function testGetRecentReturnsCorrectStructure(): void
	{
		$context = new ContextPreparator();
		$AchievementStatus = ClassRegistry::init('AchievementStatus');

		$AchievementStatus->create();
		$AchievementStatus->save([
			'AchievementStatus' => [
				'user_id' => $context->user['id'],
				'achievement_id' => 30,
				'created' => date('Y-m-d H:i:s'),
			],
		]);

		$result = $AchievementStatus->getRecent(1);
		$this->assertCount(1, $result);

		$item = $result[0];
		$this->assertArrayHasKey('status_id', $item);
		$this->assertArrayHasKey('id', $item);
		$this->assertArrayHasKey('name', $item);
		$this->assertArrayHasKey('image', $item);
		$this->assertArrayHasKey('user_id', $item);
		$this->assertArrayHasKey('user_name', $item);
		$this->assertArrayHasKey('created', $item);

		// Achievement + User joins filled correctly
		$this->assertNotEmpty($item['name']);
		$this->assertNotEmpty($item['image']);
		$this->assertNotEmpty($item['user_name']);

		// Tests simulate server in UTC. Verifies timezone offset is correct.
		$this->assertStringEndsWith('+00:00', $item['created'], "Expected UTC, got: {$item['created']}");
	}

	public function testGetRecentRespectsLimit(): void
	{
		$context = new ContextPreparator();
		$AchievementStatus = ClassRegistry::init('AchievementStatus');

		foreach ([2, 3, 4, 5, 6] as $i => $achievementId)
		{
			$AchievementStatus->create();
			$AchievementStatus->save([
				'AchievementStatus' => [
					'user_id' => $context->user['id'],
					'achievement_id' => $achievementId,
					'created' => date('Y-m-d H:i:s', strtotime("-{$i} hour")),
				],
			]);
		}

		$result = $AchievementStatus->getRecent(3);
		$this->assertCount(3, $result);
	}

	public function testGetRecentReturnsNewestFirst(): void
	{
		$context = new ContextPreparator();
		$AchievementStatus = ClassRegistry::init('AchievementStatus');

		$old = date('Y-m-d H:i:s', strtotime('-2 hours'));
		$new = date('Y-m-d H:i:s', strtotime('-1 hour'));

		$AchievementStatus->create();
		$AchievementStatus->save([
			'AchievementStatus' => [
				'user_id' => $context->user['id'],
				'achievement_id' => 10,
				'created' => $old,
			],
		]);

		$AchievementStatus->create();
		$AchievementStatus->save([
			'AchievementStatus' => [
				'user_id' => $context->user['id'],
				'achievement_id' => 11,
				'created' => $new,
			],
		]);

		$result = $AchievementStatus->getRecent(2);
		$this->assertCount(2, $result);
		$this->assertSame(11, (int) $result[0]['id'], 'Newest achievement should be first');
		$this->assertSame(10, (int) $result[1]['id'], 'Oldest achievement should be second');
	}
}
