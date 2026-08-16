<?php

/**
 * React Issues List Mount Point
 *
 * React fetches all data from API. Only initial state needed here.
 *
 * Required variables:
 * @var View $this
 * @var string $statusFilter - Current filter ('opened', 'closed', 'all')
 * @var int $currentPage - Current page number (for initial state)
 */

$userId = Auth::isLoggedIn() ? Auth::getUserID() : null;
$isAdmin = Auth::isAdmin();
$props = json_encode([
	'userId' => $userId,
	'isAdmin' => $isAdmin,
	'initialFilter' => $statusFilter,
	'initialPage' => (int) $currentPage,
]);
?>

<div 
	data-issues-root 
	data-props="<?php echo htmlspecialchars($props, ENT_QUOTES, 'UTF-8'); ?>"
>
	<!-- React app will mount here and fetch all data -->
	<div class="loading">Loading issues...</div>
</div>
