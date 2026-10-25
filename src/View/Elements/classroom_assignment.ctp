<?php

/**
 * One assignment of a class: its name, the collections its problems come from, the due day, and
 * the table of who solved what, which keeps itself up to date (app/classrooms/ClassroomProgress.tsx).
 * A draft only the teacher sees has no table yet.
 *
 * @var View $this
 * @var array $assignment A row of ClassroomAssignment::ofClassroom()
 * @var bool $isTeacher The teacher sees every pupil, and can edit and take the assignment back
 */

$isPublished = ClassroomAssignment::isPublished($assignment);
$collections = array_map(
	fn(array $collection) => '<a href="' . Set::url($collection['id']) . '">' . h($collection['title']) . '</a> '
		. '<span class="hint">' . $collection['problemCount'] . ($collection['problemCount'] == 1 ? ' problem' : ' problems') . '</span>',
	$assignment['collections']);
$props = json_encode([
	'assignmentId' => (int) $assignment['id'],
	'isTeacher' => $isTeacher,
]);
?>
<div class="card classes__assignment<?= $isPublished ? '' : ' classes__assignment--draft' ?>" data-assignment-id="<?= (int) $assignment['id'] ?>">
	<div class="classes__assignment-header">
		<span>
			<?php if (!$isPublished): ?>
			<span class="classes__badge">draft</span>
			<?php endif; ?>
			<span class="classes__assignment-title"><?= h(ClassroomAssignment::title($assignment)) ?></span>
		</span>
		<span class="classes__actions">
			<span class="hint">due <time datetime="<?= h($assignment['due']) ?>" data-format="date"><?= h($assignment['due']) ?></time></span>
			<?php if (!$isTeacher): ?>
			<a class="btn btn--small btn--success" href="<?= ClassroomAssignment::playLink((int) $assignment['id']) ?>">Solve</a>
			<?php else: ?>
			<a class="btn btn--small<?= $isPublished ? ' btn--neutral' : '' ?>" href="/classes/assignment/<?= (int) $assignment['id'] ?>"><?= $isPublished ? 'Edit' : 'Continue' ?></a>
			<form method="post" action="/classes/delete-assignment/<?= (int) $assignment['id'] ?>" onsubmit="return confirm('<?= $isPublished ? 'Take this assignment back?' : 'Throw this draft away?' ?>');">
				<button class="btn btn--small btn--neutral" type="submit">Remove</button>
			</form>
			<?php endif; ?>
		</span>
	</div>
	<?php if (!empty($collections)): ?>
	<p class="classes__assignment-collections"><?= implode(' · ', $collections) ?></p>
	<?php elseif (!$isPublished): ?>
	<p class="hint">No problems picked yet.</p>
	<?php endif; ?>
	<?php if ($isPublished): ?>
	<div data-classroom-progress-root data-props="<?= h($props) ?>"></div>
	<?php endif; ?>
</div>
