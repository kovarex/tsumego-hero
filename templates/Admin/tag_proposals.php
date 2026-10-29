<?php

use App\Utility\TagConnectionProposalsRenderer;

/**
 * @var \App\View\AppView $this
 * @var TagConnectionProposalsRenderer $tagConnectionProposalsRenderer
 */
?>
<div class="admin-area admin-area--wide">
	<?php $tagConnectionProposalsRenderer->render(); ?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
</script>
