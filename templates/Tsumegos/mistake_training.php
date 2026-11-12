<?php

use App\Utility\MistakeTraining;
use App\Utility\Rating;
use App\Utility\RungInsignia;

/**
 * Mistake Training - the overview
 * What is due today and the way into it, "All caught up!" once nothing is, and
 * "Nothing to review yet" to a player who never had a problem in the training;
 * below that what comes up next, and the calendar of the profile.
 *
 * @var \App\View\AppView $this
 * @var int $dueToday
 * @var array|null $training What MistakeTraining::profile() returned
 * @var array $poolRows What MistakeTraining::poolRows() returned
 * @var bool $everTrained
 * @var int $totalInTraining
 * @var array $upcomingDays
 * @var int $furtherDays
 * @var string|null $lastUpcomingDay
 * @var int $solvedToday
 * @var string|null $todaysTrainingLink
 */
?>
<div class="info-box info-box--empty">
	<h2 class="mistake-training__title">Mistake Training</h2>
	<?php if ($dueToday > 0): ?>
	<p class="info-box__title" id="mistake-training-due"><?php echo $dueToday; ?> problem<?php echo $dueToday == 1 ? '' : 's'; ?> to review today</p>
	<p class="mistake-training__block">
		<a class="btn" id="mistake-training-start" href="<?php echo MistakeTraining::$QUEUE_LINK; ?>"><?php echo $solvedToday > 0 ? 'Continue training' : 'Start training'; ?></a>
	</p>
	<?php elseif (!$everTrained): ?>
	<p class="info-box__title">Nothing to review yet</p>
	<p class="hint mistake-training__block">
		When you solve a problem but make a mistake first, it comes here. You review it the next day, and then at
		growing intervals until you know it.
	</p>
	<?php else: ?>
	<p class="info-box__icon" aria-hidden="true">✓</p>
	<p class="info-box__title">All caught up!</p>
	<?php endif; ?>
	<?php if ($solvedToday > 0): ?>
	<p class="hint mistake-training__block">
		Today you solved <?php echo $solvedToday; ?> problem<?php echo $solvedToday == 1 ? '' : 's'; ?>.
		<?php if ($dueToday == 0): ?>
		<a href="<?php echo htmlspecialchars((string) $todaysTrainingLink, ENT_QUOTES, 'UTF-8'); ?>">Look at today's training</a>
		<?php endif; ?>
	</p>
	<?php endif; ?>
	<?php if ($totalInTraining > 0): ?>
	<p class="hint mistake-training__block">
		<?php echo $totalInTraining; ?> problem<?php echo $totalInTraining == 1 ? '' : 's'; ?> in training.
	</p>
	<?php if (!empty($upcomingDays)): ?>
	<div class="hint">
		<div class="mistake-training__upcoming-title">Coming up</div>
		<?php foreach ($upcomingDays as $day => $count): ?>
		<div>
			<?php echo $count; ?> review<?php echo $count == 1 ? '' : 's'; ?>
			<?php echo MistakeTraining::describeDayWithDate($day); ?>
		</div>
		<?php endforeach; ?>
		<?php if ($furtherDays > 0): ?>
		<div class="mistake-training__further">
			and <?php echo $furtherDays; ?> more day<?php echo $furtherDays == 1 ? '' : 's'; ?>,
			the last one on <?php echo MistakeTraining::dayHtml($lastUpcomingDay); ?>.
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php elseif ($everTrained && $solvedToday == 0 && $dueToday == 0): ?>
	<p class="hint">Every problem you trained has graduated.</p>
	<?php endif; ?>
	<?php if ($dueToday == 0): ?>
	<p class="hint mistake-training__import">
		Problems you solved with a mistake before the training started can be brought in
		<?php echo MistakeTraining::IMPORT_LIMIT; ?> at a time, and reviewed like the rest.
	</p>
	<form method="post" action="<?php echo MistakeTraining::$IMPORT_LINK; ?>">
		<button type="submit" class="btn">Add past mistakes to training</button>
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
			<tr><th>Problem</th><th>Rank</th><th>Rung</th><th>Next review</th><th><span class="visually-hidden">Outside the training</span></th></tr>
			<?php foreach ($poolRows as $row):
				[$kind, $symbols] = RungInsignia::of($row['rung'], count(MistakeTraining::ladder()));
				$rungName = MistakeTraining::rungName($row['rung']);
				$problem = h($row['set_title']) . ' #' . $row['num'];
				//HTML: the day as a <time> the page formats
				if ($row['due'])
					$nextReview = $row['missed_today'] ? 'today, missed' : 'today';
				else
					$nextReview = MistakeTraining::describeDayWithDate(substr($row['next_due'], 0, 10));
			?>
			<tr data-tsumego="<?php echo $row['tsumego_id']; ?>">
				<td><?php echo $problem; ?></td>
				<td><?php echo Rating::getReadableRankFromRating($row['rating']); ?></td>
				<td><span class="training-overview__insignia training-overview__insignia--<?php echo $kind; ?>" role="img" aria-label="<?php echo $rungName; ?>" title="<?php echo $rungName; ?>"><?php echo RungInsignia::render($kind, $symbols); ?></span></td>
				<td><?php echo $nextReview; ?></td>
				<?php //A problem that is due opens only in the training, whose review its answer would give away ?>
				<td><?php if ($row['normal_view']): ?><a class="btn btn--small btn--navigation" href="/<?php echo $row['set_connection_id']; ?>" aria-label="Open <?php echo $problem; ?> outside the training" title="The problem's own page, outside the training">Open</a><?php endif; ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</details>
	<?php endif; ?>
</div>

