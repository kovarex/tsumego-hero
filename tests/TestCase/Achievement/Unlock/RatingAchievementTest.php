<?php

App::uses('Achievement', 'Model');
App::uses('AchievementTestCase', 'TestCase/Achievement');
App::uses('ContextPreparator', 'TestCase');

/* These achievements unlock when user reaches specific rating thresholds:
 * - Achievement::RATING_6_KYU
 * - Achievement::RATING_5_KYU
 * - Achievement::RATING_4_KYU
 * - Achievement::RATING_3_KYU
 * - Achievement::RATING_2_KYU
 * - Achievement::RATING_1_KYU
 * - Achievement::RATING_1_DAN
 * - Achievement::RATING_2_DAN
 * - Achievement::RATING_3_DAN
 * - Achievement::RATING_4_DAN
 * - Achievement::RATING_5_DAN */
class RatingAchievementTest extends AchievementTestCase
{
	public function testAchievementRating6KyuDoesUnlockBasedOnRating()
	{
		foreach ([0, -1] as $ratingDefitient)
		{
			$context = new ContextPreparator(['user' => ['rating' => (Rating::getRankMinimalRatingFromReadableRank('6k') - $ratingDefitient)]]);
			$this->triggerAchievementCheck();
			if ($ratingDefitient > 0)
				$this->assertAchievementNotUnlocked(Achievement::RATING_6_KYU);
			else
				$this->assertAchievementUnlocked(Achievement::RATING_6_KYU);
		}
	}

	public function testAllRatingAchievementsUnlockAtCorrectTresholds()
	{
		$achievementRatings = [
			Achievement::RATING_6_KYU => Rating::getRankMinimalRatingFromReadableRank('6k'),
			Achievement::RATING_5_KYU => Rating::getRankMinimalRatingFromReadableRank('5k'),
			Achievement::RATING_4_KYU => Rating::getRankMinimalRatingFromReadableRank('4k'),
			Achievement::RATING_3_KYU => Rating::getRankMinimalRatingFromReadableRank('3k'),
			Achievement::RATING_2_KYU => Rating::getRankMinimalRatingFromReadableRank('2k'),
			Achievement::RATING_1_KYU => Rating::getRankMinimalRatingFromReadableRank('1k'),
			Achievement::RATING_1_DAN => Rating::getRankMinimalRatingFromReadableRank('1d'),
			Achievement::RATING_2_DAN => Rating::getRankMinimalRatingFromReadableRank('2d'),
			Achievement::RATING_3_DAN => Rating::getRankMinimalRatingFromReadableRank('3d'),
			Achievement::RATING_4_DAN => Rating::getRankMinimalRatingFromReadableRank('4d'),
			Achievement::RATING_5_DAN => Rating::getRankMinimalRatingFromReadableRank('5d'),
		];

		foreach ($achievementRatings as $achievementId => $requiredRating)
		{
			$context = new ContextPreparator(['user' => ['rating' => $requiredRating]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId, "Achievement $achievementId should unlock at rating $requiredRating");
		}
	}
}
