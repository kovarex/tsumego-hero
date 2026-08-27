<?php

/**
 * @var View $this
 */

?>
<div class="set-edit">
	<div class="set-edit__header">
		<p class="set-edit__crumb"><a href="/sets/mine">My Sets</a> / New Set</p>
		<h1 class="set-edit-title">New Set</h1>
		<p class="hint">Create a set to organize your favorite problems, then add problems with the heart button.</p>
	</div>

	<div class="card card--green set-edit__section">
		<?php echo $this->Form->create('Set', ['id' => 'set-edit-details']); ?>
			<div class="form-field">
				<label class="form-field__label" for="SetTitle">Title</label>
				<input class="form-field__control" type="text" id="SetTitle" name="data[Set][title]" placeholder="My set name" required>
			</div>
			<p class="hint">You can add a description, color and image after creating, in the set editor.</p>
		<?php echo $this->Form->end(['label' => 'Create Set', 'class' => 'btn']); ?>
	</div>
</div>
