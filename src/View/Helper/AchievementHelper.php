<?php

namespace App\View\Helper;

//Renders the small achievement widgets that appear on several pages.
//The same achievement is shown on the grid, on its detail page, on the profile
//and in the achievement highscore, so the markup and its wording live here
//instead of being repeated in every view.
class AchievementHelper extends AppHelper
{
	//How often the repeatable achievements were earned, as a superscript next to a
	//completion count, e.g. "110/114 +2". The sign and the number stay apart on
	//purpose: the summary is not about one achievement, so it does not read "2x".
	//Empty while nothing was earned twice.
	//$earnedTimes: How often the achievements that can be earned repeatedly were earned.
	public static function renderEarnedTimes(int $earnedTimes): string
	{
		if ($earnedTimes < 2)
			return '';
		return ' <span class="achievementEarnedTimes" title="Earned ' . $earnedTimes . ' times">+'
			. $earnedTimes . '</span>';
	}

	//Badge with the number of times an achievement was earned, e.g. "3x".
	//Empty when the achievement was earned only once.
	public static function renderEarnedBadge(int $earnedCount): string
	{
		if ($earnedCount < 2)
			return '';
		return '<div class="achievementEarnedCount" title="Earned ' . $earnedCount . ' times">'
			. $earnedCount . 'x</div>';
	}
}
