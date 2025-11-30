<?php

/**
 * Single comment element.
 *
 * Uses idiomorph for React-like DOM diffing - delete action re-renders
 * the entire comments section.
 *
 * Variables:
 * @var array $comment The comment data (TsumegoComment model data)
 * @var array $user The user who wrote the comment (User model data)
 * @var int $index Comment index (for unique IDs in coordinate links)
 * @var int $tsumegoId The tsumego ID this comment belongs to
 * @var bool $showActions Whether to show delete/move actions (default: true)
 * @var array $allIssues Optional: all issues for this tsumego (for dropdown)
 * @var bool $standalone Whether to wrap in standalone container div (default: false)
 */

// Default values
$showActions = $showActions ?? true;
$standalone = $standalone ?? false;
$index = $index ?? 1;
$allIssues = $allIssues ?? [];

// Determine comment styling based on user type
$isAdmin = !empty($user['admin']) || !empty($user['isAdmin']);
$commentColorClass = $isAdmin ? 'commentBox2' : 'commentBox1';

// Get author name
$authorName = $user['name'] ?? '[deleted user]';

// Process position button if comment has a position
$positionButton = '';
if (!empty($comment['position'])) {
	$position = $comment['position'];
	if (strpos($position, '|') !== false) {
		$parts = explode("|", $position);
		$positionParts = explode('/', $parts[0]);
		$additionalArg = ",'" . $parts[1] . "'";
	} else {
		$positionParts = explode('/', $position);
		$additionalArg = '';
	}

	if (count($positionParts) >= 9) {
		$jsArgs = implode(',', array_slice($positionParts, 0, 8)) . ",'" . $positionParts[8] . "'" . $additionalArg;
		$positionButton = '<img src="/img/positionIcon1.png" class="positionIcon1" onclick="commentPosition(' . $jsArgs . ');" title="Show position">';
	}
}

// Process message - replace [current position] placeholder with position button
$message = $comment['message'];
if ($positionButton && strpos($message, '[current position]') !== false)
	$message = str_replace('[current position]', $positionButton, $message);

// Note: Go coordinates processing is done in Tsumego::loadCommentsData via TsumegosController::commentCoordinates

// Format date
$createdDate = new DateTime($comment['created']);
$formattedDate = $createdDate->format('M. d, Y');
$formattedTime = $createdDate->format('H:i');

// Check if current user can delete this comment
$canDelete = Auth::isAdmin() || (Auth::isLoggedIn() && Auth::getUserID() == $comment['user_id']);
$isInIssue = !empty($comment['tsumego_issue_id']);
$canDragComment = Auth::isAdmin();
?>
<?php if ($standalone): ?><div class="tsumego-comment--standalone"><?php endif; ?>
<div class="tsumego-comment<?php echo $canDragComment ? ' tsumego-comment--draggable' : ''; ?>" id="comment-<?php echo $comment['id']; ?>" data-comment-id="<?php echo $comment['id']; ?>" data-in-issue="<?php echo $isInIssue ? '1' : '0'; ?>">
	<div class="sandboxComment">
		<table class="sandboxTable2" width="100%" border="0">
			<tr>
				<?php if ($canDragComment): ?>
					<td class="tsumego-comment__drag-handle-cell">
						<span class="tsumego-comment__drag-handle" title="Drag to move comment">☰</span>
					</td>
				<?php endif; ?>
				<td>
					<div class="<?php echo $commentColorClass; ?>">
						<span class="tsumego-comment__author"><?php echo h($authorName); ?>:</span><br>
						<?php echo $message; ?>
					</div>
				</td>
				<td align="right" class="sandboxTable2time">
					<span class="tsumego-comment__date"><?php echo $formattedDate; ?></span><br>
					<span class="tsumego-comment__time"><?php echo $formattedTime; ?></span>

					<?php if ($showActions): ?>
						<?php if ($canDelete): ?>
							<button type="button"
									hx-post="/tsumego-comments/delete/<?php echo $comment['id']; ?>"
									hx-target="#comments-section-<?php echo $tsumegoId; ?>"
									hx-swap="morph:outerHTML"
									hx-confirm="Delete this comment?"
									hx-disabled-elt="this"
									class="deleteComment">
								Delete
							</button>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php if ($standalone): ?></div><?php endif; ?>