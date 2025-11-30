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

	<!-- Filter tabs -->
	<div class="issues-page__filters">
		<a href="/tsumego-issues?status=opened"
			class="issues-filter <?php echo $statusFilter === 'opened' ? 'issues-filter--active' : ''; ?>">
			🔴 Open (<?php echo $openCount; ?>)
		</a>
		<a href="/tsumego-issues?status=closed"
			class="issues-filter <?php echo $statusFilter === 'closed' ? 'issues-filter--active' : ''; ?>">
			✅ Closed (<?php echo $closedCount; ?>)
		</a>
		<a href="/tsumego-issues?status=all"
			class="issues-filter <?php echo $statusFilter === 'all' ? 'issues-filter--active' : ''; ?>">
			All (<?php echo $totalCount; ?>)
		</a>
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
			<?php foreach ($issues as $issue): ?>
				<?php
				$issueData = $issue['TsumegoIssue'];
				$author = $issue['Author'] ?? ['name' => '[deleted user]'];
				$tsumego = $issue['Tsumego'] ?? null;
				$set = $issue['Set'] ?? null;
				$tsumegoNum = $issue['TsumegoNum'] ?? null;
				$firstComment = $issue['FirstComment'] ?? null;
				$commentCount = $issue['CommentCount'] ?? 0;

				$statusId = $issueData['tsumego_issue_status_id'];
				$isOpened = $statusId == TsumegoIssue::$OPENED_STATUS;
				$statusName = TsumegoIssue::statusName($statusId);

				$createdDate = new DateTime($issueData['created']);
				$formattedDate = $createdDate->format('M. d, Y');

				// Build problem link
				$problemLink = $tsumego ? '/tsumegos/play/' . $tsumego['id'] : '#';
				$problemTitle = '';
				if ($set && $tsumegoNum)
					$problemTitle = $set['title'] . ' #' . $tsumegoNum;
				elseif ($tsumego)
					$problemTitle = 'Problem #' . $tsumego['id'];
				else
					$problemTitle = 'Unknown problem';
				?>

				<div class="issues-list__item <?php echo $isOpened ? 'issues-list__item--opened' : 'issues-list__item--closed'; ?>">
					<div class="issues-list__item-header">
						<span class="issues-list__badge <?php echo $isOpened ? 'badge--opened' : 'badge--closed'; ?>">
							<?php echo $isOpened ? '🔴' : '✅'; ?> <?php echo $statusName; ?>
						</span>

						<a href="<?php echo $problemLink; ?>#issue-<?php echo $issueData['id']; ?>" class="issues-list__title">
							Issue #<?php echo $issueData['id']; ?>
						</a>

						<span class="issues-list__meta">
							on <a href="<?php echo $problemLink; ?>"><?php echo h($problemTitle); ?></a>
							by <?php echo h($author['name']); ?>
							• <?php echo $formattedDate; ?>
							• <?php echo $commentCount; ?> comment<?php echo $commentCount !== 1 ? 's' : ''; ?>
						</span>
					</div>

					<?php if ($firstComment): ?>
						<div class="issues-list__preview">
							<?php echo h(mb_substr($firstComment['message'], 0, 200)); ?>
							<?php if (mb_strlen($firstComment['message']) > 200): ?>...<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="issues-list__actions">
						<a href="<?php echo $problemLink; ?>#issue-<?php echo $issueData['id']; ?>" class="btn btn--small">
							View Issue
						</a>

						<?php if (Auth::isAdmin() || (Auth::isLoggedIn() && Auth::getUserID() == $issueData['user_id'])): ?>
							<?php if ($isOpened): ?>
								<form method="post" action="/tsumego-issues/close/<?php echo $issueData['id']; ?>" style="display:inline;">
									<input type="hidden" name="data[Issue][redirect]" value="<?php echo $this->request->here; ?>">
									<button type="submit" class="btn btn--small btn--success">Close</button>
								</form>
							<?php endif; ?>
						<?php endif; ?>

						<?php if (Auth::isAdmin() && !$isOpened): ?>
							<form method="post" action="/tsumego-issues/reopen/<?php echo $issueData['id']; ?>" style="display:inline;">
								<input type="hidden" name="data[Issue][redirect]" value="<?php echo $this->request->here; ?>">
								<button type="submit" class="btn btn--small btn--warning">Reopen</button>
							</form>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Pagination -->
	<?php if (!empty($this->Paginator)): ?>
		<div class="issues-page__pagination">
			<?php echo $this->Paginator->prev('« Previous'); ?>
			<?php echo $this->Paginator->numbers(); ?>
			<?php echo $this->Paginator->next('Next »'); ?>
		</div>
	<?php endif; ?>
</div>