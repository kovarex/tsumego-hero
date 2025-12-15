<?php

class Achievement extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] = 'achievement';
		parent::__construct($id, $table, $ds);
	}

	// Solved Count Achievements (1-10)
	public const PROBLEMS_1000 = 1;
	public const PROBLEMS_2000 = 2;
	public const PROBLEMS_3000 = 3;
	public const PROBLEMS_4000 = 4;
	public const PROBLEMS_5000 = 5;
	public const PROBLEMS_6000 = 6;
	public const PROBLEMS_7000 = 7;
	public const PROBLEMS_8000 = 8;
	public const PROBLEMS_9000 = 9;
	public const PROBLEMS_10000 = 10;

	// Special Awards (11)
	public const USER_OF_THE_DAY = 11;

	// Accuracy Achievements (12-23)
	public const ACCURACY_I = 12;   // <1300, 75%
	public const ACCURACY_II = 13;  // <1300, 85%
	public const ACCURACY_III = 14; // <1300, 95%
	public const ACCURACY_IV = 15;  // 1300-1499, 75%
	public const ACCURACY_V = 16;   // 1300-1499, 85%
	public const ACCURACY_VI = 17;  // 1300-1499, 95%
	public const ACCURACY_VII = 18; // 1500-1699, 75%
	public const ACCURACY_VIII = 19; // 1500-1699, 85%
	public const ACCURACY_IX = 20;  // 1500-1699, 95%
	public const ACCURACY_X = 21;   // >=1700, 75%
	public const ACCURACY_XI = 22;  // >=1700, 85%
	public const ACCURACY_XII = 23; // >=1700, 95%

	// Speed Achievements (24-35)
	public const SPEED_I = 24;   // <1300, <20s
	public const SPEED_II = 25;  // <1300, <10s
	public const SPEED_III = 26; // <1300, <5s
	public const SPEED_IV = 27;  // 1300-1499, <18s
	public const SPEED_V = 28;   // 1300-1499, <13s
	public const SPEED_VI = 29;  // 1300-1499, <8s
	public const SPEED_VII = 30; // 1500-1699, <30s
	public const SPEED_VIII = 31; // 1500-1699, <20s
	public const SPEED_IX = 32;  // 1500-1699, <10s
	public const SPEED_X = 33;   // >=1700, <30s
	public const SPEED_XI = 34;  // >=1700, <20s
	public const SPEED_XII = 35; // >=1700, <10s

	// Level Achievements (36-45)
	public const LEVEL_UP = 36;
	public const FIRST_HERO_POWER = 37;
	public const UPGRADED_INTUITION = 38;
	public const MORE_POWER = 39;
	public const HALF_WAY_TO_TOP = 40;
	public const CONGRATS_MORE_PROBLEMS = 41;
	public const NICE_LEVEL = 42;
	public const DID_LOT_OF_TSUMEGO = 43;
	public const STILL_DOING_TSUMEGO = 44;
	public const THE_TOP = 45;

	// Superior Accuracy (46)
	public const SUPERIOR_ACCURACY = 46; // 100% accuracy on 100+ tsumegos

	// Complete Sets Achievements (47-52)
	public const COMPLETE_SETS_I = 47;   // 1 set
	public const COMPLETE_SETS_II = 48;  // 3 sets
	public const COMPLETE_SETS_III = 49; // 10 sets
	public const COMPLETE_SETS_IV = 50;  // 30 sets
	public const COMPLETE_SETS_V = 51;   // 100 sets
	public const COMPLETE_SETS_VI = 52;  // 300 sets

	// No Error Streak Achievements (53-58)
	public const NO_ERROR_STREAK_I = 53;   // 10 streak
	public const NO_ERROR_STREAK_II = 54;  // 20 streak
	public const NO_ERROR_STREAK_III = 55; // 30 streak
	public const NO_ERROR_STREAK_IV = 56;  // 50 streak
	public const NO_ERROR_STREAK_V = 57;   // 100 streak
	public const NO_ERROR_STREAK_VI = 58;  // 200 streak

	// Rating Achievements (59-69)
	public const RATING_6_KYU = 59;
	public const RATING_5_KYU = 60;
	public const RATING_4_KYU = 61;
	public const RATING_3_KYU = 62;
	public const RATING_2_KYU = 63;
	public const RATING_1_KYU = 64;
	public const RATING_1_DAN = 65;
	public const RATING_2_DAN = 66;
	public const RATING_3_DAN = 67;
	public const RATING_4_DAN = 68;
	public const RATING_5_DAN = 69;

	// Time Mode Achievements (70-91)
	// Slow mode (70-75)
	public const TIME_MODE_APPRENTICE_SLOW = 70;
	public const TIME_MODE_SCHOLAR_SLOW = 71;
	public const TIME_MODE_LABOURER_SLOW = 72;
	public const TIME_MODE_ADEPT_SLOW = 73;
	public const TIME_MODE_EXPERT_SLOW = 74;
	public const TIME_MODE_MASTER_SLOW = 75;
	// Fast mode (76-81)
	public const TIME_MODE_APPRENTICE_FAST = 76;
	public const TIME_MODE_SCHOLAR_FAST = 77;
	public const TIME_MODE_LABOURER_FAST = 78;
	public const TIME_MODE_ADEPT_FAST = 79;
	public const TIME_MODE_EXPERT_FAST = 80;
	public const TIME_MODE_MASTER_FAST = 81;
	// Blitz mode (82-87)
	public const TIME_MODE_APPRENTICE_BLITZ = 82;
	public const TIME_MODE_SCHOLAR_BLITZ = 83;
	public const TIME_MODE_LABOURER_BLITZ = 84;
	public const TIME_MODE_ADEPT_BLITZ = 85;
	public const TIME_MODE_EXPERT_BLITZ = 86;
	public const TIME_MODE_MASTER_BLITZ = 87;
	// Time Mode Precision (88-91)
	public const TIME_MODE_PRECISION_I = 88;
	public const TIME_MODE_PRECISION_II = 89;
	public const TIME_MODE_PRECISION_III = 90;
	public const TIME_MODE_PRECISION_IV = 91;

	// Special Sets (92-95, 115)
	public const LIFE_DEATH_ELEMENTARY = 92;
	public const LIFE_DEATH_INTERMEDIATE = 93;
	public const LIFE_DEATH_ADVANCED = 94;
	public const WEIQI_1000_FIRST_HALF = 95;
	public const WEIQI_1000_SECOND_HALF = 115;

	// Sprint/Gold/Potion (96-98)
	public const SPRINT = 96;       // sprint >= 30
	public const GOLD_DIGGER = 97;  // golden >= 10
	public const BAD_POTION = 98;   // potion >= 1

	// Favorites (99)
	public const FAVORITES = 99;

	// Premium (100)
	public const PREMIUM = 100;

	// Dan Solve Achievements (101-110)
	// Single solve (101-105)
	public const SOLVE_1D = 101;
	public const SOLVE_2D = 102;
	public const SOLVE_3D = 103;
	public const SOLVE_4D = 104;
	public const SOLVE_5D = 105;
	// Multiple solves (106-110)
	public const SOLVE_10_1D = 106;
	public const SOLVE_10_2D = 107;
	public const SOLVE_10_3D = 108;
	public const SOLVE_10_4D = 109;
	public const SOLVE_10_5D = 110;

	// Gem Achievements (111-114)
	public const EMERALD = 111;
	public const SAPPHIRE = 112;
	public const RUBY = 113;
	public const DIAMOND = 114;
}
