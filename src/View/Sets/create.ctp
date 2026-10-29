<?php

use App\Utility\Util;

/**
 * @var View $this
 */

?>
<div class="set-edit">
	<div class="set-edit__header">
		<p class="set-edit__crumb"><a href="/sets/mine">My Sets</a> / New Set</p>
		<h1 class="set-edit-title">New Set</h1>
		<p class="hint"><?php echo isset($this->params['url']['sandbox']) ? 'Create a new collection for the sandbox.' : 'Create a set to organize your favorite problems.'; ?></p>
	</div>

	<div class="card card--green set-edit__section">
		<form action="<?php echo h(Util::requestUri()); ?>" id="set-edit-details" method="post" accept-charset="utf-8"><div style="display:none;"><input type="hidden" name="_method" value="POST"/></div>
			<div class="form-field">
				<label class="form-field__label" for="SetTitle">Title</label>
				<input class="form-field__control" type="text" id="SetTitle" name="title" placeholder="My set name" required>
			</div>
			<p class="hint">You can add a description, color and image after creating, in the set editor.</p>
		<div class="submit"><input class="btn" type="submit" value="Create Set"/></div></form>
	</div>
</div>
