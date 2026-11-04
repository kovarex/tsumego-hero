<?php

use App\Utility\Auth;
use App\Utility\DailyStreak;

/**
 * The flame of the daily streak in the menu of a signed in player: lit once today counted, dimmed
 * while it is still to do. Hovering or tapping it shows the streak and, until the day is done, what
 * today still asks, the XP as a bar (daily-streak--done hides it). It is read from the row Auth
 * loaded, so it costs no query; webroot/js/DailyStreak.js redraws it from the result of a solve, with
 * the same data (DailyStreak::view()) and the texts of the data- attributes, lights it with a burst
 * when that solve completed the day, and hands the server the browser's time zone when it differs
 * from the stored one.
 * @var \App\View\AppView $this
 */

$streak = DailyStreak::view(Auth::getUser());
$days = fn(int $count): string => $count . ' ' . ($count == 1 ? 'day' : 'days');
$doneText = 'Today is done.';
$todoText = 'To keep the streak today:';
?>
<div id="daily-streak" class="daily-streak<?php echo $streak['done_today'] ? ' daily-streak--done' : ''; ?>" tabindex="0"
	data-time-zone="<?php echo h(Auth::getUser()['time_zone'] ?? ''); ?>" aria-describedby="daily-streak-details">
	<svg class="daily-streak__flame" viewBox="0 0 24 24" aria-hidden="true">
		<path d="M12.6 1.8c.5 3.3-1.2 5.3-3 7.3C8 10.9 6.6 12.6 6.6 15.2a5.4 5.4 0 0 0 10.8 0c0-2.4-1.1-4.2-2.4-5.6.1 1.7-.6 2.9-1.6 3.4.5-3.5-.4-8-1.8-11.2z"/>
		<path class="daily-streak__spark" d="M18.5 2.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z"/>
		<circle class="daily-streak__burst" cx="12" cy="14" r="9"/>
	</svg>
	<span class="daily-streak__length" data-streak-count="length"><?php echo $streak['length']; ?></span>
	<div id="daily-streak-details" class="daily-streak__details" role="tooltip">
		<div class="daily-streak__title">Daily streak</div>
		<div>Streak: <span data-streak-days="length"><?php echo $days($streak['length']); ?></span>, best <span data-streak-days="best"><?php echo $days($streak['best']); ?></span></div>
		<div>Freezes: <span data-streak-count="freezes"><?php echo $streak['freezes']; ?></span></div>
		<div class="daily-streak__today" data-streak-today data-done-text="<?php echo h($doneText); ?>" data-todo-text="<?php echo h($todoText); ?>"><?php echo h($streak['done_today'] ? $doneText : $todoText); ?></div>
		<div class="daily-streak__progress-of-day">
			<div>XP: <span data-streak-count="xp"><?php echo $streak['xp']; ?></span> of <span data-streak-count="xp_required"><?php echo $streak['xp_required']; ?></span></div>
			<progress class="daily-streak__progress" aria-label="Today's XP" max="<?php echo $streak['xp_required']; ?>" value="<?php echo $streak['done_today'] ? $streak['xp_required'] : min($streak['xp'], $streak['xp_required']); ?>"></progress>
			<div>Mistake training reviews: <span data-streak-count="reviews"><?php echo $streak['reviews']; ?></span> of <span data-streak-count="reviews_required"><?php echo $streak['reviews_required']; ?></span></div>
		</div>
		<div class="daily-streak__rules">
			A day asks the XP of <?php echo DailyStreak::PROBLEMS_A_DAY; ?> problems of your rank and <?php echo DailyStreak::REVIEWS_A_DAY; ?> mistake training reviews, or as many as are due.
			A freeze is earned every <?php echo DailyStreak::FREEZE_EVERY_DAYS; ?> days and covers a missed day. A new day starts at <?php echo DailyStreak::DAY_START_HOUR; ?> AM.
		</div>
	</div>
</div>
