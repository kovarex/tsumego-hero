<?php

use App\Utility\MistakeTraining;

/**
 * Mistake Training - "All caught up" view
 * Shown when no tsumegos are due for review.
 *
 * @var int $totalInTraining
 * @var int $importCandidates
 * @var int $importLimit
 * @var bool $showDebugLink
 * @var array $upcomingDays
 * @var int $furtherDays
 * @var string|null $lastUpcomingDay
 * @var int $solvedToday
 * @var string|null $todaysTrainingLink
 */
?>
<div class="info-box info-box--empty">
	<h2 style="margin-bottom: var(--space-4);">Mistake Training</h2>
	<p class="info-box__icon" aria-hidden="true">✓</p>
	<p class="info-box__title">All caught up!</p>
	<?php if ($solvedToday > 0): ?>
	<p class="hint" style="margin-bottom: var(--space-3);">
		Today you solved <?php echo $solvedToday; ?> problem<?php echo $solvedToday == 1 ? '' : 's'; ?>.
		<a href="<?php echo htmlspecialchars((string) $todaysTrainingLink, ENT_QUOTES, 'UTF-8'); ?>">Look at today's training</a>
	</p>
	<?php endif; ?>
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
	<?php elseif ($solvedToday == 0): ?>
	<p class="hint">No problems in training yet. Solve a problem with a mistake and it comes back here for review.</p>
	<?php endif; ?>
	<?php if (!empty($importCandidates)): ?>
	<p class="hint" style="margin-top: var(--space-3);">
		Before the training existed you solved <?php echo $importCandidates; ?> problem<?php echo $importCandidates == 1 ? '' : 's'; ?>
		with a mistake. Bring in the <?php echo min($importLimit, $importCandidates); ?> most recent, and the rest will be
		here whenever you have nothing to review.
	</p>
	<form method="post" action="<?php echo MistakeTraining::$IMPORT_LINK; ?>">
		<button type="submit" class="btn">Bring <?php echo min($importLimit, $importCandidates); ?> in</button>
	</form>
	<?php endif; ?>
	<?php if (!empty($showDebugLink)): ?>
	<p class="hint" style="margin-top: var(--space-3);">
		<a href="<?php echo MistakeTraining::$DEBUG_LINK; ?>">Toolbox</a> (admins, debug mode only)
	</p>
	<?php endif; ?>
</div>

