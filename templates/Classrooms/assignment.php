<?php

use App\Model\Table\ClassroomAssignmentTable;

/**
 * The page where the teacher builds an assignment of a class: picks problems from the
 * collections, names it, sets the due day and publishes it (app/classrooms/AssignmentEditor.tsx).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Classroom $classroom
 * @var array $assignment
 * @var array $problems What ClassroomAssignmentTable::problems() returned, with the previews
 */

$props = json_encode([
	'assignmentId' => (int) $assignment['id'],
	'classroomUrl' => '/classes/' . $classroom->id,
	'title' => (string) ($assignment['title'] ?? ''),
	'due' => $assignment['due'],
	'isPublished' => ClassroomAssignmentTable::isPublished($assignment),
	'maximumProblems' => ClassroomAssignmentTable::MAXIMUM_PROBLEMS,
	'maximumTitleLength' => ClassroomAssignmentTable::MAXIMUM_TITLE_LENGTH,
	'problems' => $problems,
]);
?>
<div class="classes classes--wide">
	<div class="classes__header">
		<p class="classes__crumb"><a href="/classes">Classes</a> › <a href="/classes/<?= $classroom->id ?>"><?= h($classroom->name) ?></a></p>
		<h1 class="classes__title"><?= ClassroomAssignmentTable::isPublished($assignment) ? 'Assignment' : 'New assignment' ?></h1>
	</div>
	<div data-assignment-editor-root data-props="<?= h($props) ?>"></div>
</div>
