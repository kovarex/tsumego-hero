<?php

/**
 * Single issue element with all its comments.
 *
 * Uses idiomorph for React-like DOM diffing - close/reopen actions re-render
 * the entire comments section.
 *
 * Variables:
 * @var array $issue The issue data (TsumegoIssue model data)
 * @var array $comments Array of comments belonging to this issue
 * @var array $author The user who created the issue (User model data)
 * @var int $issueNumber Display number for this issue (Issue #1, #2, etc.)
 * @var int $tsumegoId The tsumego ID this issue belongs to
 */

App::uses('TsumegoIssue', 'Model');

// Default values
$issueNumber = $issueNumber ?? $issue['id'];
$tsumegoId = $tsumegoId ?? $issue['tsumego_id'];

// Get status info
$statusId = $issue['tsumego_issue_status_id'];
$statusName = TsumegoIssue::statusName($statusId);
$isOpened = $statusId == TsumegoIssue::$OPENED_STATUS;
$isClosed = $statusId == TsumegoIssue::$CLOSED_STATUS;

// Status CSS class
$statusClass = $isOpened ? 'status--opened' : 'status--closed';

// Get author name
$authorName = isset($author['name']) ? $author['name'] : '[deleted user]';

// Format created date
$createdDate = new DateTime($issue['created']);
$formattedDate = $createdDate->format('M. d, Y');

// Check permissions
$isAdmin = Auth::isAdmin();
$isOwner = Auth::isLoggedIn() && Auth::getUserID() == $issue['user_id'];
$canClose = ($isAdmin || $isOwner) && $isOpened;
$canReopen = $isAdmin && $isClosed;
$canReply = Auth::isLoggedIn();
$canDragComment = Auth::isAdmin();
?>

<div class="tsumego-issue tsumego-issue--<?php echo $isOpened ? 'opened' : 'closed'; ?>" id="issue-<?php echo $issue['id']; ?>" data-issue-id="<?php echo $issue['id']; ?>">
	<div class="tsumego-issue__header">
		<span class="tsumego-issue__title">
			Issue #<?php echo h($issueNumber); ?>
		</span>
		<span class="tsumego-issue__badge <?php echo $statusClass; ?>">
			<?php if ($isOpened): ?>
				🔴 <?php echo $statusName; ?>
			<?php else: ?>
				✅ <?php echo $statusName; ?>
			<?php endif; ?>
		</span>
		<span class="tsumego-issue__meta">
			by <?php echo h($authorName); ?> • <?php echo $formattedDate; ?>
		</span>

		<?php if ($canClose || $canReopen): ?>
			<span class="tsumego-issue__actions">
				<?php if ($canClose): ?>
					<button type="button"
							hx-post="/tsumego-issues/close/<?php echo $issue['id']; ?>"
							hx-target="#comments-section-<?php echo $tsumegoId; ?>"
							hx-swap="morph:outerHTML"
							hx-vals='{"source":"play"}'
							hx-disabled-elt="this"
							class="btn btn--success btn--small">
						✓ Close Issue
					</button>
				<?php endif; ?>

				<?php if ($canReopen): ?>
					<button type="button"
							hx-post="/tsumego-issues/reopen/<?php echo $issue['id']; ?>"
							hx-target="#comments-section-<?php echo $tsumegoId; ?>"
							hx-swap="morph:outerHTML"
							hx-vals='{"source":"play"}'
							hx-disabled-elt="this"
							class="btn btn--warning btn--small">
						↩ Reopen
					</button>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	</div>

	<div class="tsumego-issue__comments tsumego-dnd__issue-dropzone" id="issue-<?php echo $issue['id']; ?>-comments" data-issue-id="<?php echo $issue['id']; ?>">
		<?php if (!empty($comments)): ?>
			<?php foreach ($comments as $commentIndex => $comment): ?>
				<?php
				// User data is already loaded by Tsumego::loadCommentsData
				$commentUserData = $comment['user'] ?? ['name' => '[deleted user]'];
				?>
				<?php echo $this->element('TsumegoComments/comment', [
					'comment' => $comment,
					'user' => $commentUserData,
					'index' => ($issueNumber * 100) + $commentIndex + 1, // Unique index for coordinate links
					'tsumegoId' => $tsumegoId,
					'showActions' => true,
				]); ?>
			<?php endforeach; ?>
		<?php else: ?>
			<p class="tsumego-issue__empty">No comments in this issue.</p>
		<?php endif; ?>
	</div>

	<?php if ($canReply): ?>
		<!-- Reply toggle button -->
		<div class="tsumego-issue__reply-toggle">
			<button type="button" class="tsumego-issue__reply-btn" onclick="toggleIssueReply(<?php echo $issue['id']; ?>)">
				💬 Reply to this issue
			</button>
		</div>

		<!-- Reply form (hidden by default) -->
		<div class="tsumego-issue__reply-form" id="reply-form-<?php echo $issue['id']; ?>" style="display: none;">
			<?php echo $this->element('TsumegoComments/form', [
				'tsumegoId' => $tsumegoId,
				'issueId' => $issue['id'],
			]); ?>
		</div>
	<?php endif; ?>
</div>