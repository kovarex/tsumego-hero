<?php

use App\Model\Table\UserTable;
use App\Utility\FriendActivity;
use App\Utility\Util;

/**
 * The box of the homepage with what the friends of the player did lately (FriendActivity).
 *
 * @var \App\View\AppView $this
 * @var FriendActivity $activity
 */
?>
<section class="card friend-activity" id="friend-activity" aria-labelledby="friend-activity-title">
	<h2 class="friend-activity__title" id="friend-activity-title"><a href="/friends">Friends' activity</a></h2>
	<?php if (!empty($activity->solvedToday)): ?>
	<p class="friend-activity__today">Solved today:
		<?php echo implode(', ', array_map(fn(array $entry) => UserTable::renderLink($entry['friend']) . ' ' . $entry['solved'], $activity->solvedToday)); ?>
	</p>
	<?php endif; ?>
	<?php if (empty($activity->events)): ?>
	<p class="hint">Nothing new from your friends in the last <?php echo FriendActivity::DAYS; ?> days.</p>
	<?php else: ?>
	<div class="friend-activity__events" role="list">
		<?php foreach ($activity->events as $event): ?>
		<div class="friend-activity__event friend-activity__event--<?php echo $event['kind']; ?>" role="listitem">
			<?php echo UserTable::renderLink($event['friend']); ?>
			<?php if ($event['kind'] === FriendActivity::ACHIEVEMENT): ?>
			unlocked <a href="/achievements/view/<?php echo $event['subject_id']; ?>"><?php echo h($event['subject_name']); ?></a>
			<?php elseif ($event['kind'] === FriendActivity::CHALLENGE): ?>
			played <a href="/challenges/<?php echo $event['subject_id']; ?>"><?php echo h($event['subject_name']); ?></a><?php if ($event['points'] !== null): ?>: <?php echo round($event['points'], 1); ?> points<?php endif; ?>
			<?php else: ?>
			reached a <?php echo h($event['subject_name']); ?>
			<?php endif; ?>
			<?php $isDay = strlen($event['happened']) === 10; ?>
			<time class="hint" datetime="<?php echo $isDay ? $event['happened'] : Util::toIso8601($event['happened']); ?>" data-format="<?php echo $isDay ? 'date' : 'datetime'; ?>"><?php echo h($event['happened']); ?></time>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</section>
