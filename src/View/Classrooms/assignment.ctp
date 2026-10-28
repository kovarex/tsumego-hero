<?php

/**
 * The page where the teacher builds an assignment of a class: picks problems from the
 * collections, names it, sets the due day and publishes it (app/classrooms/AssignmentEditor.tsx).
 *
 * @var View $this
 * @var \App\Model\Entity\Classroom $classroom
 * @var array $assignment
 * @var array $problems What ClassroomAssignment::problems() returned, with the previews
 */

$props = json_encode([
	'assignmentId' => (int) $assignment['id'],
	'classroomUrl' => '/classes/' . $classroom->id,
	'title' => (string) ($assignment['title'] ?? ''),
	'due' => $assignment['due'],
	'isPublished' => ClassroomAssignment::isPublished($assignment),
	'maximumProblems' => ClassroomAssignment::MAXIMUM_PROBLEMS,
	'maximumTitleLength' => ClassroomAssignment::MAXIMUM_TITLE_LENGTH,
	'problems' => $problems,
]);
?>
<div class="classes classes--wide">
	<div class="classes__header">
		<p class="classes__crumb"><a href="/classes">Classes</a> › <a href="/classes/<?= $classroom->id ?>"><?= h($classroom->name) ?></a></p>
		<h1 class="classes__title"><?= ClassroomAssignment::isPublished($assignment) ? 'Assignment' : 'New assignment' ?></h1>
	</div>
	<div data-assignment-editor-root data-props="<?= h($props) ?>"></div>
</div>
