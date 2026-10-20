<?php

use App\Utility\MistakeTraining;
use App\Utility\RungInsignia;

/**
 * The mistake training of a player on the profile: a calendar of the training days
 * of the last half a year, a column a week like the contribution calendar of
 * GitHub (cleared days in green, days trained but not cleared in light green, days
 * missed with a red edge), the problems in the training now by rung, and the
 * streaks of cleared days.
 *
 * Usage: $this->element('mistake_training_profile', ['training' => MistakeTraining::profile(...)])
 * and 'title' for a heading other than "Mistake Training".
 *
 * @var View $this
 * @var string|null $title
 * @var array{days: array<string, array{reviewed: int, cleared: bool, missed: bool}>, rungs: list<int>, graduated: int, streak: int, best_streak: int} $training
 */

$today = MistakeTraining::today();
$start = MistakeTraining::profileStart();
$rungCount = count($training['rungs']);
$inTraining = array_sum($training['rungs']);
$weeks = intdiv((int) round((strtotime($today) - strtotime($start)) / 86400), 7) + 1;
$describeStreak = fn(int $days): string => $days . ' ' . ($days == 1 ? 'day' : 'days');

// The name of a month goes over the week of its first day, like GitHub's, and the
// month the calendar starts in only when it has room before the next one.
$months = [];
for ($week = 0; $week < $weeks; $week++)
	for ($weekday = 0; $weekday < 7; $weekday++)
	{
		$day = strtotime($start . ' +' . ($week * 7 + $weekday) . ' days');
		if (date('j', $day) == 1)
			$months[$week] = date('M', $day);
	}
if (!isset($months[0]) && (empty($months) || min(array_keys($months)) >= 3))
	$months = [0 => date('M', strtotime($start))] + $months;
?>
<section class="training-overview" id="mistake-training-overview" aria-labelledby="mistake-training-overview-title">
	<h2 class="training-overview__title" id="mistake-training-overview-title"><?php echo htmlspecialchars($title ?? 'Mistake Training', ENT_QUOTES, 'UTF-8'); ?></h2>
	<div class="training-overview__stats">
		<div class="training-overview__stat">
			<span class="training-overview__value" id="mistake-training-in-training"><?php echo $inTraining; ?></span>
			<span class="training-overview__label">In training</span>
		</div>
		<div class="training-overview__stat">
			<span class="training-overview__value" id="mistake-training-graduated"><?php echo $training['graduated']; ?></span>
			<span class="training-overview__label">Graduated</span>
		</div>
		<div class="training-overview__stat">
			<span class="training-overview__value" id="mistake-training-streak"><?php echo $describeStreak($training['streak']); ?></span>
			<span class="training-overview__label" title="Cleared days in a row. A day with nothing to review keeps the streak.">Current streak</span>
		</div>
		<div class="training-overview__stat">
			<span class="training-overview__value" id="mistake-training-best-streak"><?php echo $describeStreak($training['best_streak']); ?></span>
			<span class="training-overview__label">Best streak</span>
		</div>
	</div>
	<ul class="training-overview__rungs" aria-label="Problems in training by rung">
		<?php foreach ($training['rungs'] as $rung => $count):
			[$kind, $symbols] = RungInsignia::of($rung, $rungCount); ?>
		<li class="training-overview__rung" title="<?php echo MistakeTraining::rungName($rung) . ': ' . $count; ?>">
			<span class="training-overview__insignia training-overview__insignia--<?php echo $kind; ?>" role="img" aria-label="<?php echo MistakeTraining::rungName($rung); ?>"><?php echo RungInsignia::render($kind, $symbols); ?></span>
			<span class="training-overview__count"><?php echo $count; ?></span>
		</li>
		<?php endforeach; ?>
		<li class="training-overview__rung" title="Graduated: <?php echo $training['graduated']; ?>">
			<span class="training-overview__insignia training-overview__insignia--graduated" role="img" aria-label="Graduated"><?php echo RungInsignia::renderCrown(); ?></span>
			<span class="training-overview__count"><?php echo $training['graduated']; ?></span>
		</li>
	</ul>
	<div class="training-calendar" role="figure" aria-label="Training days of the last six months">
		<div class="training-calendar__grid" style="--weeks: <?php echo $weeks; ?>">
			<?php foreach ($months as $week => $month): ?>
			<span class="training-calendar__month" style="grid-column: <?php echo $week + 2; ?> / span 3"><?php echo $month; ?></span>
			<?php endforeach; ?>
			<?php foreach ([1 => 'Mon', 3 => 'Wed', 5 => 'Fri'] as $weekday => $name): ?>
			<span class="training-calendar__weekday" style="grid-row: <?php echo $weekday + 1; ?>"><?php echo $name; ?></span>
			<?php endforeach; ?>
			<?php for ($index = 0; $index < $weeks * 7; $index++):
				$day = date('Y-m-d', strtotime($start . ' +' . $index . ' days'));
				if ($day > $today)
					break;
				$trained = $training['days'][$day] ?? null;
				if ($trained === null)
					[$state, $description] = ['', 'no training'];
				elseif ($trained['cleared'])
					[$state, $description] = [' training-calendar__day--cleared', $trained['reviewed'] . ' reviewed, cleared'];
				elseif ($trained['reviewed'] > 0)
					[$state, $description] = [' training-calendar__day--trained' . ($trained['missed'] ? ' training-calendar__day--missed' : ''), $trained['reviewed'] . ' reviewed, not cleared'];
				elseif ($trained['missed'])
					[$state, $description] = [' training-calendar__day--missed', 'missed'];
				else
					[$state, $description] = ['', 'no training'];
			?>
			<span class="training-calendar__day<?php echo $state; ?>" style="grid-column: <?php echo intdiv($index, 7) + 2; ?>; grid-row: <?php echo $index % 7 + 2; ?>" data-day="<?php echo $day; ?>" title="<?php echo date('M j, Y', strtotime($day)) . ': ' . $description; ?>"></span>
			<?php endfor; ?>
		</div>
	</div>
	<script>
		// On a narrow screen the calendar scrolls, and opens on the latest weeks
		document.currentScript.previousElementSibling.scrollLeft = 1e6;
	</script>
	<div class="training-calendar__legend">
		<span class="training-calendar__key"><span class="training-calendar__day training-calendar__day--cleared"></span>Cleared</span>
		<span class="training-calendar__key"><span class="training-calendar__day training-calendar__day--trained"></span>Trained, not cleared</span>
		<span class="training-calendar__key"><span class="training-calendar__day training-calendar__day--missed"></span>Missed</span>
		<span class="training-calendar__key"><span class="training-calendar__day"></span>No training</span>
	</div>
</section>
