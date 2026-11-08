<?php

use App\Utility\PrintSheet;

/**
 * Problems on paper: the picker of the classes and the options of the sheet, which opens in a new
 * tab (app/print/PrintSelection.tsx, PrintController).
 *
 * @var \App\View\AppView $this
 * @var array $problems What ClassroomAssignmentTable::pickablePayload() returned, with the previews
 */

$props = json_encode([
	'problems' => $problems,
	'problemsPerPageChoices' => PrintSheet::PROBLEMS_PER_PAGE,
	'defaultProblemsPerPage' => PrintSheet::DEFAULT_PROBLEMS_PER_PAGE,
	'maximumProblems' => PrintSheet::MAXIMUM_PROBLEMS,
]);
?>
<div class="classes classes--wide">
	<div class="classes__header">
		<h1 class="classes__title">Print problems</h1>
	</div>
	<div data-print-selection-root data-props="<?= h($props) ?>"></div>
</div>
