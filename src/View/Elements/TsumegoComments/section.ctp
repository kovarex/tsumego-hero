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

	// ==========================================================================
	// Drag & Drop Comment Movement (Admin only)
	// Uses SortableJS for reliable cross-browser drag-and-drop
	// ==========================================================================

	(function() {
		'use strict';

		// Require SortableJS
		if (typeof Sortable === 'undefined') {
			console.error('[Comment DnD] SortableJS is required but not loaded!');
			return;
		}

		if (typeof Idiomorph === 'undefined')
			throw new Error('[Comment DnD] Idiomorph library is required but not loaded!');

		// Get the tsumego ID from the section
		var section = document.querySelector('[data-tsumego-id]');
		if (!section)
		{
			console.log('[Comment DnD] No section with data-tsumego-id found, skipping init.');
			return;
		}
		var tsumegoId = section.dataset.tsumegoId;
		console.log('[Comment DnD] Initializing for tsumego:', tsumegoId);

		// Check if drag handles exist (admin only)
		var hasHandles = document.querySelector('.tsumego-comment__drag-handle');
		if (!hasHandles)
		{
			console.log('[Comment DnD] No drag handles found (user is not admin), skipping init.');
			return;
		}

		console.log('[Comment DnD] Drag handles found, setting up SortableJS...');

		var dropZones = {
			// Drop zones removed - using Make Issue button instead
		};

		var activeSortables = [];
		var currentDraggedComment = null;
		var currentDragState = null; // Store original position for tracking
		var dragCancelled = false; // Flag to prevent drop handlers from executing

		// NOTE: ESC key cancel is NOT supported by SortableJS.
		// The library maintainer confirmed: "It impossible to do when using Native DND."
		// Even with forceFallback:true, SortableJS has no keyboard event handling.
		// Would require forking SortableJS or custom implementation.

		// Move comment via htmx-style POST
		function moveComment(commentId, targetIssueId)
		{
			console.log('[Comment DnD] Moving comment', commentId, 'to target:', targetIssueId);

			var formData = new FormData();
			formData.append('data[Comment][tsumego_issue_id]', targetIssueId);
			formData.append('data[Comment][htmx]', '1');

			fetch('/tsumego-issues/move-comment/' + commentId, {
				method: 'POST',
				body: formData,
				headers: {
					'HX-Request': 'true'
				}
			})
			.then(function(response) {
				if (!response.ok)
					throw new Error('Server returned ' + response.status);
				return response.text();
			})
			.then(function(html) {
				console.log('[Comment DnD] Received response, morphing...');
				var target = document.getElementById('comments-section-' + tsumegoId);
				if (target && html)
				{
					Idiomorph.morph(target, html, {morphStyle: 'outerHTML'});
					setTimeout(initDragDrop, 100);
				}
			})
			.catch(function(err) {
				console.error('[Comment DnD] Move failed:', err);
				alert('Failed to move comment. Please try again.');
			});
		}

		// Show/hide drop zones during drag (no longer needed - using buttons instead)
		function showDropZones(show)
		{
			// Drop zones have been removed in favor of "Make Issue" buttons
			// This function is kept for compatibility but does nothing
		}

		// Show/hide issue drop targets (must be defined before initDragDrop)
		function showIssueDropTargets(show, sourceIssueId)
		{
			document.querySelectorAll('.tsumego-issue').forEach(function(issueEl) {
				var issueId = issueEl.dataset.issueId;
				var overlay = issueEl.querySelector('.tsumego-issue__drop-overlay');
				if (overlay)
				{
					if (show)
					{
						overlay.style.display = 'flex';
						// Mark source issue overlay differently
						if (issueId === sourceIssueId)
						{
							overlay.classList.add('tsumego-issue__drop-overlay--source');
							overlay.querySelector('span').textContent = 'Drop to return comment';
						}
						else
						{
							overlay.classList.remove('tsumego-issue__drop-overlay--source');
							overlay.querySelector('span').textContent = 'Drop here to add to this issue';
						}
					}
					else
					{
						overlay.style.display = 'none';
						overlay.classList.remove('tsumego-issue__drop-overlay--source');
					}
				}
			});
		}

		// Initialize drag-drop functionality with SortableJS
		function initDragDrop()
		{
			console.log('[Comment DnD] initDragDrop called');

			// Destroy previous sortables
			activeSortables.forEach(function(s) {
				if (s && s.destroy) s.destroy();
			});
			activeSortables = [];

			// Find all containers that hold draggable comments
			var commentsContent = document.getElementById('tsumego-comments-content');
			if (!commentsContent) {
				console.log('[Comment DnD] No comments content container found');
				return;
			}

			// Create sortable for main content area
			// Standalone comments are wrapped in .tsumego-comment--standalone, so we drag those wrappers
			// Issues are .tsumego-issue, we don't drag those from here
			var mainSortable = new Sortable(commentsContent, {
				group: {
					name: 'comments',
					pull: true,
					put: true // Allow drops into main area (for moving comments out of issues)
				},
				sort: false, // Don't allow reordering
				animation: 150,
				scroll: true, // Enable auto-scroll when dragging near edges
				scrollSensitivity: 80, // px from edge to start scrolling
				scrollSpeed: 15, // px per frame scroll speed
				bubbleScroll: true, // Enable scrolling in ancestor scroll containers too
				handle: '.tsumego-comment__drag-handle',
				draggable: '.tsumego-comment--standalone', // Only standalone wrappers are draggable here
				ghostClass: 'tsumego-comment--ghost',
				chosenClass: 'tsumego-comment--dragging',
				filter: '.tsumego-issue', // Don't drag issues
				onStart: function(evt) {
					console.log('[Comment DnD] onStart (main)', evt.item);
					// Get the actual comment element from within the wrapper
					currentDraggedComment = evt.item.querySelector('.tsumego-comment') || evt.item;
					currentDragState = {
						item: evt.item,
						from: evt.from,
						oldIndex: evt.oldIndex,
						sourceIssueId: null
					};
					console.log('[Comment DnD] Dragging comment:', currentDraggedComment.dataset.commentId);
					showDropZones(true);
					// Show ALL issue drop targets when dragging from main area
					showIssueDropTargets(true, null);
				},
				onEnd: function(evt) {
					console.log('[Comment DnD] onEnd (main)');
					showDropZones(false);
					showIssueDropTargets(false, null);
					currentDraggedComment = null;
					currentDragState = null;
				},
				onAdd: function(evt) {
					// Check if drag was cancelled
					if (dragCancelled)
					{
						console.log('[Comment DnD] onAdd ignored - drag was cancelled');
						return;
					}
					
					// Comment dropped into main area (making it standalone)
					var comment = evt.item.querySelector('.tsumego-comment') || evt.item;
					var commentId = comment.dataset.commentId;
					console.log('[Comment DnD] Comment', commentId, 'dropped to standalone (main area)');
					
					// Clear drag state before server call
					currentDragState = null;
					
					// Optimistic: SortableJS already moved the element, leave it there
					// The server response + idiomorph will handle the final state
					moveComment(commentId, 'standalone');
				}
			});
			activeSortables.push(mainSortable);
			console.log('[Comment DnD] Main sortable created');
			
			// Create sortable for each issue's comment container
			// IMPORTANT: Use a DIFFERENT group name so issue sortables don't interact with each other
			// This prevents flickering when dragging over same issue's comments
			document.querySelectorAll('.tsumego-dnd__issue-dropzone').forEach(function(container) {
				var issueId = container.dataset.issueId;
				var issueEl = container.closest('.tsumego-issue');
				console.log('[Comment DnD] Setting up sortable for issue:', issueId);

				// Each issue gets its own unique group - can only pull, not receive from others in group
				var issueSortable = new Sortable(container, {
					group: {
						name: 'issue-' + issueId, // Unique group per issue
						pull: 'comments', // Can pull to 'comments' group
						put: false // Don't accept drops via SortableJS
					},
					sort: false,
					animation: 0,
					scroll: true, // Enable auto-scroll when dragging near edges
					scrollSensitivity: 80, // px from edge to start scrolling
					scrollSpeed: 15, // px per frame scroll speed
					bubbleScroll: true, // Enable scrolling in ancestor scroll containers too
					handle: '.tsumego-comment__drag-handle',
					draggable: '.tsumego-comment',
					ghostClass: 'tsumego-comment--ghost',
					chosenClass: 'tsumego-comment--dragging',
					onStart: function(evt) {
						console.log('[Comment DnD] onStart (issue ' + issueId + ')');
						currentDraggedComment = evt.item;
						currentDragState = {
							item: evt.item,
							from: evt.from,
							oldIndex: evt.oldIndex,
							sourceIssueId: issueId
						};
						// Mark this issue as the drag source
						issueEl.classList.add('tsumego-issue--drag-source');
						showDropZones(true);
						// Show all issue drop targets (except the source)
						showIssueDropTargets(true, issueId);
					},
					onEnd: function(evt) {
						console.log('[Comment DnD] onEnd (issue ' + issueId + ')');
						issueEl.classList.remove('tsumego-issue--drag-source');
						showDropZones(false);
						showIssueDropTargets(false, null);
						currentDraggedComment = null;
						currentDragState = null;
					}
				});
				activeSortables.push(issueSortable);
				
				// Add drop target overlay to the issue header
				setupIssueDropTarget(issueEl, issueId);
			});
			
			// Setup issue drop target (for receiving drops)
			function setupIssueDropTarget(issueEl, issueId)
			{
				// Create overlay if it doesn't exist
				var overlay = issueEl.querySelector('.tsumego-issue__drop-overlay');
				if (!overlay)
				{
					overlay = document.createElement('div');
					overlay.className = 'tsumego-issue__drop-overlay';
					overlay.innerHTML = '<span>Drop here to add to this issue</span>';
					overlay.style.display = 'none';
					issueEl.appendChild(overlay);
				}
				
				// Native drag events on the overlay
				overlay.addEventListener('dragover', function(e) {
					e.preventDefault();
					overlay.classList.add('tsumego-issue__drop-overlay--active');
				});
				
				overlay.addEventListener('dragleave', function(e) {
					overlay.classList.remove('tsumego-issue__drop-overlay--active');
				});
				
				overlay.addEventListener('drop', function(e) {
					e.preventDefault();
					e.stopPropagation();
					overlay.classList.remove('tsumego-issue__drop-overlay--active');
					
					if (!currentDraggedComment) return;
					
					var comment = currentDraggedComment.querySelector ? 
						(currentDraggedComment.querySelector('.tsumego-comment') || currentDraggedComment) :
						currentDraggedComment;
					var commentId = comment.dataset.commentId;
					
					// Check if this is a return to source issue
					if (currentDragState && currentDragState.sourceIssueId === issueId)
					{
						console.log('[Comment DnD] Comment', commentId, 'returned to source issue', issueId);
						// Re-insert the element back into the issue's dropzone
						var dropzone = issueEl.querySelector('.tsumego-dnd__issue-dropzone');
						if (dropzone && currentDraggedComment)
						{
							dropzone.appendChild(currentDraggedComment);
						}
						// Clear state
						currentDraggedComment = null;
						currentDragState = null;
						return;
					}
					
					console.log('[Comment DnD] Drop on overlay - Comment', commentId, 'dropped into issue', issueId);
					
					// Optimistic: Move element into target issue's dropzone
					var targetDropzone = issueEl.querySelector('.tsumego-dnd__issue-dropzone');
					if (targetDropzone && currentDraggedComment)
					{
						targetDropzone.appendChild(currentDraggedComment);
					}
					
					moveComment(commentId, issueId);
				});
			}

			// Drop zone for new issue removed - using Make Issue button instead

			console.log('[Comment DnD] Setup complete with', activeSortables.length, 'sortables');
		}

		// Initialize on page load
		initDragDrop();

		// Re-initialize after htmx swaps
		document.body.addEventListener('htmx:afterSwap', function(e) {
			if (e.detail.target && e.detail.target.id && e.detail.target.id.startsWith('comments-section-'))
				setTimeout(initDragDrop, 50);
		});

		console.log('[Comment DnD] SortableJS setup initiated');
	})();
</script>