<?php

use App\Utility\MistakeTraining;
use App\Utility\RungInsignia;
use App\Utility\TsumegoButton;

/**
 * Mistake Training - the overview
 * What is due today and the way into it, "All caught up!" once nothing is, and
 * "Nothing to review yet" to a player who never had a problem in the training;
 * below that what comes up next, the calendar of the profile, and the problems in
 * the training. A problem opens from there in the normal play page, except one still
 * due today, which shows as a locked "?" (MistakeTraining::allowsNormalView()).
 *
 * @var \App\View\AppView $this
 * @var int $dueToday
 * @var array|null $training What MistakeTraining::profile() returned
 * @var array $problems What MistakeTraining::overviewProblems() returned
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
	<?php if (!empty($problems)): ?>
	<details class="mistake-training-pool" open>
		<summary>Problems in training (<?php echo count($problems); ?>)</summary>
		<table class="mistake-training-pool__table">
			<thead><tr><th>Problem</th><th>Rank</th><th>Rung</th><th>Next review</th></tr></thead>
			<tbody>
			<?php foreach ($problems as $problem):
				[$kind, $symbols] = RungInsignia::of($problem['rung'], count(MistakeTraining::ladder()));
				$rungName = MistakeTraining::rungName($problem['rung']);
				// HTML: the day as a <time> the page formats
				if ($problem['due'])
					$nextReview = $problem['missed_today'] ? 'today, missed' : 'today';
				else
					$nextReview = MistakeTraining::describeDayWithDate(substr($problem['next_due'], 0, 10));
			?>
			<tr class="mistake-training-pool__row">
				<td>
					<div class="mistake-training-pool__problem">
						<ul class="problem-nav__buttons"><?php
							if ($problem['button'])
								$problem['button']->render();
							else
								TsumegoButton::renderLocked('Due today, opens once solved in today\'s training');
						?></ul>
						<?php if ($problem['button']): ?>
						<a class="mistake-training-pool__set" href="/sets/view/<?php echo $problem['set_id']; ?>"><?php echo h($problem['set_title']); ?></a>
						<?php endif; ?>
					</div>
				</td>
				<td class="mistake-training-pool__rank"><?php echo h($problem['rank']); ?></td>
				<td><span class="training-overview__insignia training-overview__insignia--<?php echo $kind; ?>" role="img" aria-label="<?php echo $rungName; ?>" title="<?php echo $rungName; ?>"><?php echo RungInsignia::render($kind, $symbols); ?></span></td>
				<td class="mistake-training-pool__next"><?php echo $nextReview; ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<?php endif; ?>
</div>

