<?php

class Constants {
	public static int $MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED = 75;
	public static string $SGF_PLACEHOLDER = '(;GM[1]FF[4]CA[UTF-8]ST[2]RU[Japanese]SZ[19]PW[White]PB[Black])';

	public static int $LEVEL_MODE = 1;
	public static int $RATING_MODE = 2;
	public static int $TIME_MODE = 3;

	public static int $GOLDEN_TSUMEGO_XP_MULTIPLIER = 8;
	public static int $SPRINT_MULTIPLIER = 2;
	public static int $SPRINT_SECONDS = 120;
	public static float $RESOLVING_MULTIPLIER = 0.5;

	public static float $PLAYER_RATING_CALCULATION_MODIFIER = 0.5;
	public static float $TSUMEGO_RATING_CALCULATION_MODIFIER = 0.5;
}
