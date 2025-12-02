<?php
/**
 * Combined issues section element for idiomorph updates.
 *
 * This element contains both the filter tabs AND the issues list.
 * When htmx uses morph swap, it updates the entire section and
 * idiomorph figures out what actually changed in the DOM.
 *
 * Uses the shared issue.ctp element for consistent display across
 * both the global issues list and play page.
 *
 * Variables:
 * @var array $issues Array of issues with enriched data (from findForIndex)
 * @var string $statusFilter Current filter ('opened', 'closed', 'all')
 * @var int $openCount Number of open issues
 * @var int $closedCount Number of closed issues
 * @var int $currentPage Current page number (1-indexed)
 * @var int $totalPages Total number of pages
 */

App::uses('TsumegoIssue', 'Model');

$statusFilter = $statusFilter ?? 'opened';
$openCount = $openCount ?? 0;
$closedCount = $closedCount ?? 0;
$totalCount = $openCount + $closedCount;
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
?>

<div id="issues-section" hx-ext="morph">
	<!-- Filter tabs -->
	<div class="issues-page__filters">
		<a href="/tsumego-issues?status=opened"
			hx-get="/tsumego-issues?status=opened"
			hx-target="#issues-section"
			hx-swap="morph:outerHTML"
			hx-push-url="true"
			hx-indicator="#issues-loading"
			class="issues-filter <?php echo $statusFilter === 'opened' ? 'issues-filter--active' : ''; ?>">
			🔴 Open (<?php echo $openCount; ?>)
		</a>
		<a href="/tsumego-issues?status=closed"
			hx-get="/tsumego-issues?status=closed"
			hx-target="#issues-section"
			hx-swap="morph:outerHTML"
			hx-push-url="true"
			hx-indicator="#issues-loading"
			class="issues-filter <?php echo $statusFilter === 'closed' ? 'issues-filter--active' : ''; ?>">
			✅ Closed (<?php echo $closedCount; ?>)
		</a>
		<a href="/tsumego-issues?status=all"
			hx-get="/tsumego-issues?status=all"
			hx-target="#issues-section"
			hx-swap="morph:outerHTML"
			hx-push-url="true"
			hx-indicator="#issues-loading"
			class="issues-filter <?php echo $statusFilter === 'all' ? 'issues-filter--active' : ''; ?>">
			All (<?php echo $totalCount; ?>)
		</a>
		<span id="issues-loading" class="htmx-indicator">Loading...</span>
	</div>

	<!-- Issues list -->
	<div class="issues-list">
		<?php if (empty($issues)): ?>
			<p class="issues-list__empty">
				<?php if ($statusFilter === 'opened'): ?>
					No open issues! 🎉
				<?php elseif ($statusFilter === 'closed'): ?>
					No closed issues yet.
				<?php else: ?>
					No issues found.
				<?php endif; ?>
			</p>
		<?php else: ?>
			<?php foreach ($issues as $issueItem): ?>
				<?php
				// Build problem link and title for the global list context
				$tsumegoId = $issueItem['tsumegoId'];
				$set = $issueItem['Set'] ?? null;
				$tsumegoNum = $issueItem['TsumegoNum'] ?? null;

				$problemLink = '/tsumegos/play/' . $tsumegoId;
				if ($set && $tsumegoNum)
					$problemTitle = $set['title'] . ' #' . $tsumegoNum;
				else
					$problemTitle = 'Problem #' . $tsumegoId;
				?>

				<!-- Problem reference header for global list -->
				<div class="issues-list__problem-ref">
					<a href="<?php echo $problemLink; ?>"><?php echo h($problemTitle); ?></a>
				</div>

				<?php
				// Use the shared issue.ctp element
				echo $this->element('TsumegoIssues/issue', [
					'issue' => $issueItem['issue'],
					'comments' => $issueItem['comments'],
					'author' => $issueItem['author'],
					'issueNumber' => $issueItem['issue']['id'], // Use issue ID in global list
					'tsumegoId' => $tsumegoId,
					// Global list context - htmx targets #issues-section
					'globalListContext' => true,
					'statusFilter' => $statusFilter,
					'currentPage' => $currentPage,
				]);
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Pagination -->
	<?php if ($totalPages > 1): ?>
		<div class="issues-page__pagination">
			<?php if ($currentPage > 1): ?>
				<a href="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $currentPage - 1; ?>"
					hx-get="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $currentPage - 1; ?>"
					hx-target="#issues-section"
					hx-swap="morph:outerHTML"
					hx-push-url="true"
					class="pagination-link pagination-link--prev">
					← Prev
				</a>
			<?php else: ?>
				<span class="pagination-link pagination-link--prev pagination-link--disabled">← Prev</span>
			<?php endif; ?>

			<?php
			// Show page numbers with ellipsis for large page counts
			$showPages = [];
			$showPages[] = 1; // Always show first

			// Pages around current
			for ($i = max(2, $currentPage - 2); $i <= min($totalPages - 1, $currentPage + 2); $i++)
				$showPages[] = $i;

			if ($totalPages > 1)
				$showPages[] = $totalPages; // Always show last

			$showPages = array_unique($showPages);
			sort($showPages);

			$lastShown = 0;
			foreach ($showPages as $pageNum):
				// Add ellipsis if there's a gap
				if ($lastShown && $pageNum - $lastShown > 1):
					?>
					<span class="pagination-ellipsis">…</span>
				<?php endif; ?>

				<?php if ($pageNum == $currentPage): ?>
					<span class="pagination-link pagination-link--active"><?php echo $pageNum; ?></span>
				<?php else: ?>
					<a href="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $pageNum; ?>"
						hx-get="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $pageNum; ?>"
						hx-target="#issues-section"
						hx-swap="morph:outerHTML"
						hx-push-url="true"
						class="pagination-link">
						<?php echo $pageNum; ?>
					</a>
				<?php endif; ?>
				<?php $lastShown = $pageNum; ?>
			<?php endforeach; ?>

			<?php if ($currentPage < $totalPages): ?>
				<a href="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $currentPage + 1; ?>"
					hx-get="/tsumego-issues?status=<?php echo h($statusFilter); ?>&page=<?php echo $currentPage + 1; ?>"
					hx-target="#issues-section"
					hx-swap="morph:outerHTML"
					hx-push-url="true"
					class="pagination-link pagination-link--next">
					Next →
				</a>
			<?php else: ?>
				<span class="pagination-link pagination-link--next pagination-link--disabled">Next →</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
