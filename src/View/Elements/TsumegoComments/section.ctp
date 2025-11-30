<?php

/**
 * Comments section element - combines issues and standalone comments.
 *
 * Variables:
 * @var array $issues Array of issues with their comments (from Tsumego::loadCommentsData)
 * @var array $plainComments Array of comments not belonging to any issue
 * @var int $tsumegoId The tsumego ID
 */

// Default values
$issues = $issues ?? [];
$plainComments = $plainComments ?? [];
$tsumegoId = $tsumegoId ?? 0;

// Calculate counts
$issueCount = count($issues);
$commentCount = count($plainComments);
$totalCount = $issueCount + $commentCount;

// Count open issues
$openIssueCount = 0;
foreach ($issues as $issue)
	if ($issue['tsumego_issue_status_id'] == TsumegoIssue::$OPENED_STATUS)
		$openIssueCount++;

// Combine and sort by date for "all" view
$allItems = [];

// Add issues
foreach ($issues as $index => $issue) {
	$allItems[] = [
		'type' => 'issue',
		'created' => $issue['created'],
		'data' => $issue,
		'issueNumber' => $index + 1,
	];
}

// Add standalone comments
foreach ($plainComments as $index => $comment) {
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
?>

<?php
// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = TsumegoUtil::hasStateAllowingInspection($t ?? []) || Auth::isAdmin();
?>
<div id="commentSpace" class="tsumego-comments-section" <?php if (!$shouldShowComments): ?> style="display: none;" <?php endif; ?>>
	<div id="msg1x">
		<a id="show2" class="tsumego-comments__toggle">
			Comments <?php if ($totalCount > 0): ?>(<?php echo $totalCount; ?>)<?php endif; ?>
			<img id="greyArrow" src="/img/greyArrow2.png" class="tsumego-comments__arrow">
		</a>
	</div>

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
						<span class="tsumego-comments__open-badge">🔴 <?php echo $openIssueCount; ?> open</span>
					<?php endif; ?>
				</button>
			</div>

			<!-- All items view -->
			<div class="tsumego-comments__content" data-view="all">
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
						<div class="tsumego-comment--standalone">
							<?php echo $this->element('TsumegoComments/comment', [
								'comment' => $commentData,
								'user' => $commentUserData,
								'index' => $item['index'],
								'tsumegoId' => $tsumegoId,
								'showActions' => true,
							]); ?>
						</div>
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
	// Tab switching
	document.addEventListener('DOMContentLoaded', function() {
		var tabs = document.querySelectorAll('.tsumego-comments__tab');
		tabs.forEach(function(tab) {
			tab.addEventListener('click', function() {
				tabs.forEach(function(t) {
					t.classList.remove('active');
				});
				this.classList.add('active');

				var filter = this.dataset.filter;
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
			});
		});
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