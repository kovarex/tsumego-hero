<?php

App::uses('Achievement', 'Model');
App::uses('AppController', 'Controller');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'Test');

class GemAchievementTest extends AchievementTestCase
{
	public function testEmeraldAchievement()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'emerald', 'value' => 1]
			]
		]);

		// Trigger check
		$this->triggerAchievementCheck($context->user['id']);

		// Assert emerald (111) unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::EMERALD, 'Emerald achievement should unlock when emerald condition = 1');
	}

	public function testSapphireAchievement()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'sapphire', 'value' => 1]
			]
		]);

		// Trigger check
		$this->triggerAchievementCheck($context->user['id']);

		// Assert sapphire (112) unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::SAPPHIRE, 'Sapphire achievement should unlock when sapphire condition = 1');
	}

	public function testRubyAchievement()
	{
		$context = new ContextPreparator([
			'achievement-conditions' => [
				['category' => 'ruby', 'value' => 1]
			]
		]);

		// Trigger check
		$this->triggerAchievementCheck($context->user['id']);

		// Assert ruby (113) unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::RUBY, 'Ruby achievement should unlock when ruby condition = 1');
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
		$this->triggerAchievementCheck($context->user['id']);

		// Verify all three gems unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::EMERALD, 'Emerald should be unlocked');
		$this->assertAchievementUnlocked($context->user['id'], Achievement::SAPPHIRE, 'Sapphire should be unlocked');
		$this->assertAchievementUnlocked($context->user['id'], Achievement::RUBY, 'Ruby should be unlocked');

		// Trigger check again - now Diamond should unlock
		$this->triggerAchievementCheck($context->user['id']);

		// Assert diamond (114) unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::DIAMOND, 'Diamond achievement should unlock when all three gems are unlocked');
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
		$this->triggerAchievementCheck($context->user['id']);

		// Assert emerald and sapphire unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::EMERALD, 'Emerald should be unlocked');
		$this->assertAchievementUnlocked($context->user['id'], Achievement::SAPPHIRE, 'Sapphire should be unlocked');

		// Assert ruby and diamond NOT unlocked
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::RUBY);
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::DIAMOND);
	}
}
