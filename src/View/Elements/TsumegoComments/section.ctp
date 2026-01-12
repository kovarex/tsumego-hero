<?php
/**
 * React Comments Section - Mount point for the React comments component.
 *
 * Variables:
 * @var int $tsumegoId The tsumego ID
 * @var array $t The tsumego data (used to determine if comments should be visible)
 */

// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = TsumegoUtil::hasStateAllowingInspection($t ?? []) || Auth::isAdmin();
$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;

// Calculate counts for tabs (only thing we need from server)
$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
$counts = $TsumegoIssue->getCommentSectionCounts($tsumegoId);
?>

<!-- React mount point with only counts (fetch comments on tab click) -->
<div 
	id="commentSpace"
	class="tsumego-comments-section"
	data-comments-root 
	data-tsumego-id="<?= $tsumegoId ?>"
	data-user-id="<?= $userId ?>"
	data-is-admin="<?= Auth::isAdmin() ? 'true' : 'false' ?>"
	data-initial-counts="<?= htmlspecialchars(json_encode($counts), ENT_QUOTES, 'UTF-8') ?>"
	<?php if (!$shouldShowComments): ?>style="display: none;"<?php endif; ?>
>
	<!-- React will mount here and fetch comments when tab clicked -->
	<div class="loading">Click a tab to load comments...</div>
</div>
