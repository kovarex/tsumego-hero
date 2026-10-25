<?php
/**
 * @var View $this
 * @var array $user
 */
?>
<div id="changename-box" class="users form">
	<div class="left">
		<h1>Choose your display name</h1>
		<p>Pick a display name to continue.</p>

		<p class="current-name">Currently: <strong><?php echo h($user['display_name']); ?></strong></p>

		<?php echo $this->Form->create('User', ['url' => ['controller' => 'users', 'action' => 'updatename']]); ?>
		<?php echo $this->Form->input('display_name', [
			'label' => 'Display name',
			'placeholder' => 'Your display name',
			'maxlength' => '50',
			'autocomplete' => 'off',
		]); ?>
		<p class="hint">Must be 3-50 characters and unique.</p>
		<?php echo $this->Form->end('Save'); ?>
		<p><a href="/users/logout">Log out</a></p>
	</div>
</div>

<script>
	// Prevent accidental double submission: disable the submit button on submit.
	(function () {
		var box = document.getElementById('changename-box');
		box.addEventListener('submit', function () {
			var btn = box.querySelector('button[type="submit"]');
			if (btn && !btn.dataset.submitted) {
				btn.dataset.submitted = '1';
				btn.disabled = true;
				btn.textContent = 'Saving...';
			}
		});
	}());
</script>

