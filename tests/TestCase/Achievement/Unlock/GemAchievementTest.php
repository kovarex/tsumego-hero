<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class GemAchievementTest extends AchievementTestCase
{
	public function testEmeraldAchievement(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'emerald', 'value' => 1]]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::EMERALD, 'Emerald achievement should unlock when emerald condition = 1');
	}

	public function testSapphireAchievement(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'sapphire', 'value' => 1]]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::SAPPHIRE, 'Sapphire achievement should unlock when sapphire condition = 1');
	}

	public function testRubyAchievement(): void
	{
		$context = new ContextPreparator(['achievement-conditions' => [['category' => 'ruby', 'value' => 1]]]);
		$this->triggerAchievementCheck();
		$this->assertAchievementUnlocked(Achievement::RUBY, 'Ruby achievement should unlock when ruby condition = 1');
	}

	public function testDiamondAchievementUnlocksWhenAllGemsCollected()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'emerald', 'value' => 1],
				['category' => 'sapphire', 'value' => 1],
				['category' => 'ruby', 'value' => 1]
			]
		]);

		// Trigger check - should unlock emerald, sapphire, ruby first
		$this->triggerAchievementCheck();

		// Verify all three gems unlocked
		$this->assertAchievementUnlocked(Achievement::EMERALD, 'Emerald should be unlocked');
		$this->assertAchievementUnlocked(Achievement::SAPPHIRE, 'Sapphire should be unlocked');
		$this->assertAchievementUnlocked(Achievement::RUBY, 'Ruby should be unlocked');

		// Trigger check again - now Diamond should unlock
		$this->triggerAchievementCheck();

		// Assert diamond (114) unlocked
		$this->assertAchievementUnlocked(Achievement::DIAMOND, 'Diamond achievement should unlock when all three gems are unlocked');
	}

	public function testDiamondDoesNotUnlockWithoutAllGems()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'emerald', 'value' => 1],
				['category' => 'sapphire', 'value' => 1]
				// NO ruby - should not unlock diamond
			]
		]);

		// Trigger check
		$this->triggerAchievementCheck();

		// Assert emerald and sapphire unlocked
		$this->assertAchievementUnlocked(Achievement::EMERALD, 'Emerald should be unlocked');
		$this->assertAchievementUnlocked(Achievement::SAPPHIRE, 'Sapphire should be unlocked');

		// Assert ruby and diamond NOT unlocked
		$this->assertAchievementNotUnlocked( Achievement::RUBY);
		$this->assertAchievementNotUnlocked( Achievement::DIAMOND);
	}
}
