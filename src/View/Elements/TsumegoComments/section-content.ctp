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

// Combine and sort by date for "all" view
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
	<!-- Header with toggle -->
	<div id="msg1x">
		<a id="show2" class="tsumego-comments__toggle">
			Comments <?php if ($totalCount > 0): ?>(<?php echo $totalCount; ?>)<?php endif; ?>
			<img id="greyArrow" src="/img/greyArrow2.png" class="tsumego-comments__arrow">
		</a>
	</div>

	<!-- Collapsible wrapper (toggled by #show2 in play.ctp) -->
	<div id="msg2x">
		<?php if (!$isEmpty): ?>
			<!-- Tab navigation -->
			<div class="tsumego-comments__tabs">
				<button class="tsumego-comments__tab active" data-filter="all">
					ALL (<?php echo $totalCount; ?>)
				</button>
				<button class="tsumego-comments__tab" data-filter="comments">
					COMMENTS (<?php echo $commentCount; ?>)
				</button>
				<button class="tsumego-comments__tab" data-filter="issues">
					ISSUES (<?php echo $issueCount; ?>)
					<?php if ($openIssueCount > 0): ?>
						<span class="tsumego-comments__tab-badge">🔴 <?php echo $openIssueCount; ?> open</span>
					<?php endif; ?>
				</button>
			</div>
		<?php endif; ?>

		<!-- All items view -->
		<div class="tsumego-comments__content" data-view="all" id="tsumego-comments-content">
			<?php if ($canDragComment): ?>
				<!-- Drop zone: Create new issue (shown when dragging) -->
				<div class="tsumego-dnd__dropzone tsumego-dnd__dropzone--new-issue" data-target="new" style="display: none;">
					📋 Drop here to create NEW issue
				</div>
			<?php endif; ?>

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
