<?php

/**
 * Comments section element - combines issues and standalone comments.
 *
 * Uses idiomorph for automatic DOM diffing. When any htmx action completes,
 * the server returns section-content.ctp and idiomorph updates only what changed.
 *
 * This file is for initial page load only. htmx responses use section-content.ctp.
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

// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = TsumegoUtil::hasStateAllowingInspection($t ?? []) || Auth::isAdmin();
?>
<div id="commentSpace" class="tsumego-comments-section" <?php if (!$shouldShowComments): ?> style="display: none;" <?php endif; ?>>
	<?php echo $this->element('TsumegoComments/section-content', [
		'issues' => $issues,
		'plainComments' => $plainComments,
		'tsumegoId' => $tsumegoId,
		'totalCount' => $totalCount,
		'commentCount' => $commentCount,
		'issueCount' => $issueCount,
		'openIssueCount' => $openIssueCount ?? null,
	]); ?>
</div>

<!-- Move to Issue Dialog (hidden by default) -->
<?php if (Auth::isAdmin()): ?>
	<div id="moveToIssueDialog" class="tsumego-comments__dialog" style="display: none;">
		<div class="tsumego-comments__dialog-content">
			<h4>Move Comment</h4>
			<form method="post" id="moveToIssueForm" action="">
				<input type="hidden" name="data[Comment][redirect]" value="<?php echo $this->request->here; ?>">
				<select name="data[Comment][tsumego_issue_id]" id="moveToIssueSelect">
					<option value="standalone">No Issue (standalone comment)</option>
					<option value="new">Create NEW Issue</option>
					<?php foreach ($issues as $index => $issue): ?>
						<?php $statusName = TsumegoIssue::statusName($issue['tsumego_issue_status_id']); ?>
						<option value="<?php echo $issue['id']; ?>">
							Issue #<?php echo $index + 1; ?> (<?php echo $statusName; ?>)
						</option>
					<?php endforeach; ?>
				</select>
				<div class="tsumego-comments__dialog-buttons">
					<button type="submit">Move</button>
					<button type="button" onclick="hideMoveToIssueDialog()">Cancel</button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>

<script>
	// Tab switching - use event delegation to handle dynamically replaced content
	document.addEventListener('click', function(e) {
		if (e.target.classList.contains('tsumego-comments__tab')) {
			var tabs = document.querySelectorAll('.tsumego-comments__tab');
			tabs.forEach(function(t) {
				t.classList.remove('active');
			});
			e.target.classList.add('active');

			var filter = e.target.dataset.filter;
			var items = document.querySelectorAll('.tsumego-issue, .tsumego-comment--standalone');
			items.forEach(function(item) {
				if (filter === 'all') {
					item.style.display = '';
				} else if (filter === 'issues') {
					item.style.display = item.classList.contains('tsumego-issue') ? '' : 'none';
				} else if (filter === 'comments') {
					item.style.display = item.classList.contains('tsumego-comment--standalone') ? '' : 'none';
				}
			});
		}
	});

	// Move to Issue functionality
	function showMoveToIssueDialog(commentId, currentIssueId) {
		var dialog = document.getElementById('moveToIssueDialog');
		var form = document.getElementById('moveToIssueForm');
		var select = document.getElementById('moveToIssueSelect');

		form.action = '/tsumego-issues/move-comment/' + commentId;

		// Pre-select current issue or standalone
		if (currentIssueId) {
			select.value = currentIssueId;
		} else {
			select.value = 'standalone';
		}

		dialog.style.display = 'flex';
	}

	function hideMoveToIssueDialog() {
		document.getElementById('moveToIssueDialog').style.display = 'none';
	}

	// Reply to Issue functionality
	function toggleIssueReply(issueId) {
		var form = document.getElementById('reply-form-' + issueId);
		if (form.style.display === 'none') {
			// Hide all other reply forms first
			document.querySelectorAll('.tsumego-issue__reply-form').forEach(function(f) {
				f.style.display = 'none';
			});
			form.style.display = 'block';
			form.querySelector('textarea').focus();
		} else {
			form.style.display = 'none';
		}
	}

	// Close dialog on outside click
	document.getElementById('moveToIssueDialog')?.addEventListener('click', function(e) {
		if (e.target === this) {
			hideMoveToIssueDialog();
		}
	});
</script>