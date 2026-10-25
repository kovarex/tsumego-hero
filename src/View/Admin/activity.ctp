<?php

use App\Utility\AdminActivityRenderer;

/**
 * @var View $this
 * @var AdminActivityRenderer $adminActivityRenderer
 */
?>
<div class="admin-area admin-area--wide">
	<?php $adminActivityRenderer->render(); ?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
</script>
