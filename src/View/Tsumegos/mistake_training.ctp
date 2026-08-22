<?php
/**
 * Mistake Training - "All caught up" view
 * Shown when no tsumegos are due for review.
 *
 * @var int $totalInTraining
 * @var array $upcomingByDay
 */
?>
<div style="max-width: 600px; margin: 100px auto; text-align: center;">
	<h2 style="margin-bottom: 16px;">Mistake Training</h2>
	<div style="padding: 40px; background: var(--info-box-background, #f9f9f9); border-radius: 12px; border: 1px solid var(--current-border-color, #ddd);">
		<p style="font-size: 48px; margin: 0 0 16px 0;">✓</p>
		<p style="font-size: 18px; color: var(--text-color, #333); margin: 0 0 8px 0;">
			All caught up!
		</p>
		<?php if ($totalInTraining > 0): ?>
		<p style="font-size: 14px; color: var(--text-softer-color, #888); margin: 0 0 10px 0;">
			<?php echo $totalInTraining; ?> problem<?php echo $totalInTraining == 1 ? '' : 's'; ?> in training.
		</p>
		<?php if (!empty($upcomingByDay)): ?>
		<div style="font-size: 14px; color: var(--text-softer-color, #888);">
			<?php foreach ($upcomingByDay as $day => $count): ?>
			<div><?php echo $count; ?> due <?php echo date('M j', strtotime($day)); ?></div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<?php else: ?>
		<p style="font-size: 14px; color: var(--text-softer-color, #888); margin: 0;">
			No problems in training yet. Make a mistake while solving to add one here.
		</p>
		<?php endif; ?>
	</div>
</div>
