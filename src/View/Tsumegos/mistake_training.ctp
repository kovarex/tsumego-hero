<?php
/**
 * Mistake Training - "All caught up" view
 * Shown when no tsumegos are due for review.
 *
 * @var int $totalInTraining
 * @var array $upcomingDays
 * @var int $furtherDays
 * @var string|null $lastUpcomingDay
 */
?>
<div class="info-box info-box--empty">
	<h2 style="margin-bottom: var(--space-4);">Mistake Training</h2>
	<p class="info-box__icon" aria-hidden="true">✓</p>
	<p class="info-box__title">All caught up!</p>
	<?php if ($totalInTraining > 0): ?>
	<p class="hint" style="margin-bottom: var(--space-3);">
		<?php echo $totalInTraining; ?> problem<?php echo $totalInTraining == 1 ? '' : 's'; ?> in training.
	</p>
	<?php if (!empty($upcomingDays)): ?>
	<div class="hint">
		<div style="margin-bottom: var(--space-2);">Coming up</div>
		<?php foreach ($upcomingDays as $day => $count): ?>
		<?php
			$dayLabel = MistakeTraining::describeDay($day);
			$dayDate = date('M j', strtotime($day));
		?>
		<div>
			<?php echo $count; ?> review<?php echo $count == 1 ? '' : 's'; ?>
			<?php echo htmlspecialchars($dayLabel . ($dayLabel === $dayDate ? '' : ' (' . $dayDate . ')'), ENT_QUOTES, 'UTF-8'); ?>
		</div>
		<?php endforeach; ?>
		<?php if ($furtherDays > 0): ?>
		<div style="margin-top: var(--space-2);">
			and <?php echo $furtherDays; ?> more day<?php echo $furtherDays == 1 ? '' : 's'; ?>,
			the last one on <?php echo date('M j', strtotime($lastUpcomingDay)); ?>.
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php else: ?>
	<p class="hint">No problems in training yet. Miss a problem while solving and it comes back here for review.</p>
	<?php endif; ?>
</div>

