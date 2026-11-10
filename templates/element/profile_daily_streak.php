<?php

use App\Utility\DailyStreak;

/**
 * The daily streak of the player on their profile, for anyone who opens it: the streak as the menu
 * flame reads it (missed days already used up freezes or ended it), the longest one and the freezes
 * held. The flame is lit once today counted. A player who never had a streak gets one quiet line.
 * @var \App\View\AppView $this
 * @var array $user The row of the player (UserTable::row())
 */

$streak = DailyStreak::ofUser($user);
$days = fn(int $count): string => $count . ' ' . ($count == 1 ? 'day' : 'days');
?>
<table class="stat profile-streak" id="streak-info-table">
	<tr>
		<td>Daily streak:</td>
		<?php if ($streak->length == 0 && $streak->best == 0): ?>
		<td class="profile-streak__none">No streak yet</td>
		<?php else: ?>
		<td>
			<span class="profile-streak__current<?php echo $streak->doneToday ? ' profile-streak__current--done' : ''; ?>"
				title="<?php echo $streak->doneToday ? 'Today is done' : 'Today is not done yet'; ?>">
				<?php //The flame of the menu (element/daily_streak.php) ?>
				<svg class="profile-streak__flame" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.6 1.8c.5 3.3-1.2 5.3-3 7.3C8 10.9 6.6 12.6 6.6 15.2a5.4 5.4 0 0 0 10.8 0c0-2.4-1.1-4.2-2.4-5.6.1 1.7-.6 2.9-1.6 3.4.5-3.5-.4-8-1.8-11.2z"/></svg><?php echo $days($streak->length); ?>
			</span>
		</td>
		<?php endif; ?>
	</tr>
	<?php if ($streak->best > 0): ?>
	<tr>
		<td>Best streak:</td>
		<td><?php echo $days($streak->best); ?></td>
	</tr>
	<tr>
		<td>Freezes:</td>
		<td><?php echo $streak->freezes; ?></td>
	</tr>
	<?php endif; ?>
</table>
