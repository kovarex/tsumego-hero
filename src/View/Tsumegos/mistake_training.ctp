<?php
/**
 * Mistake Training - "All caught up" view
 * Shown when no tsumegos are due for review.
 *
 * @var int $totalInTraining
 * @var array $upcomingByDay
 */
?>
<div class="info-box info-box--empty">
	<h2 style="margin-bottom: var(--space-4);">Mistake Training</h2>
	<p class="info-box__icon">✓</p>
	<p class="info-box__title">All caught up!</p>
	<?php if ($totalInTraining > 0): ?>
	<p class="hint" style="margin-bottom: var(--space-3);">
		<?php echo $totalInTraining; ?> problem<?php echo $totalInTraining == 1 ? '' : 's'; ?> in training.
	</p>
	<?php if (!empty($upcomingByDay)): ?>
	<div class="hint">
		<?php foreach ($upcomingByDay as $day => $count): ?>
		<div><?php echo $count; ?> due <?php echo date('M j', strtotime($day)); ?></div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php else: ?>
	<p class="hint">No problems in training yet. Make a mistake while solving to add one here.</p>
	<?php endif; ?>
</div>
