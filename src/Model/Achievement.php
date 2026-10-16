<?php

use App\Utility\AchievementId;
use App\Utility\HeroPowers;

class Achievement extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] = 'achievement';
		parent::__construct($id, $table, $ds);
	}

	/**
	 * Returns the progress counter definition for an achievement, or null if the
	 * achievement has no progress to show (binary unlock or derived elsewhere).
	 *
	 * @param int $achievementId
	 * @return array|null ['category' => string, 'goal' => int]
	 */
	public static function progressDefinition(int $achievementId): ?array
	{
		$definitions = [
			AchievementId::COMPLETE_SETS_I->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_I_SETS_COUNT],
			AchievementId::COMPLETE_SETS_II->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_II_SETS_COUNT],
			AchievementId::COMPLETE_SETS_III->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_III_SETS_COUNT],
			AchievementId::COMPLETE_SETS_IV->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_IV_SETS_COUNT],
			AchievementId::COMPLETE_SETS_V->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_V_SETS_COUNT],
			AchievementId::COMPLETE_SETS_VI->value => ['category' => 'set', 'goal' => self::COMPLETE_SETS_VI_SETS_COUNT],
			AchievementId::NO_ERROR_STREAK_I->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_I_STREAK_COUNT],
			AchievementId::NO_ERROR_STREAK_II->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_II_STREAK_COUNT],
			AchievementId::NO_ERROR_STREAK_III->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_III_STREAK_COUNT],
			AchievementId::NO_ERROR_STREAK_IV->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_IV_STREAK_COUNT],
			AchievementId::NO_ERROR_STREAK_V->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_V_STREAK_COUNT],
			AchievementId::NO_ERROR_STREAK_VI->value => ['category' => 'err', 'goal' => self::NO_ERROR_STREAK_VI_STREAK_COUNT],
			AchievementId::SPRINT->value => ['category' => 'sprint', 'goal' => self::SPRINT_COUNT],
			AchievementId::GOLD_DIGGER->value => ['category' => 'golden', 'goal' => self::GOLD_DIGGER_COUNT],
			AchievementId::BAD_POTION->value => ['category' => 'potion', 'goal' => HeroPowers::$BAD_POTION_THRESHOLD],
			AchievementId::SOLVE_10_1D->value => ['category' => 'danSolve1d', 'goal' => self::SOLVE_10_COUNT],
			AchievementId::SOLVE_10_2D->value => ['category' => 'danSolve2d', 'goal' => self::SOLVE_10_COUNT],
			AchievementId::SOLVE_10_3D->value => ['category' => 'danSolve3d', 'goal' => self::SOLVE_10_COUNT],
			AchievementId::SOLVE_10_4D->value => ['category' => 'danSolve4d', 'goal' => self::SOLVE_10_COUNT],
			AchievementId::SOLVE_10_5D->value => ['category' => 'danSolve5d', 'goal' => self::SOLVE_10_COUNT],
		];
		return $definitions[$achievementId] ?? null;
	}

	// Rarity thresholds (percentage of all users who completed the achievement)
	public const float RARITY_COMMON_PERCENT = 10;
	public const float RARITY_UNCOMMON_PERCENT = 1;
	public const float RARITY_RARE_PERCENT = 0.1;

	/**
	 * Returns the rarity label for an achievement completion percentage.
	 *
	 * @param float $completionPercent Percentage of all users who completed it.
	 * @return string
	 */
	public static function getRarityLabel(float $completionPercent): string
	{
		if ($completionPercent >= self::RARITY_COMMON_PERCENT)
			return 'Common';
		if ($completionPercent >= self::RARITY_UNCOMMON_PERCENT)
			return 'Uncommon';
		if ($completionPercent >= self::RARITY_RARE_PERCENT)
			return 'Rare';
		return 'Ultra rare';
	}

	// Complete Sets Achievements
	public const int COMPLETE_SETS_I_SETS_COUNT = 10;
	public const int COMPLETE_SETS_II_SETS_COUNT = 20;
	public const int COMPLETE_SETS_III_SETS_COUNT = 30;
	public const int COMPLETE_SETS_IV_SETS_COUNT = 40;
	public const int COMPLETE_SETS_V_SETS_COUNT = 50;
	public const int COMPLETE_SETS_VI_SETS_COUNT = 60;

	// No Error Streak Achievements
	public const int NO_ERROR_STREAK_I_STREAK_COUNT = 10;
	public const int NO_ERROR_STREAK_II_STREAK_COUNT = 20;
	public const int NO_ERROR_STREAK_III_STREAK_COUNT = 30;
	public const int NO_ERROR_STREAK_IV_STREAK_COUNT = 50;
	public const int NO_ERROR_STREAK_V_STREAK_COUNT = 100;
	public const int NO_ERROR_STREAK_VI_STREAK_COUNT = 200;

	// Sprint/Gold/Potion
	public const int SPRINT_COUNT = 30;
	public const int GOLD_DIGGER_COUNT = 10;

	// Multiple solves
	public const int SOLVE_10_COUNT = 10;

}
