<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/**
 * Test Rating Achievements (IDs 59-69)
 *
 * These achievements unlock when user reaches specific rating thresholds:
 * - Achievement 59: Rating 1500
 * - Achievement 60: Rating 1600
 * - Achievement 61: Rating 1700
 * - Achievement 62: Rating 1800
 * - Achievement 63: Rating 1900
 * - Achievement 64: Rating 2000
 * - Achievement 65: Rating 2100
 * - Achievement 66: Rating 2200
 * - Achievement 67: Rating 2300
 * - Achievement 68: Rating 2400
 * - Achievement 69: Rating 2500
 */
class RatingAchievementTest extends AchievementTestCase
{
	/**
	 * Test that achievement 59 unlocks at rating 1500
	 */
	public function testAchievement59UnlocksAtRating1500()
	{
		// Arrange: Create user with rating 1500
		$context = new ContextPreparator([
			'user' => ['rating' => 1500],
		]);

		// Act: Trigger achievement check
		$this->triggerAchievementCheck($context->user['id']);

		// Assert: Achievement 59 should be unlocked
		$this->assertAchievementUnlocked($context->user['id'], Achievement::RATING_6_KYU);
	}

	/**
	 * Test that achievement 59 does NOT unlock below rating 1500
	 */
	public function testAchievement59DoesNotUnlockBelowRating1500()
	{
		// Arrange: Create user with rating 1499
		$context = new ContextPreparator([
			'user' => ['rating' => 1499],
		]);

		// Act: Trigger achievement check
		$this->triggerAchievementCheck($context->user['id']);

		// Assert: Achievement 59 should NOT be unlocked
		$this->assertAchievementNotUnlocked($context->user['id'], Achievement::RATING_6_KYU);
	}

	/**
	 * Test all rating achievements unlock at correct thresholds
	 */
	public function testAllRatingAchievements()
	{
		$achievementRatings = [
			Achievement::RATING_6_KYU => 1500,
			Achievement::RATING_5_KYU => 1600,
			Achievement::RATING_4_KYU => 1700,
			Achievement::RATING_3_KYU => 1800,
			Achievement::RATING_2_KYU => 1900,
			Achievement::RATING_1_KYU => 2000,
			Achievement::RATING_1_DAN => 2100,
			Achievement::RATING_2_DAN => 2200,
			Achievement::RATING_3_DAN => 2300,
			Achievement::RATING_4_DAN => 2400,
			Achievement::RATING_5_DAN => 2500,
		];

		foreach ($achievementRatings as $achievementId => $requiredRating)
		{
			// Arrange: Create user with required rating
			$context = new ContextPreparator([
				'user' => ['rating' => $requiredRating],
			]);

			// Act: Trigger achievement check
			$this->triggerAchievementCheck($context->user['id']);

			// Assert: Achievement should be unlocked
			$this->assertAchievementUnlocked(
				$context->user['id'],
				$achievementId,
				"Achievement $achievementId should unlock at rating $requiredRating"
			);
		}
	}
}
