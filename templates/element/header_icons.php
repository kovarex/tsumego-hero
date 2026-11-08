<?php

use App\Utility\Auth;
use App\Utility\MistakeTraining;
use App\Utility\Util;

/**
 * The icons of a signed in player in the menu bar, left of the daily streak and the account bar:
 * the hearts left, the mistake training with the number of its problems due for review and the
 * challenges with the number waiting to be played. They are always in sight, the play page included, so the player
 * sees what waits without opening a menu. A count of 0 shows no badge. The icons have no text;
 * their meaning is in the title and the label of each link.
 * @var \App\View\AppView $this
 * @var int $mistakeTrainingDue
 * @var int $challengesWaiting
 */

$health = Auth::getRemainingHealth();
$maxHealth = Util::getHealthBasedOnLevel((int) Auth::getUser()['level']);
$badge = fn(int $count): string => $count > 0 ? '<span class="header-icons__badge" aria-hidden="true">' . $count . '</span>' : '';
$mistakeTrainingLabel = $mistakeTrainingDue > 0
	? 'Mistake training: ' . $mistakeTrainingDue . ' problem' . ($mistakeTrainingDue === 1 ? '' : 's') . ' to review today'
	: 'Mistake training: nothing to review today';
$challengesLabel = $challengesWaiting > 0
	? 'Challenges: ' . $challengesWaiting . ' waiting for you to play ' . ($challengesWaiting === 1 ? 'it' : 'them')
	: 'Challenges';
?>
<div class="header-icons">
	<?php //The play page redraws the count after a fail or a restore (PlayBoard.js redrawHearts()) ?>
	<div id="header-hearts" class="header-icons__hearts" title="Hearts: <?php echo $health . ' of ' . $maxHealth; ?>" aria-label="Hearts: <?php echo $health . ' of ' . $maxHealth; ?>">
		<svg class="header-icons__icon" viewBox="0 0 24 24" aria-hidden="true">
			<path d="M12 21s-7.5-4.6-9.5-9.3C1.1 8.4 3 5 6.4 5c2 0 3.6 1.1 4.6 2.6h2C14 6.1 15.6 5 17.6 5 21 5 22.9 8.4 21.5 11.7 19.5 16.4 12 21 12 21z"/>
		</svg>
		<span class="header-icons__count" data-health-counter><?php echo $health; ?></span>
	</div>
	<a id="header-mistake-training" class="header-icons__link" href="<?php echo MistakeTraining::$OVERVIEW_LINK; ?>" title="<?php echo h($mistakeTrainingLabel); ?>" aria-label="<?php echo h($mistakeTrainingLabel); ?>">
		<svg class="header-icons__icon" viewBox="0 0 24 24" aria-hidden="true">
			<path d="M12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35A7.96 7.96 0 0 0 12 4z"/>
		</svg>
		<?php echo $badge($mistakeTrainingDue); ?>
	</a>
	<a id="header-challenges" class="header-icons__link" href="/challenges" title="<?php echo h($challengesLabel); ?>" aria-label="<?php echo h($challengesLabel); ?>">
		<svg class="header-icons__icon" viewBox="0 0 24 24" aria-hidden="true">
			<g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
				<path d="M4 4l10 10M12 16l4-4M14 14l5 5"/>
				<path d="M20 4L10 14M8 12l4 4M10 14l-5 5"/>
			</g>
		</svg>
		<?php echo $badge($challengesWaiting); ?>
	</a>
</div>
