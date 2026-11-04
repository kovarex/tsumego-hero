<?php

use App\Utility\Auth;
use App\Utility\DailyStreak;

/**
 * The flame of the daily streak in the menu of a signed in player: lit once today counted, dimmed
 * while it is still to do, golden in the golden mode. Hovering or tapping it shows both streaks and
 * what today still asks. It is read from the row Auth loaded, so it costs no query;
 * webroot/js/DailyStreak.js redraws it from the result of a solve, with the same data
 * (DailyStreak::view()) and the texts of the data- attributes, and hands the server the browser's
 * time zone when it differs from the stored one.
 * @var \App\View\AppView $this
 */

$streak = DailyStreak::view(Auth::getUser());
$days = fn(int $count): string => $count . ' ' . ($count == 1 ? 'day' : 'days');
$todoText = 'Solve a problem that gives XP, or a mistake training review, to keep it going today.';
$goldenText = 'Today: {xp} of {xp_required} XP and {reviews} of {reviews_required} mistake training reviews.';
$today = $streak['done_today'] ? 'Today is done.' : ($streak['golden'] ? strtr($goldenText, [
	'{xp}' => $streak['xp'], '{xp_required}' => $streak['xp_required'], '{reviews}' => $streak['reviews'], '{reviews_required}' => $streak['reviews_required']]) : $todoText);
?>
<div id="daily-streak" class="daily-streak<?php echo $streak['done_today'] ? ' daily-streak--done' : ''; ?><?php echo $streak['golden'] ? ' daily-streak--golden' : ''; ?>" tabindex="0"
	data-time-zone="<?php echo h(Auth::getUser()['time_zone'] ?? ''); ?>" aria-describedby="daily-streak-details">
	<svg class="daily-streak__flame" viewBox="0 0 24 24" aria-hidden="true">
		<path d="M12.6 1.8c.5 3.3-1.2 5.3-3 7.3C8 10.9 6.6 12.6 6.6 15.2a5.4 5.4 0 0 0 10.8 0c0-2.4-1.1-4.2-2.4-5.6.1 1.7-.6 2.9-1.6 3.4.5-3.5-.4-8-1.8-11.2z"/>
		<path class="daily-streak__spark" d="M18.5 2.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z"/>
	</svg>
	<span class="daily-streak__length" data-streak-active-length><?php echo $streak['golden'] ? $streak['golden_length'] : $streak['length']; ?></span>
	<div id="daily-streak-details" class="daily-streak__details" role="tooltip">
		<div class="daily-streak__title" data-streak-title data-normal-text="Daily streak" data-golden-text="Golden streak"><?php echo $streak['golden'] ? 'Golden streak' : 'Daily streak'; ?></div>
		<div>Streak: <span data-streak-days="length"><?php echo $days($streak['length']); ?></span>, best <span data-streak-days="best"><?php echo $days($streak['best']); ?></span></div>
		<div>Golden streak: <span data-streak-days="golden_length"><?php echo $days($streak['golden_length']); ?></span>, best <span data-streak-days="golden_best"><?php echo $days($streak['golden_best']); ?></span></div>
		<div>Freezes: <span data-streak-count="freezes"><?php echo $streak['freezes']; ?></span></div>
		<div class="daily-streak__today" data-streak-today data-done-text="Today is done." data-todo-text="<?php echo h($todoText); ?>" data-golden-text="<?php echo h($goldenText); ?>"><?php echo h($today); ?></div>
		<div class="daily-streak__rules">
			A golden day asks the XP of <?php echo DailyStreak::GOLDEN_PROBLEMS; ?> problems of your rank and <?php echo DailyStreak::GOLDEN_REVIEWS; ?> mistake training reviews, or as many as are due.
			A day counts in the mode you play, which you pick in the <a href="/users/settings">settings</a>; a missed day ends both streaks.
			A freeze is earned every <?php echo DailyStreak::FREEZE_EVERY_DAYS; ?> days and covers a missed day. A new day starts at <?php echo DailyStreak::DAY_START_HOUR; ?> AM.
		</div>
	</div>
</div>
