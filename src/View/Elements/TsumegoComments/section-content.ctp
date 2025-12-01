<?php

/**
 * Comments section content - the morphable container for htmx responses.
 *
 * This is the inner content that gets morphed when comments are added/deleted
 * or issues are closed/reopened. The outer wrapper (section.ctp) is only
 * rendered on initial page load.
 *
 * Includes the header, collapsible content, tabs, items, and form.
 *
 * Variables:
 * @var array $issues Array of issues with their comments (from Tsumego::loadCommentsData)
 * @var array $plainComments Array of comments not belonging to any issue
 * @var int $tsumegoId The tsumego ID
 * @var int $totalCount Total count (optional, calculated if not provided)
 * @var int $commentCount Standalone comments count (optional, calculated if not provided)
 * @var int $issueCount Issues count (optional, calculated if not provided)
 * @var int $openIssueCount Open issues count (optional, calculated if not provided)
 */

// Default values
$issues = $issues ?? [];
$plainComments = $plainComments ?? [];
$tsumegoId = $tsumegoId ?? 0;

// Use provided counts or calculate them
if (!isset($issueCount))
	$issueCount = count($issues);
if (!isset($commentCount))
	$commentCount = count($plainComments);
if (!isset($totalCount))
	$totalCount = $issueCount + $commentCount;

// Count open issues if not provided
if (!isset($openIssueCount))
{
	$openIssueCount = 0;
	foreach ($issues as $issue)
		if ($issue['tsumego_issue_status_id'] == TsumegoIssue::$OPENED_STATUS)
			$openIssueCount++;
}

// Count closed issues
$closedIssueCount = $issueCount - $openIssueCount;

// "Open" tab count = standalone comments + open issues
$openTabCount = $commentCount + $openIssueCount;

// Combine and sort by date for "open" view (comments + open issues)
$allItems = [];

// Add issues - use EARLIEST comment's date for sorting (not issue creation date)
foreach ($issues as $index => $issue)
{
	// Find earliest comment creation date
	$sortDate = $issue['created']; // Fallback to issue creation date
	if (!empty($issue['comments']))
	{
		$earliestCommentDate = $issue['comments'][0]['created']; // Comments are ordered ASC
		if (strtotime($earliestCommentDate) < strtotime($sortDate))
			$sortDate = $earliestCommentDate;
	}
	
	$allItems[] = [
		'type' => 'issue',
		'created' => $sortDate, // Use earliest comment date for sorting
		'data' => $issue,
		'issueNumber' => $index + 1,
	];
}

// Add standalone comments
foreach ($plainComments as $index => $comment)
{
	$allItems[] = [
		'type' => 'comment',
		'created' => $comment['created'],
		'data' => $comment,
		'index' => $index + 1,
	];
}

// Sort by created date (oldest first)
usort($allItems, function ($a, $b) {
	return strtotime($a['created']) - strtotime($b['created']);
});

$isEmpty = empty($allItems);
$canDragComment = Auth::isAdmin();
?>
<!-- Morphable container - htmx targets this for all comment actions -->
<div id="comments-section-<?php echo $tsumegoId; ?>" hx-ext="morph" data-tsumego-id="<?php echo $tsumegoId; ?>">
	<!-- Tab navigation - always visible, clicking toggles content -->
	<div class="tsumego-comments__tabs" id="msg1x">
		<button class="tsumego-comments__tab" data-filter="open">
			COMMENTS (<?php echo $commentCount; ?><?php if ($openIssueCount > 0): ?> + <?php echo $openIssueCount; ?> open issue<?php echo $openIssueCount > 1 ? 's' : ''; ?><?php endif; ?>)
		</button>
		<button class="tsumego-comments__tab" data-filter="closed">
			CLOSED ISSUES (<?php echo $closedIssueCount; ?>)
		</button>
	</div>

	<!-- Collapsible wrapper (toggled by tab clicks) -->
	<div id="msg2x">
		<?php if (!$isEmpty): ?>
			<!-- All items view -->
			<div class="tsumego-comments__content" data-view="all" id="tsumego-comments-content">
				<?php foreach ($allItems as $item): ?>
					<?php if ($item['type'] === 'issue'): ?>
						<?php
						$issueData = $item['data'];
						$issueComments = $issueData['comments'] ?? [];
						$issueAuthor = $issueData['author'] ?? ['name' => '[deleted user]'];
						?>
						<?php echo $this->element('TsumegoIssues/issue', [
							'issue' => $issueData,
							'comments' => $issueComments,
							'author' => $issueAuthor,
							'issueNumber' => $item['issueNumber'],
							'tsumegoId' => $tsumegoId,
						]); ?>
					<?php else: ?>
						<?php
						$commentData = $item['data'];
						$commentUserData = $commentData['user'] ?? ['name' => '[deleted user]'];
						?>
						<?php echo $this->element('TsumegoComments/comment', [
							'comment' => $commentData,
							'user' => $commentUserData,
							'index' => $item['index'],
							'tsumegoId' => $tsumegoId,
							'showActions' => true,
							'standalone' => true,
						]); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Comment Form -->
		<?php if (Auth::isLoggedIn()): ?>
			<?php echo $this->element('TsumegoComments/form', [
				'tsumegoId' => $tsumegoId,
			]); ?>
		<?php else: ?>
			<div class="tsumego-comments__login-prompt">
				<p><a href="/users/login">Log in</a> to leave a comment.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
