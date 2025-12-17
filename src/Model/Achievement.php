<?php

class Achievement extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'achievement';
		parent::__construct($id, $table, $ds);
	}

	// Solved Count Achievements
	public const int PROBLEMS_1000 = 1;
	public const int PROBLEMS_2000 = 2;
	public const int PROBLEMS_3000 = 3;
	public const int PROBLEMS_4000 = 4;
	public const int PROBLEMS_5000 = 5;
	public const int PROBLEMS_6000 = 6;
	public const int PROBLEMS_7000 = 7;
	public const int PROBLEMS_8000 = 8;
	public const int PROBLEMS_9000 = 9;
	public const int PROBLEMS_10000 = 10;

	// Special Awards
	public const int USER_OF_THE_DAY = 11;

	// Accuracy Achievements
	public const int ACCURACY_I = 12;   // <1300, 75%
	public const int ACCURACY_II = 13;  // <1300, 85%
	public const int ACCURACY_III = 14; // <1300, 95%
	public const int ACCURACY_IV = 15;  // 1300-1499, 75%
	public const int ACCURACY_V = 16;   // 1300-1499, 85%
	public const int ACCURACY_VI = 17;  // 1300-1499, 95%
	public const int ACCURACY_VII = 18; // 1500-1699, 75%
	public const int ACCURACY_VIII = 19; // 1500-1699, 85%
	public const int ACCURACY_IX = 20;  // 1500-1699, 95%
	public const int ACCURACY_X = 21;   // >=1700, 75%
	public const int ACCURACY_XI = 22;  // >=1700, 85%
	public const int ACCURACY_XII = 23; // >=1700, 95%

	// Speed Achievements
	public const int SPEED_I = 24;   // <1300, <20s
	public const int SPEED_II = 25;  // <1300, <10s
	public const int SPEED_III = 26; // <1300, <5s
	public const int SPEED_IV = 27;  // 1300-1499, <18s
	public const int SPEED_V = 28;   // 1300-1499, <13s
	public const int SPEED_VI = 29;  // 1300-1499, <8s
	public const int SPEED_VII = 30; // 1500-1699, <30s
	public const int SPEED_VIII = 31; // 1500-1699, <20s
	public const int SPEED_IX = 32;  // 1500-1699, <10s
	public const int SPEED_X = 33;   // >=1700, <30s
	public const int SPEED_XI = 34;  // >=1700, <20s
	public const int SPEED_XII = 35; // >=1700, <10s

	// Level Achievements
	public const int LEVEL_UP = 36;
	public const int FIRST_HERO_POWER = 37;
	public const int UPGRADED_INTUITION = 38;
	public const int MORE_POWER = 39;
	public const int HALF_WAY_TO_TOP = 40;
	public const int CONGRATS_MORE_PROBLEMS = 41;
	public const int NICE_LEVEL = 42;
	public const int DID_LOT_OF_TSUMEGO = 43;
	public const int STILL_DOING_TSUMEGO = 44;
	public const int THE_TOP = 45;

	// Superior Accuracy
	public const int SUPERIOR_ACCURACY = 46; // 100% accuracy on 100+ tsumegos

	// Complete Sets Achievements
	public const int COMPLETE_SETS_I = 47;
	public const int COMPLETE_SETS_I_SETS_COUNT = 10;
	public const int COMPLETE_SETS_II = 48;
	public const int COMPLETE_SETS_II_SETS_COUNT = 20;
	public const int COMPLETE_SETS_III = 49;
	public const int COMPLETE_SETS_III_SETS_COUNT = 30;
	public const int COMPLETE_SETS_IV = 50;
	public const int COMPLETE_SETS_IV_SETS_COUNT = 40;
	public const int COMPLETE_SETS_V = 51;
	public const int COMPLETE_SETS_V_SETS_COUNT = 50;
	public const int COMPLETE_SETS_VI = 52;
	public const int COMPLETE_SETS_VI_SETS_COUNT = 60;

	// No Error Streak Achievements
	public const int NO_ERROR_STREAK_I = 53;
	public const int NO_ERROR_STREAK_I_STREAK_COUNT = 10;
	public const int NO_ERROR_STREAK_II = 54;
	public const int NO_ERROR_STREAK_II_STREAK_COUNT = 20;
	public const int NO_ERROR_STREAK_III = 55;
	public const int NO_ERROR_STREAK_III_STREAK_COUNT = 30;
	public const int NO_ERROR_STREAK_IV = 56;
	public const int NO_ERROR_STREAK_IV_STREAK_COUNT = 50;
	public const int NO_ERROR_STREAK_V = 57;
	public const int NO_ERROR_STREAK_V_STREAK_COUNT = 100;
	public const int NO_ERROR_STREAK_VI = 58;
	public const int NO_ERROR_STREAK_VI_STREAK_COUNT = 200;

	// Rating Achievements
	public const int RATING_6_KYU = 59;
	public const int RATING_5_KYU = 60;
	public const int RATING_4_KYU = 61;
	public const int RATING_3_KYU = 62;
	public const int RATING_2_KYU = 63;
	public const int RATING_1_KYU = 64;
	public const int RATING_1_DAN = 65;
	public const int RATING_2_DAN = 66;
	public const int RATING_3_DAN = 67;
	public const int RATING_4_DAN = 68;
	public const int RATING_5_DAN = 69;

	// Time Mode Achievements
	// Slow mode
	public const int TIME_MODE_APPRENTICE_SLOW = 70;
	public const int TIME_MODE_SCHOLAR_SLOW = 71;
	public const int TIME_MODE_LABOURER_SLOW = 72;
	public const int TIME_MODE_ADEPT_SLOW = 73;
	public const int TIME_MODE_EXPERT_SLOW = 74;
	public const int TIME_MODE_MASTER_SLOW = 75;

	// Fast mode
	public const int TIME_MODE_APPRENTICE_FAST = 76;
	public const int TIME_MODE_SCHOLAR_FAST = 77;
	public const int TIME_MODE_LABOURER_FAST = 78;
	public const int TIME_MODE_ADEPT_FAST = 79;
	public const int TIME_MODE_EXPERT_FAST = 80;
	public const int TIME_MODE_MASTER_FAST = 81;
	// Blitz mode
	public const int TIME_MODE_APPRENTICE_BLITZ = 82;
	public const int TIME_MODE_SCHOLAR_BLITZ = 83;
	public const int TIME_MODE_LABOURER_BLITZ = 84;
	public const int TIME_MODE_ADEPT_BLITZ = 85;
	public const int TIME_MODE_EXPERT_BLITZ = 86;
	public const int TIME_MODE_MASTER_BLITZ = 87;
	// Time Mode Precision
	public const int TIME_MODE_PRECISION_I = 88;
	public const int TIME_MODE_PRECISION_II = 89;
	public const int TIME_MODE_PRECISION_III = 90;
	public const int TIME_MODE_PRECISION_IV = 91;

	// Special Sets
	public const int LIFE_DEATH_ELEMENTARY = 92;
	public const int LIFE_DEATH_INTERMEDIATE = 93;
	public const int LIFE_DEATH_ADVANCED = 94;
	public const int WEIQI_1000_FIRST_HALF = 95;
	public const int WEIQI_1000_SECOND_HALF = 115;

	// Sprint/Gold/Potion
	public const int SPRINT = 96;       // sprint >= 30
	public const int GOLD_DIGGER = 97;  // golden >= 10
	public const int BAD_POTION = 98;   // potion >= 1

	// Favorites
	public const int FAVORITES = 99;

	// Premium
	public const int PREMIUM = 100;

	// Dan Solve Achievements
	// Single solve
	public const int SOLVE_1D = 101;
	public const int SOLVE_2D = 102;
	public const int SOLVE_3D = 103;
	public const int SOLVE_4D = 104;
	public const int SOLVE_5D = 105;
	// Multiple solves
	public const int SOLVE_10_1D = 106;
	public const int SOLVE_10_2D = 107;
	public const int SOLVE_10_3D = 108;
	public const int SOLVE_10_4D = 109;
	public const int SOLVE_10_5D = 110;

	// Gem Achievements
	public const EMERALD = 111;
	public const SAPPHIRE = 112;
	public const RUBY = 113;
	public const DIAMOND = 114;
}
