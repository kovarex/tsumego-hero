<?php
/**
 * React Issues List Mount Point
 *
 * React fetches all data from API. Only initial state needed here.
 *
 * Required variables:
 * @var string $statusFilter - Current filter ('opened', 'closed', 'all')
 * @var int $currentPage - Current page number (for initial state)
 */

$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;
$isAdmin = Auth::isAdmin();
?>

<div 
	data-issues-root 
	data-user-id="<?php echo $userId; ?>"
	data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>"
	data-status-filter="<?php echo h($statusFilter); ?>"
	data-current-page="<?php echo h($currentPage); ?>"
>
	<!-- React app will mount here and fetch all data -->
	<div class="loading">Loading issues...</div>
</div>
