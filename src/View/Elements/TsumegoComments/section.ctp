<?php

/**
 * React Comments Section - Mount point for the React comments component.
 *
 * Variables:
 * @var View $this
 * @var TsumegoIssue $TsumegoIssue
 * @var int $tsumegoId The tsumego ID
 * @var bool $solved Whether the problem is solved for the current mode
 */

// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = Auth::isAdmin() || $solved;
$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;

// Calculate counts for tabs (only thing we need from server)
$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
$counts = $TsumegoIssue->getCommentSectionCounts($tsumegoId);
$props = json_encode([
	'userId' => $userId,
	'isAdmin' => Auth::isAdmin(),
	'tsumegoId' => (int) $tsumegoId,
	'initialCounts' => $counts,
]);
?>

<!-- React mount point with only counts (fetch comments on tab click) -->
<div 
	id="commentSpace"
	class="tsumego-comments-section"
	data-comments-root 
	data-props="<?= htmlspecialchars($props, ENT_QUOTES, 'UTF-8') ?>"
	<?php if (!$shouldShowComments): ?>style="display: none;"<?php endif; ?>
>
	<!-- React will mount here and fetch comments when tab clicked -->
	<div class="loading">Click a tab to load comments...</div>
</div>
