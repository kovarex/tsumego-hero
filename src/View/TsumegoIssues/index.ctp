<?php

/**
 * Global issues list view.
 *
 * Shows all issues across all tsumegos with filtering options.
 *
 * Variables:
 * @var array $issues Array of issues with enriched data
 * @var string $statusFilter Current filter ('opened', 'closed', 'all')
 * @var int $openCount Number of open issues
 * @var int $closedCount Number of closed issues
 */

$statusFilter = $statusFilter ?? 'opened';
$openCount = $openCount ?? 0;
$closedCount = $closedCount ?? 0;
$totalCount = $openCount + $closedCount;
?>

<div class="issues-page">
	<h1>Tsumego Issues</h1>

	<p class="issues-page__description">
		Issues are reports about problems with tsumego solutions - missing moves, wrong answers, or other corrections needed.
	</p>

	<!-- Issues section with idiomorph for automatic DOM diffing -->
	<?php echo $this->element('TsumegoIssues/issues-section', compact('issues', 'statusFilter', 'openCount', 'closedCount')); ?>

	<!-- Pagination -->
	<?php if (!empty($this->Paginator)): ?>
		<div class="issues-page__pagination">
			<?php echo $this->Paginator->prev('« Previous'); ?>
			<?php echo $this->Paginator->numbers(); ?>
			<?php echo $this->Paginator->next('Next »'); ?>
		</div>
	<?php endif; ?>
</div>