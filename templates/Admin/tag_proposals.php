<?php

use App\Utility\TagConnectionProposalsRenderer;

/**
 * @var View $this
 * @var TagConnectionProposalsRenderer $tagConnectionProposalsRenderer
 */
?>
<div class="admin-area admin-area--wide">
	<?php $tagConnectionProposalsRenderer->render(); ?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
</script>
