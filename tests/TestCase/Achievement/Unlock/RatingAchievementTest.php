<?php

use App\Utility\AchievementId;
use App\Utility\Rating;

/* These achievements unlock when user reaches specific rating thresholds:
 * - AchievementId::RATING_6_KYU
 * - AchievementId::RATING_5_KYU
 * - AchievementId::RATING_4_KYU
 * - AchievementId::RATING_3_KYU
 * - AchievementId::RATING_2_KYU
 * - AchievementId::RATING_1_KYU
 * - AchievementId::RATING_1_DAN
 * - AchievementId::RATING_2_DAN
 * - AchievementId::RATING_3_DAN
 * - AchievementId::RATING_4_DAN
 * - AchievementId::RATING_5_DAN */
class RatingAchievementTest extends AchievementTestCase
{
	public function testAchievementRating6KyuDoesUnlockBasedOnRating()
	{
		// One point below the threshold leaves the achievement locked, the threshold itself
		// and anything above it unlocks it.
		foreach ([-1, 0, 1] as $pointsFromThreshold)
		{
			$context = new ContextPreparator(['user' => ['rating' => (Rating::getRankMinimalRatingFromReadableRank('6k') + $pointsFromThreshold)]]);
			$this->triggerAchievementCheck();
			if ($pointsFromThreshold < 0)
				$this->assertAchievementNotUnlocked(AchievementId::RATING_6_KYU);
			else
				$this->assertAchievementUnlocked(AchievementId::RATING_6_KYU);
		}
	}

	public function testEveryRatingAchievementUnlocksAtItsThreshold()
	{
		$achievementRatings = [
			[AchievementId::RATING_6_KYU, Rating::getRankMinimalRatingFromReadableRank('6k')],
			[AchievementId::RATING_5_KYU, Rating::getRankMinimalRatingFromReadableRank('5k')],
			[AchievementId::RATING_4_KYU, Rating::getRankMinimalRatingFromReadableRank('4k')],
			[AchievementId::RATING_3_KYU, Rating::getRankMinimalRatingFromReadableRank('3k')],
			[AchievementId::RATING_2_KYU, Rating::getRankMinimalRatingFromReadableRank('2k')],
			[AchievementId::RATING_1_KYU, Rating::getRankMinimalRatingFromReadableRank('1k')],
			[AchievementId::RATING_1_DAN, Rating::getRankMinimalRatingFromReadableRank('1d')],
			[AchievementId::RATING_2_DAN, Rating::getRankMinimalRatingFromReadableRank('2d')],
			[AchievementId::RATING_3_DAN, Rating::getRankMinimalRatingFromReadableRank('3d')],
			[AchievementId::RATING_4_DAN, Rating::getRankMinimalRatingFromReadableRank('4d')],
			[AchievementId::RATING_5_DAN, Rating::getRankMinimalRatingFromReadableRank('5d')],
		];

		// One rating point below the threshold none of them is unlocked yet...
		foreach ($achievementRatings as [$achievementId, $requiredRating])
		{
			$context = new ContextPreparator(['user' => ['rating' => $requiredRating - 1]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementNotUnlocked($achievementId);
		}

		// ...and the threshold itself unlocks it.
		foreach ($achievementRatings as [$achievementId, $requiredRating])
		{
			$context = new ContextPreparator(['user' => ['rating' => $requiredRating]]);
			$this->triggerAchievementCheck();
			$this->assertAchievementUnlocked($achievementId, "Achievement {$achievementId->name} should unlock at rating $requiredRating");
		}
	}
}
