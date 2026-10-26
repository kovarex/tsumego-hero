<?php

namespace App\Utility;

/**
 * The achievements of the site, as the ids the achievement table knows them by. An
 * achievement that asks for a number of something keeps that number elsewhere, so a
 * rule can not be mistaken for an id.
 */
enum AchievementId: int
{
	// Solved Count Achievements
	case PROBLEMS_1000 = 1;
	case PROBLEMS_2000 = 2;
	case PROBLEMS_3000 = 3;
	case PROBLEMS_4000 = 4;
	case PROBLEMS_5000 = 5;
	case PROBLEMS_6000 = 6;
	case PROBLEMS_7000 = 7;
	case PROBLEMS_8000 = 8;
	case PROBLEMS_9000 = 9;
	case PROBLEMS_10000 = 10;

	// Special Awards
	case USER_OF_THE_DAY = 11;

	// Accuracy Achievements
	case ACCURACY_I = 12; // <1300, 75%;
	case ACCURACY_II = 13; // <1300, 85%;
	case ACCURACY_III = 14; // <1300, 95%;
	case ACCURACY_IV = 15; // 1300-1499, 75%;
	case ACCURACY_V = 16; // 1300-1499, 85%;
	case ACCURACY_VI = 17; // 1300-1499, 95%;
	case ACCURACY_VII = 18; // 1500-1699, 75%;
	case ACCURACY_VIII = 19; // 1500-1699, 85%;
	case ACCURACY_IX = 20; // 1500-1699, 95%;
	case ACCURACY_X = 21; // >=1700, 75%;
	case ACCURACY_XI = 22; // >=1700, 85%;
	case ACCURACY_XII = 23; // >=1700, 95%;

	// Speed Achievements
	case SPEED_I = 24; // <1300, <20s;
	case SPEED_II = 25; // <1300, <10s;
	case SPEED_III = 26; // <1300, <5s;
	case SPEED_IV = 27; // 1300-1499, <18s;
	case SPEED_V = 28; // 1300-1499, <13s;
	case SPEED_VI = 29; // 1300-1499, <8s;
	case SPEED_VII = 30; // 1500-1699, <30s;
	case SPEED_VIII = 31; // 1500-1699, <20s;
	case SPEED_IX = 32; // 1500-1699, <10s;
	case SPEED_X = 33; // >=1700, <30s;
	case SPEED_XI = 34; // >=1700, <20s;
	case SPEED_XII = 35; // >=1700, <10s;

	// Level Achievements
	case LEVEL_UP = 36;
	case FIRST_HERO_POWER = 37;
	case UPGRADED_INTUITION = 38;
	case MORE_POWER = 39;
	case HALF_WAY_TO_TOP = 40;
	case CONGRATS_MORE_PROBLEMS = 41;
	case NICE_LEVEL = 42;
	case DID_LOT_OF_TSUMEGO = 43;
	case STILL_DOING_TSUMEGO = 44;
	case THE_TOP = 45;

	// Superior Accuracy
	case SUPERIOR_ACCURACY = 46; // 100% accuracy on 100+ tsumegos;

	// Complete Sets Achievements
	case COMPLETE_SETS_I = 47;
	case COMPLETE_SETS_II = 48;
	case COMPLETE_SETS_III = 49;
	case COMPLETE_SETS_IV = 50;
	case COMPLETE_SETS_V = 51;
	case COMPLETE_SETS_VI = 52;

	// No Error Streak Achievements
	case NO_ERROR_STREAK_I = 53;
	case NO_ERROR_STREAK_II = 54;
	case NO_ERROR_STREAK_III = 55;
	case NO_ERROR_STREAK_IV = 56;
	case NO_ERROR_STREAK_V = 57;
	case NO_ERROR_STREAK_VI = 58;

	// Rating Achievements
	case RATING_6_KYU = 59;
	case RATING_5_KYU = 60;
	case RATING_4_KYU = 61;
	case RATING_3_KYU = 62;
	case RATING_2_KYU = 63;
	case RATING_1_KYU = 64;
	case RATING_1_DAN = 65;
	case RATING_2_DAN = 66;
	case RATING_3_DAN = 67;
	case RATING_4_DAN = 68;
	case RATING_5_DAN = 69;

	// Slow mode
	case TIME_MODE_APPRENTICE_SLOW = 70;
	case TIME_MODE_SCHOLAR_SLOW = 71;
	case TIME_MODE_LABOURER_SLOW = 72;
	case TIME_MODE_ADEPT_SLOW = 73;
	case TIME_MODE_EXPERT_SLOW = 74;
	case TIME_MODE_MASTER_SLOW = 75;

	// Fast mode
	case TIME_MODE_APPRENTICE_FAST = 76;
	case TIME_MODE_SCHOLAR_FAST = 77;
	case TIME_MODE_LABOURER_FAST = 78;
	case TIME_MODE_ADEPT_FAST = 79;
	case TIME_MODE_EXPERT_FAST = 80;
	case TIME_MODE_MASTER_FAST = 81;

	// Blitz mode
	case TIME_MODE_APPRENTICE_BLITZ = 82;
	case TIME_MODE_SCHOLAR_BLITZ = 83;
	case TIME_MODE_LABOURER_BLITZ = 84;
	case TIME_MODE_ADEPT_BLITZ = 85;
	case TIME_MODE_EXPERT_BLITZ = 86;
	case TIME_MODE_MASTER_BLITZ = 87;

	// Time Mode Precision
	case TIME_MODE_PRECISION_I = 88;
	case TIME_MODE_PRECISION_II = 89;
	case TIME_MODE_PRECISION_III = 90;
	case TIME_MODE_PRECISION_IV = 91;

	// Special Sets
	case LIFE_DEATH_ELEMENTARY = 92;
	case LIFE_DEATH_INTERMEDIATE = 93;
	case LIFE_DEATH_ADVANCED = 94;
	case WEIQI_1000_FIRST_HALF = 95;
	case WEIQI_1000_SECOND_HALF = 115;

	// Sprint/Gold/Potion
	case SPRINT = 96; // sprint >= 30;
	case GOLD_DIGGER = 97; // golden >= 10;
	case BAD_POTION = 98;

	// Favorites
	case FAVORITES = 99;

	// Single solve
	case SOLVE_1D = 101;
	case SOLVE_2D = 102;
	case SOLVE_3D = 103;
	case SOLVE_4D = 104;
	case SOLVE_5D = 105;

	// Multiple solves
	case SOLVE_10_1D = 106;
	case SOLVE_10_2D = 107;
	case SOLVE_10_3D = 108;
	case SOLVE_10_4D = 109;
	case SOLVE_10_5D = 110;

	// Gem Achievements
	case EMERALD = 111;
	case SAPPHIRE = 112;
	case RUBY = 113;
	case DIAMOND = 114;

	/**
	 * The number of achievements there are.
	 */
	public static function count(): int
	{
		return count(self::cases());
	}
}
