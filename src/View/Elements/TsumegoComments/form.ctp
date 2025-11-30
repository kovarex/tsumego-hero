<?php

/**
 * Comment form element.
 *
 * Variables:
 * @var int $tsumegoId The tsumego ID to comment on
 * @var int|null $issueId If set, this is a reply to a specific issue
 */

$issueId = $issueId ?? null;
$formId = $issueId ? 'replyForm-' . $issueId : 'tsumegoCommentForm';
$isReplyToIssue = !empty($issueId);
?>

<div class="tsumego-comments__form">
	<?php if (!$isReplyToIssue): ?>
		<h4>Add Comment</h4>
	<?php endif; ?>

	<form method="post" action="/tsumego-comments/add" id="<?php echo $formId; ?>" onsubmit="return preventDoubleSubmit('<?php echo $formId; ?>')">
		<input type="hidden" name="data[Comment][tsumego_id]" value="<?php echo $tsumegoId; ?>">
		<input type="hidden" name="data[Comment][redirect]" value="<?php echo $this->request->here; ?>">
		<?php if ($isReplyToIssue): ?>
			<input type="hidden" name="data[Comment][tsumego_issue_id]" value="<?php echo $issueId; ?>">
		<?php endif; ?>
		<input type="hidden" name="data[Comment][position]" id="commentPosition-<?php echo $formId; ?>" value="">

		<textarea
			name="data[Comment][message]"
			id="commentMessage-<?php echo $formId; ?>"
			rows="3"
			placeholder="<?php echo $isReplyToIssue ? 'Write your reply...' : 'Write your comment here...'; ?>"
			required></textarea>

		<div class="tsumego-comments__form-actions">
			<?php if (!$isReplyToIssue): ?>
				<label>
					<input type="checkbox" id="reportIssueCheckbox-<?php echo $formId; ?>" onchange="toggleIssueMode('<?php echo $formId; ?>')">
					Report as an issue (missing move, wrong answer, etc.)
				</label>
			<?php endif; ?>

			<button type="button" class="tsumego-comments__position-btn" onclick="openPositionPicker('<?php echo $formId; ?>')">
				<img src="/img/positionIcon1.png" width="16" alt="">
				Add board position
			</button>

			<span id="positionIndicator-<?php echo $formId; ?>" class="tsumego-comments__position-indicator" style="display: none;">
				✓ Position attached
				<a href="#" onclick="clearPosition('<?php echo $formId; ?>'); return false;">Remove</a>
			</span>
		</div>

		<div class="tsumego-comments__form-buttons">
			<button type="submit" class="tsumego-comments__submit-btn" id="submitBtn-<?php echo $formId; ?>">
				<?php echo $isReplyToIssue ? 'Submit Reply' : 'Submit Comment'; ?>
			</button>
		</div>
	</form>
</div>

<style>
	.tsumego-comments__submit-btn:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
</style>

