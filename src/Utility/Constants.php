<?php

class Constants
{
	public static int $MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED = 75;
	public static string $SGF_PLACEHOLDER = '(;GM[1]FF[4]CA[UTF-8]ST[2]RU[Japanese]SZ[19]PW[White]PB[Black])';

	// Default tsumego ID for first-time visitors
	public static int $DEFAULT_TSUMEGO_ID = 15352;

	public static int $DEFAULT_SET_ORDER = 999;

	public static int $LEVEL_MODE = 1;
	public static int $RATING_MODE = 2;
	public static int $TIME_MODE = 3;

	public static int $GOLDEN_TSUMEGO_XP_MULTIPLIER = 8;
	public static float $SECOND_SOLVE_XP_MULTIPLIER = 0.5;
	public static int $SPRINT_MULTIPLIER = 2;
	public static int $SPRINT_SECONDS = 120;
	public static float $RESOLVING_MULTIPLIER = 0.5;

	public static float $PLAYER_RATING_CALCULATION_MODIFIER = 0.5;
	public static float $TSUMEGO_RATING_CALCULATION_MODIFIER = 0.5;

	public static float $RATING_MODE_SELECTION_INTERVAL = 480;
	public static float $RATING_MODE_DIFFERENCE_SETTING_1 = 150;
	public static float $RATING_MODE_DIFFERENCE_SETTING_2 = 300;
	public static float $RATING_MODE_DIFFERENCE_SETTING_3 = 450;

	public static float $MINIMUM_RATING_TO_CONTRIBUTE = 1500;
}
