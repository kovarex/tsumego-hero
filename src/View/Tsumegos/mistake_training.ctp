<?php

use App\Utility\MistakeTraining;
use App\Utility\RungInsignia;

/**
 * Mistake Training - the overview
 * What is due today and the way into it, "All caught up!" once nothing is, and
 * "Nothing to review yet" to a player who never had a problem in the training;
 * below that what comes up next, and the calendar and streak of the profile.
 *
 * @var View $this
 * @var int $dueToday
 * @var array|null $training What MistakeTraining::profile() returned
 * @var array $poolRows What MistakeTraining::poolRows() returned
 * @var bool $everTrained
 * @var int $totalInTraining
 * @var int $importCandidates
 * @var int $importLimit
 * @var array $upcomingDays
 * @var int $furtherDays
 * @var string|null $lastUpcomingDay
 * @var int $solvedToday
 * @var string|null $todaysTrainingLink
 */
?>
<div class="info-box info-box--empty">
	<h2 style="margin-bottom: var(--space-4);">Mistake Training</h2>
	<?php if ($dueToday > 0): ?>
	<p class="info-box__title" id="mistake-training-due"><?php echo $dueToday; ?> problem<?php echo $dueToday == 1 ? '' : 's'; ?> to review today</p>
	<p style="margin-bottom: var(--space-3);">
		<a class="btn" id="mistake-training-start" href="<?php echo MistakeTraining::$QUEUE_LINK; ?>"><?php echo $solvedToday > 0 ? 'Continue training' : 'Start training'; ?></a>
	</p>
	<?php elseif (!$everTrained): ?>
	<p class="info-box__title">Nothing to review yet</p>
	<p class="hint" style="margin-bottom: var(--space-3);">
		When you solve a problem but make a mistake first, it comes here. You review it the next day, and then at
		growing intervals until you know it.
	</p>
	<?php else: ?>
	<p class="info-box__icon" aria-hidden="true">✓</p>
	<p class="info-box__title">All caught up!</p>
	<?php endif; ?>
	<?php if ($solvedToday > 0): ?>
	<p class="hint" style="margin-bottom: var(--space-3);">
		Today you solved <?php echo $solvedToday; ?> problem<?php echo $solvedToday == 1 ? '' : 's'; ?>.
		<?php if ($dueToday == 0): ?>
		<a href="<?php echo htmlspecialchars((string) $todaysTrainingLink, ENT_QUOTES, 'UTF-8'); ?>">Look at today's training</a>
		<?php endif; ?>
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
	<?php elseif ($everTrained && $solvedToday == 0 && $dueToday == 0): ?>
	<p class="hint">Every problem you trained has graduated.</p>
	<?php endif; ?>
	<?php if (!empty($importCandidates)): ?>
	<p class="hint" style="margin-top: var(--space-3);">
		Before the training existed you solved <?php echo $importCandidates; ?><?php echo $importCandidates >= MistakeTraining::IMPORT_COUNT_CAP ? ' or more' : ''; ?>
		problem<?php echo $importCandidates == 1 ? '' : 's'; ?> with a mistake. Add the <?php echo min($importLimit, $importCandidates); ?> most recent, and the rest will be
		here whenever you have nothing to review.
	</p>
	<form method="post" action="<?php echo MistakeTraining::$IMPORT_LINK; ?>">
		<button type="submit" class="btn">Add <?php echo min($importLimit, $importCandidates); ?> to training</button>
	</form>
	<?php endif; ?>
	<?php if ($training !== null): ?>
	<div class="mistake-training-progress">
		<?php echo $this->element('mistake_training_profile', ['training' => $training, 'title' => 'Your progress']); ?>
	</div>
	<?php endif; ?>
	<?php if (!empty($poolRows)): ?>
	<details class="mistake-training-pool" open>
		<summary>Problems in training (<?php echo count($poolRows); ?>)</summary>
		<table class="mistake-training-pool__table">
			<tr><th>Problem</th><th>Rung</th><th>Next review</th></tr>
			<?php foreach ($poolRows as $row):
				[$kind, $symbols] = RungInsignia::of($row['rung'], count(MistakeTraining::ladder()));
				$rungName = MistakeTraining::rungName($row['rung']);
				$dueDay = substr($row['next_due'], 0, 10);
				if ($row['next_due'] <= date('Y-m-d H:i:s'))
					$nextReview = $row['missed_today'] ? 'today, missed' : 'today';
				else
				{
					$dayLabel = MistakeTraining::describeDay($dueDay);
					$dayDate = date('M j', strtotime($dueDay));
					$nextReview = $dayLabel . ($dayLabel === $dayDate ? '' : ' (' . $dayDate . ')');
				}
			?>
			<tr data-tsumego="<?php echo $row['tsumego_id']; ?>">
				<td><?php echo htmlspecialchars($row['set_title'], ENT_QUOTES, 'UTF-8'); ?> #<?php echo $row['num']; ?></td>
				<td><span class="training-overview__insignia training-overview__insignia--<?php echo $kind; ?>" role="img" aria-label="<?php echo $rungName; ?>" title="<?php echo $rungName; ?>"><?php echo RungInsignia::render($kind, $symbols); ?></span></td>
				<td><?php echo htmlspecialchars($nextReview, ENT_QUOTES, 'UTF-8'); ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</details>
	<?php endif; ?>
</div>

