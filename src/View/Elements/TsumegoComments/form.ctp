<?php

/**
 * Comment form element.
 *
 * Uses idiomorph for React-like DOM diffing - the entire comments section is re-rendered
 * and morphed, so we don't need complex target selection.
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

	<form method="post" action="/tsumego-comments/add" id="<?php echo $formId; ?>"
		  hx-post="/tsumego-comments/add"
		  hx-target="#comments-section-<?php echo $tsumegoId; ?>"
		  hx-swap="morph:outerHTML"
		  hx-disabled-elt="find button[type='submit']"
		  hx-on::after-request="if(event.detail.successful) { this.reset(); }">
		<input type="hidden" name="data[Comment][tsumego_id]" value="<?php echo $tsumegoId; ?>">
		<input type="hidden" name="data[Comment][redirect]" value="<?php echo $this->request->here; ?>">
		<?php if ($isReplyToIssue): ?>
			<input type="hidden" name="data[Comment][tsumego_issue_id]" value="<?php echo $issueId; ?>">
		<?php endif; ?>

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
		cursor: wait;
	}
</style>

<script>
	function toggleIssueMode(formId) {
		var checkbox = document.getElementById('reportIssueCheckbox-' + formId);
		var form = document.getElementById(formId);

		if (checkbox && checkbox.checked) {
			// Creating new issue - change action and htmx attribute
			form.action = '/tsumego-issues/create';
			form.setAttribute('hx-post', '/tsumego-issues/create');
			// Update field names for issue creation
			form.querySelector('[name="data[Comment][tsumego_id]"]').name = 'data[Issue][tsumego_id]';
			form.querySelector('[name="data[Comment][message]"]').name = 'data[Issue][message]';
			form.querySelector('[name="data[Comment][redirect]"]').name = 'data[Issue][redirect]';
			// Re-process htmx attributes after dynamic change
			htmx.process(form);
		} else {
			// Regular comment
			form.action = '/tsumego-comments/add';
			form.setAttribute('hx-post', '/tsumego-comments/add');
			var tsumegoIdField = form.querySelector('[name="data[Issue][tsumego_id]"]');
			var messageField = form.querySelector('[name="data[Issue][message]"]');
			var redirectField = form.querySelector('[name="data[Issue][redirect]"]');
			if (tsumegoIdField) tsumegoIdField.name = 'data[Comment][tsumego_id]';
			if (messageField) messageField.name = 'data[Comment][message]';
			if (redirectField) redirectField.name = 'data[Comment][redirect]';
			// Re-process htmx attributes after dynamic change
			htmx.process(form);
		}
	}

	function openPositionPicker(formId) {
		// Integrate with besogo editor to capture current position
		if (typeof besogo === 'undefined' || !besogo.editor) {
			alert('Board editor not available');
			return;
		}

		var messageField = document.getElementById('commentMessage-' + formId);
		if (!messageField) {
			alert('Message field not found');
			return;
		}

		// Get current position from besogo editor
		var current = besogo.editor.getCurrent();
		var besogoOrientation = besogo.editor.getOrientation();
		if (besogoOrientation[1] == "full-board")
			besogoOrientation[0] = besogoOrientation[1];

		// Get board size and coordinate labels
		var root = current;
		while (root && root.parent) root = root.parent;
		var size = root ? root.getSize() : {x: 19, y: 19};
		var sizeX = size.x;
		var sizeY = size.y;
		var labels = besogo.coord['western'](sizeX, sizeY);

		// Build position data string and human-readable path
		var positionData;
		var displayPath = '';

		if (current === null || current.move === null) {
			positionData = "-1/-1/0/0/0/0/0/0/" + besogoOrientation[0];
			displayPath = 'start';
		} else {
			var pX = -1, pY = -1;
			if (current.moveNumber > 1 && current.parent && current.parent.move) {
				pX = current.parent.move.x;
				pY = current.parent.move.y;
			}
			var cX = -1, cY = -1;
			if (current.children && current.children.length > 0) {
				cX = current.children[0].move.x;
				cY = current.children[0].move.y;
			}

			// Build path string (all moves leading to this position)
			// Path is stored in REVERSE order: [target, parent, grandparent, ...]
			// This matches how compareFoundCommentMoves traverses upward from the target node
			var pathCoords = [];
			var pathDisplay = [];
			var node = current;
			while (node !== null && node.move !== null) {
				pathCoords.push(node.move.x + "/" + node.move.y);  // push = target first
				// Convert to Western coordinate (e.g., "C12")
				var coord = labels.x[node.move.x] + labels.y[node.move.y];
				pathDisplay.unshift(coord);  // unshift for display = chronological order
				node = node.parent;
			}
			var pathStr = pathCoords.join("+");
			displayPath = pathDisplay.join('→');

			positionData = current.move.x + "/" + current.move.y + "/" + pX + "/" + pY + "/" +
				cX + "/" + cY + "/" + current.moveNumber + "/" +
				(current.children ? current.children.length : 0) + "/" +
				besogoOrientation[0] + "|" + pathStr;
		}

		// Show readable format in textarea: [A12→B13]
		// Store position data as hidden attribute on form for later extraction
		var readableTag = "[" + displayPath + "]";
		
		// Store the mapping of readable to data format (without pos: prefix)
		if (!window._positionMappings) window._positionMappings = {};
		if (!window._positionMappings[formId]) window._positionMappings[formId] = [];
		window._positionMappings[formId].push({
			readable: readableTag,
			data: "[" + positionData + "]"
		});

		// Insert readable format at cursor position in textarea
		var cursor = messageField.selectionStart || messageField.value.length;
		var text = messageField.value;
		messageField.value = text.slice(0, cursor) + readableTag + text.slice(cursor);
		messageField.selectionStart = messageField.selectionEnd = cursor + readableTag.length;
		messageField.focus();
	}

	// Listen for Ctrl+Click coordinates from besogo board
	// Only add listener once (check if already added)
	if (!window._besogoCoordClickListenerAdded) {
		window._besogoCoordClickListenerAdded = true;
		document.addEventListener('besogoCoordClick', function(e) {
			// Try to find a focused textarea, or the main comment form
			var target = document.activeElement;
			if (!target || target.tagName !== 'TEXTAREA') {
				// Fall back to main comment form textarea
				target = document.getElementById('commentMessage-tsumegoCommentForm');
			}

			if (target && target.tagName === 'TEXTAREA') {
				var start = target.selectionStart || 0;
				var end = target.selectionEnd || 0;
				var text = target.value;
				var coord = e.detail.coord + ' ';
				target.value = text.slice(0, start) + coord + text.slice(end);
				target.selectionStart = target.selectionEnd = start + coord.length;
				target.focus();
			}
		});
	}

	// Intercept htmx form submission to convert readable position format to data format
	if (!window._positionConvertListenerAdded) {
		window._positionConvertListenerAdded = true;
		document.body.addEventListener('htmx:configRequest', function(e) {
			var form = e.detail.elt;
			if (!form || form.tagName !== 'FORM') return;
			
			var formId = form.id;
			if (!formId) return;
			
			// Check if this form has position mappings
			var mappings = window._positionMappings && window._positionMappings[formId];
			if (!mappings || mappings.length === 0) return;
			
			// Find the message field in the request parameters
			var messageKey = 'data[Comment][message]';
			if (form.querySelector('[name="data[Issue][message]"]')) {
				messageKey = 'data[Issue][message]';
			}
			
			var message = e.detail.parameters[messageKey];
			if (!message) return;
			
			// Replace all readable tags with data format
			for (var i = 0; i < mappings.length; i++) {
				var mapping = mappings[i];
				message = message.replace(mapping.readable, mapping.data);
			}
			
			e.detail.parameters[messageKey] = message;
			
			// Clear mappings for this form after conversion
			window._positionMappings[formId] = [];
		});
	}
</script>