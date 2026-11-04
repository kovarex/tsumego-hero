<?php

use App\Utility\Auth;
use App\Utility\DailyStreak;

/**
 * The flame of the daily streak in the menu of a signed in player: lit once today counted, dimmed
 * while it is still to do. Hovering or tapping it shows the details. It is read from the row Auth
 * loaded, so it costs no query; webroot/js/DailyStreak.js keeps it up to date and sends the
 * browser's time zone when it differs from the stored one.
 * @var \App\View\AppView $this
 */

$streak = DailyStreak::ofUser(Auth::getUser());
$dayWord = fn(int $days): string => $days == 1 ? 'day' : 'days';
?>
<div id="daily-streak" class="daily-streak<?php echo $streak->doneToday ? ' daily-streak--done' : ''; ?>" tabindex="0"
	data-time-zone="<?php echo h(Auth::getUser()['time_zone'] ?? ''); ?>" aria-describedby="daily-streak-details">
	<svg class="daily-streak__flame" viewBox="0 0 24 24" aria-hidden="true">
		<path d="M12.6 1.8c.5 3.3-1.2 5.3-3 7.3C8 10.9 6.6 12.6 6.6 15.2a5.4 5.4 0 0 0 10.8 0c0-2.4-1.1-4.2-2.4-5.6.1 1.7-.6 2.9-1.6 3.4.5-3.5-.4-8-1.8-11.2z"/>
	</svg>
	<span class="daily-streak__length" data-streak-length><?php echo $streak->length; ?></span>
	<div id="daily-streak-details" class="daily-streak__details" role="tooltip">
		<div class="daily-streak__title">Daily streak: <span data-streak-length><?php echo $streak->length; ?></span> <span data-streak-length-word><?php echo $dayWord($streak->length); ?></span></div>
		<div>Best: <span data-streak-best><?php echo $streak->best; ?></span> <span data-streak-best-word><?php echo $dayWord($streak->best); ?></span></div>
		<div>Freezes: <span data-streak-freezes><?php echo $streak->freezes; ?></span></div>
		<div class="daily-streak__today" data-streak-today data-done-text="Today is done." data-todo-text="Solve a problem that gives XP, or a mistake training review, to keep it going today.">
			<?php echo $streak->doneToday ? 'Today is done.' : 'Solve a problem that gives XP, or a mistake training review, to keep it going today.'; ?>
		</div>
		<div class="daily-streak__rules">A freeze is earned every <?php echo DailyStreak::FREEZE_EVERY_DAYS; ?> days and covers a missed day. A new day starts at <?php echo DailyStreak::DAY_START_HOUR; ?> AM.</div>
	</div>
</div>
