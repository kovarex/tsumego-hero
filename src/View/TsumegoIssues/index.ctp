<?php

/**
 * Global issues list view.
 *
 * Shows all issues across all tsumegos with filtering options.
 * React fetches all data from API - no SSR needed.
 *
 * Variables:
 * @var string $statusFilter Current filter ('opened', 'closed', 'all')
 * @var int $currentPage Current page number (for initial state only)
 */

$statusFilter = $statusFilter ?? 'opened';
$currentPage = $currentPage ?? 1;
?>

<div class="issues-page">
	<h1>Tsumego Issues</h1>

	<p class="issues-page__description">
		Issues are reports about problems with tsumego solutions - missing moves, wrong answers, or other corrections needed.
	</p>

	<!-- React-based issues list with filters and pagination -->
	<?php echo $this->element('TsumegoIssues/section', compact(
		'statusFilter',
		'currentPage'
	)); ?>
</div>