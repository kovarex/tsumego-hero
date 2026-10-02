<?php

/**
 * AchievementHelper - Renders the small achievement widgets that appear on
 * several pages.
 *
 * The same achievement is shown on the grid, on its detail page, on the profile
 * and in the achievement highscore, so the markup and its wording live here
 * instead of being repeated in every view.
 */
class AchievementHelper extends AppHelper
{
	/**
	 * Repeat count of a repeatable achievement, shown as a superscript next to a
	 * completion count, e.g. "110/114 +7". Empty when it was never repeated.
	 *
	 * @param int $repeats How often the achievement was earned on top of the first time.
	 */
	public static function renderRepeatCount(int $repeats): string
	{
		if ($repeats < 1)
			return '';
		return ' <span class="achievementRepeats" title="Extra completions of repeatable achievements">+'
			. $repeats . '</span>';
	}

	/**
	 * Badge with the number of times an achievement was earned, e.g. "3x".
	 * Empty when the achievement was earned only once.
	 */
	public static function renderEarnedBadge(int $earnedCount): string
	{
		if ($earnedCount < 2)
			return '';
		return '<div class="achievementEarnedCount" title="Earned ' . $earnedCount . ' times">'
			. $earnedCount . 'x</div>';
	}
}
