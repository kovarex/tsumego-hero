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

// Process message - parse position tags and replace with clickable buttons
$message = $comment['message'];

// New format: [x/y/pX/pY/cX/cY/moveNumber/childrenCount/orientation|path]
// No pos: prefix, no {display} suffix - display is computed dynamically
// Path is stored in REVERSE order: [target, parent, grandparent, ...]
$posButtonIndex = 0;
$message = preg_replace_callback(
	'/\[(\d+\/\d+\/-?\d+\/-?\d+\/-?\d+\/-?\d+\/\d+\/\d+\/[a-z\-]+(?:\|[\d\/\+]+)?)\]/',
	function ($matches) use (&$posButtonIndex) {
		$positionData = $matches[1];
		$posButtonIndex++;

		// Parse position data to build JS arguments and display path
		if (strpos($positionData, '|') !== false)
		{
			$parts = explode("|", $positionData);
			$positionParts = explode('/', $parts[0]);
			$pathStr = $parts[1];
			$additionalArg = ",'" . $pathStr . "'";
			
			// Compute display from path coordinates
			// Path is stored as [target, parent, ...] so we reverse for display
			$pathCoords = explode('+', $pathStr);
			$displayParts = [];
			foreach ($pathCoords as $coord)
			{
				$xy = explode('/', $coord);
				if (count($xy) >= 2)
				{
					$x = (int) $xy[0];  // 1-indexed column (1-19)
					$y = (int) $xy[1];  // 1-indexed row from top (1-19)
					// Convert to Western notation (A-T, skipping I)
					// Column: x=1 -> A, x=2 -> B, ... x=8 -> H, x=9 -> J (skip I), etc.
					$cols = 'ABCDEFGHJKLMNOPQRST';  // 19 letters (no I)
					$col = ($x >= 1 && $x <= 19) ? $cols[$x - 1] : '?';
					// Row: In Western notation, row 1 is at bottom, row 19 at top
					// So y=1 (top in internal coords) -> 19, y=19 (bottom) -> 1
					$row = 19 - $y + 1;  // = 20 - y
					$displayParts[] = $col . $row;
				}
			}
			// Reverse for chronological display (ancestor→target)
			$displayPath = implode('→', array_reverse($displayParts));
		}
		else
		{
			$positionParts = explode('/', $positionData);
			$additionalArg = '';
			$displayPath = 'position';
			$pathStr = ''; // No path data for non-path positions
		}

		if (count($positionParts) >= 9)
		{
			$jsArgs = implode(',', array_slice($positionParts, 0, 8)) . ",'" . $positionParts[8] . "'" . $additionalArg;
			// Store raw path in data-path for JS rotation updates
			$dataPath = h($pathStr);
			return '<span class="position-button" onclick="commentPosition(' . $jsArgs . ');" title="Show position: ' . h($displayPath) . '" data-path="' . $dataPath . '">'
				. '<img src="/img/positionIcon1.png" class="positionIcon1" alt="">'
				. '<span class="position-path">' . h($displayPath) . '</span>'
				. '</span>';
		}

		// Fallback: return original text if parsing fails
		return $matches[0];
	},
	$message
);

// Legacy format: [pos:data]{display} - for backward compatibility
$message = preg_replace_callback(
	'/\[pos:([^\]]+)\]\{([^}]+)\}/',
	function ($matches) use (&$posButtonIndex) {
		$positionData = $matches[1];
		$displayPath = $matches[2];
		$posButtonIndex++;

		// Parse position data to build JS arguments
		$pathStr = '';
		if (strpos($positionData, '|') !== false)
		{
			$parts = explode("|", $positionData);
			$positionParts = explode('/', $parts[0]);
			$pathStr = $parts[1];
			$additionalArg = ",'" . $pathStr . "'";
		}
		else
		{
			$positionParts = explode('/', $positionData);
			$additionalArg = '';
		}

		if (count($positionParts) >= 9)
		{
			$jsArgs = implode(',', array_slice($positionParts, 0, 8)) . ",'" . $positionParts[8] . "'" . $additionalArg;
			// Store raw path in data-path for JS rotation updates
			$dataPath = h($pathStr);
			return '<span class="position-button" onclick="commentPosition(' . $jsArgs . ');" title="Show position: ' . h($displayPath) . '" data-path="' . $dataPath . '">'
				. '<img src="/img/positionIcon1.png" class="positionIcon1" alt="">'
				. '<span class="position-path">' . h($displayPath) . '</span>'
				. '</span>';
		}

		// Fallback: return original text if parsing fails
		return $matches[0];
	},
	$message
);

// Legacy support: Process old position field if present and no new-format positions found
$positionButton = '';
if (!empty($comment['position']) && $posButtonIndex === 0) {
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

// Process message - replace [current position] placeholder with legacy position button
if ($positionButton && strpos($message, '[current position]') !== false)
	$message = str_replace('[current position]', $positionButton, $message);

// Note: Go coordinates processing is done in Tsumego::loadCommentsData via TsumegosController::commentCoordinates

// Format date - combine date and time on one line
$createdDate = new DateTime($comment['created']);
$formattedDate = $createdDate->format('M. d, Y H:i');

// Check if current user can delete this comment
$canDelete = Auth::isAdmin() || (Auth::isLoggedIn() && Auth::getUserID() == $comment['user_id']);
$isInIssue = !empty($comment['tsumego_issue_id']);
$canDragComment = Auth::isAdmin() && $showActions;  // Respect showActions to disable in global list
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
					<span class="tsumego-comment__date"><?php echo $formattedDate; ?></span>

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
						<?php if ($canDragComment && !$isInIssue): ?>
							<button type="button"
									hx-post="/tsumego-issues/move-comment/<?php echo $comment['id']; ?>"
									hx-vals='{"data[Comment][tsumego_issue_id]":"new","data[Comment][htmx]":"1"}'
									hx-target="#comments-section-<?php echo $tsumegoId; ?>"
									hx-swap="morph:outerHTML"
									hx-disabled-elt="this"
									class="tsumego-comment__make-issue-btn">
								📋 Make Issue
							</button>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>
</div>
<?php if ($standalone): ?></div><?php endif; ?>