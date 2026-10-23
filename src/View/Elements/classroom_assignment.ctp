<?php

/**
 * One assignment of a class: the collection and its problem numbers, the due day, and the table of
 * who solved what, which keeps itself up to date (app/classrooms/ClassroomProgress.tsx).
 *
 * @var View $this
 * @var array $assignment A row of ClassroomAssignment::ofClassroom()
 * @var bool $isTeacher The teacher sees every pupil and can take the assignment back
 */

$firstNumber = $assignment['first_number'];
$lastNumber = $assignment['last_number'];
if ($firstNumber !== null && $lastNumber !== null)
	$range = $firstNumber == $lastNumber ? 'problem ' . $firstNumber : 'problems ' . $firstNumber . '–' . $lastNumber;
elseif ($firstNumber !== null)
	$range = 'from problem ' . $firstNumber;
elseif ($lastNumber !== null)
	$range = 'up to problem ' . $lastNumber;
else
	$range = null;
$props = json_encode([
	'assignmentId' => (int) $assignment['id'],
	'isTeacher' => $isTeacher,
]);
?>
<div class="card classes__assignment" data-assignment-id="<?= (int) $assignment['id'] ?>">
	<div class="classes__assignment-header">
		<span>
			<a class="classes__assignment-title" href="<?= Set::url((int) $assignment['set_id']) ?>"><?= h($assignment['set_title']) ?></a>
			<?php if ($range !== null): ?>
			<span class="hint"><?= $range ?></span>
			<?php endif; ?>
		</span>
		<span class="classes__actions">
			<span class="hint">due <time datetime="<?= h($assignment['due']) ?>" data-format="date"><?= h($assignment['due']) ?></time></span>
			<?php if ($isTeacher): ?>
			<form method="post" action="/classes/delete-assignment/<?= (int) $assignment['id'] ?>" onsubmit="return confirm('Take this assignment back?');">
				<button class="btn btn--small btn--neutral" type="submit">Remove</button>
			</form>
			<?php endif; ?>
		</span>
	</div>
	<div data-classroom-progress-root data-props="<?= h($props) ?>"></div>
</div>
