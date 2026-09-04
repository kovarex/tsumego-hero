<?php

/**
 * React Comments Section - Mount point for the React comments component.
 *
 * Variables:
 * @var View $this
 * @var TsumegoIssue $TsumegoIssue
 * @var int $tsumegoId The tsumego ID
 * @var array $t The tsumego data (used to determine if comments should be visible)
 */

// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = TsumegoUtil::hasStateAllowingInspection($t) || Auth::isAdmin();
$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;

// Calculate counts for tabs (only thing we need from server)
$TsumegoIssue = ClassRegistry::init('TsumegoIssue');
$counts = $TsumegoIssue->getCommentSectionCounts($tsumegoId);

// Slim list of position-anchored comment positions so the review tree can show
// badges before the user opens the comments tab (avoids fetching all comments).
$TsumegoComment = ClassRegistry::init('TsumegoComment');
$anchoredPositions = $TsumegoComment->find('list', [
	'fields' => ['id', 'position'],
	'conditions' => [
		'tsumego_id' => $tsumegoId,
		'position !=' => null,
		'deleted' => 0,
	],
]);

$props = json_encode([
	'userId' => $userId,
	'isAdmin' => Auth::isAdmin(),
	'tsumegoId' => (int) $tsumegoId,
	'initialCounts' => $counts,
	'initialAnchoredPositions' => array_values($anchoredPositions),
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
