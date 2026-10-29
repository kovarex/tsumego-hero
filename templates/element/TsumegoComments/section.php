<?php

use App\Model\Table\TsumegoIssueTable;
use App\Utility\Auth;
use Cake\ORM\TableRegistry;

/**
 * React Comments Section - Mount point for the React comments component.
 *
 * Variables:
 * @var \App\View\AppView $this
 * @var int $tsumegoId The tsumego ID
 * @var bool $solved Whether the problem is solved for the current mode
 */

// Determine if comments should be visible (solved, completed, or admin)
$shouldShowComments = Auth::isAdmin() || $solved;
$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;

// Calculate counts for tabs (only thing we need from server)
$counts = TsumegoIssueTable::getCommentSectionCounts($tsumegoId);

// Slim list of position-anchored comment positions so the review tree can show
// badges before the user opens the comments tab (avoids fetching all comments).
$anchoredPositions = TableRegistry::getTableLocator()->get('TsumegoComment')->find('list', keyField: 'id', valueField: 'position')
	->where(['tsumego_id' => $tsumegoId, 'position IS NOT' => null, 'deleted IS' => null])
	->toArray();

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