<script>
	// Track forms that are currently submitting to prevent double-submit
	var submittingForms = {};

	function preventDoubleSubmit(formId) {
		if (submittingForms[formId]) {
			return false; // Already submitting
		}

		var submitBtn = document.getElementById('submitBtn-' + formId);
		if (submitBtn) {
			submitBtn.disabled = true;
		}

		submittingForms[formId] = true;
		return true;
	}

	function toggleIssueMode(formId) {
		var checkbox = document.getElementById('reportIssueCheckbox-' + formId);
		var form = document.getElementById(formId);

		if (checkbox && checkbox.checked) {
			// Creating new issue - change action
			form.action = '/tsumego-issues/create';
			// Update field names for issue creation
			form.querySelector('[name="data[Comment][tsumego_id]"]').name = 'data[Issue][tsumego_id]';
			form.querySelector('[name="data[Comment][message]"]').name = 'data[Issue][message]';
			form.querySelector('[name="data[Comment][redirect]"]').name = 'data[Issue][redirect]';
			var positionField = document.getElementById('commentPosition-' + formId);
			if (positionField) positionField.name = 'data[Issue][position]';
		} else {
			// Regular comment
			form.action = '/tsumego-comments/add';
			var tsumegoIdField = form.querySelector('[name="data[Issue][tsumego_id]"]');
			var messageField = form.querySelector('[name="data[Issue][message]"]');
			var redirectField = form.querySelector('[name="data[Issue][redirect]"]');
			if (tsumegoIdField) tsumegoIdField.name = 'data[Comment][tsumego_id]';
			if (messageField) messageField.name = 'data[Comment][message]';
			if (redirectField) redirectField.name = 'data[Comment][redirect]';
			var positionField = document.getElementById('commentPosition-' + formId);
			if (positionField && positionField.name === 'data[Issue][position]')
				positionField.name = 'data[Comment][position]';
		}
	}

	function openPositionPicker(formId) {
		// Integrate with besogo editor to capture current position
		if (typeof besogo === 'undefined' || !besogo.editor) {
			alert('Board editor not available');
			return;
		}

		var messageField = document.getElementById('commentMessage-' + formId);
		var positionField = document.getElementById('commentPosition-' + formId);
		var indicator = document.getElementById('positionIndicator-' + formId);

		if (!messageField || !positionField) {
			alert('Form fields not found');
			return;
		}

		// Get current position from besogo editor (same logic as play.ctp)
		var current = besogo.editor.getCurrent();
		var besogoOrientation = besogo.editor.getOrientation();
		if (besogoOrientation[1] == "full-board")
			besogoOrientation[0] = besogoOrientation[1];

		var additionalCoords = "";
		var originalCurrent = current; // Keep reference to original current

		// Only call isMoveInTree if current exists, has move data, and has navTreeX
		// This prevents errors when current is at root or in invalid state
		if (current && current.move && typeof current.navTreeX !== 'undefined') {
			try {
				var isInTree = besogo.editor.isMoveInTree(current);
				if (isInTree && isInTree[0] !== null) {
					current = isInTree[0];
				}
				// If isInTree[0] is null, keep using originalCurrent

				if (isInTree && isInTree[1] && isInTree[1]['x'] && isInTree[1]['x'].length > 0) {
					for (var i = isInTree[1]['x'].length - 1; i >= 0; i--)
						additionalCoords += isInTree[1]['x'][i] + isInTree[1]['y'][i] + " ";
					additionalCoords = " + " + additionalCoords;
				}
			} catch (e) {
				console.warn('isMoveInTree error, using current position:', e);
				current = originalCurrent; // Fallback to original current
			}
		}

		// Update message with position marker
		var commentContent = messageField.value;
		if (commentContent.includes("[current position]")) {
			commentContent = commentContent.replace('[current position]', '');
		}
		messageField.value = commentContent + "[current position]" + additionalCoords;

		// Set hidden position field - use originalCurrent if current is invalid
		var positionCurrent = (current && current.move) ? current : originalCurrent;
		if (positionCurrent === null || positionCurrent.move === null) {
			positionField.value = "-1/-1/0/0/0/0/0/0/0";
		} else {
			var pX = -1,
				pY = -1;
			if (positionCurrent.moveNumber > 1 && positionCurrent.parent && positionCurrent.parent.move) {
				pX = positionCurrent.parent.move.x;
				pY = positionCurrent.parent.move.y;
			}
			var cX, cY;
			if (!positionCurrent.children || positionCurrent.children.length === 0) {
				cX = -1;
				cY = -1;
			} else {
				cX = positionCurrent.children[0].move.x;
				cY = positionCurrent.children[0].move.y;
			}

			var newP = positionCurrent.parent;
			var newPcoords = positionCurrent.move.x + "/" + positionCurrent.move.y + "+";
			while (newP !== null && newP.move !== null) {
				newPcoords += newP.move.x + "/" + newP.move.y + "+";
				newP = newP.parent;
			}
			newPcoords = newPcoords.slice(0, -1);
			positionField.value = positionCurrent.move.x + "/" + positionCurrent.move.y + "/" + pX + "/" + pY + "/" + cX + "/" + cY + "/" + positionCurrent.moveNumber + "/" + (positionCurrent.children ? positionCurrent.children.length : 0) + "/" + besogoOrientation[0] + "|" + newPcoords;
		}

		// Show indicator
		if (indicator) {
			indicator.style.display = 'inline';
		}
	}

	function clearPosition(formId) {
		var positionField = document.getElementById('commentPosition-' + formId);
		var messageField = document.getElementById('commentMessage-' + formId);
		var indicator = document.getElementById('positionIndicator-' + formId);

		if (positionField) positionField.value = '';
		if (indicator) indicator.style.display = 'none';

		// Also remove [current position] marker from message
		if (messageField && messageField.value.includes('[current position]')) {
			messageField.value = messageField.value.replace(/\[current position\].*$/, '').trim();
		}
	}
</script>