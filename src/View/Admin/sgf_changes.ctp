<?php

use App\Utility\SgfChangesRenderer;

/**
 * @var View $this
 * @var SgfChangesRenderer $sgfChangesRenderer
 */
?>
<div class="admin-area admin-area--wide sgf-changes">
	<?php $sgfChangesRenderer->render(); ?>
</div>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];

	// The tick of the admin on a change, saved right away, see AdminController::reviewSgfChange
	document.querySelector('.sgf-changes').addEventListener('click', function(event)
	{
		var tick = event.target.closest('.sgf-review__tick');
		if (!tick || tick.disabled)
			return;
		var reviewed = tick.getAttribute('aria-pressed') !== 'true';
		tick.disabled = true;
		fetch('/admin/sgf-changes/review/' + tick.dataset.sgfId, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'reviewed=' + (reviewed ? 1 : 0)
		}).then(function(response)
		{
			if (!response.ok)
				throw new Error('The review was not saved');
			return response.json();
		}).then(function(result)
		{
			tick.setAttribute('aria-pressed', result.reviewed ? 'true' : 'false');
			tick.classList.toggle('sgf-review__tick--reviewed', result.reviewed);
			tick.title = result.reviewed ? 'You reviewed this change, click to take the tick back' : 'Mark as reviewed by you';
		}).catch(function(error)
		{
			alert(error.message);
		}).finally(function()
		{
			tick.disabled = false;
		});
	});
</script>
