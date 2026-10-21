<?php

/**
 * Reveals what was typed into a password field. One delegated handler per page, the
 * buttons carry the id of the field they belong to.
 *
 * @var View $this
 */
?>
<script>
document.addEventListener('click', function(event) {
	var toggle = event.target.closest('.password-toggle');
	if (!toggle)
		return;

	var field = document.getElementById(toggle.dataset.target);
	if (!field)
		return;

	var reveal = field.type === 'password';
	field.type = reveal ? 'text' : 'password';
	toggle.textContent = reveal ? 'Hide' : 'Show';
});
</script>
