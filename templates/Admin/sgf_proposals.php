<?php

use App\Utility\RejectedSGFProposalsRenderer;
use App\Utility\SGFProposalsRenderer;

/**
 * @var \App\View\AppView $this
 * @var RejectedSGFProposalsRenderer $rejectedSgfProposalsRenderer
 * @var SGFProposalsRenderer $sgfProposalsRenderer
 */
?>
<div class="admin-area admin-area--wide">
	<?php
	$sgfProposalsRenderer->render();
	$rejectedSgfProposalsRenderer->render();
	?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
</script>
