<?php

use App\Utility\AdminActivityRenderer;

/**
 * @var \App\View\AppView $this
 * @var AdminActivityRenderer $adminActivityRenderer
 */
?>
<div class="admin-area admin-area--wide">
	<?php $adminActivityRenderer->render(); ?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
</script>
