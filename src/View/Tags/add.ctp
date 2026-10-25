<?php

/**
 * @var View $this
 * @var array $allTags
 */

?>
<div class="tags-container">
<div class="tags-content" style="text-align:center">
	<h1>Add Tag</h1>
	<form method="post" action="/tags/addAction">
		<div class="stack">
			<div class="form-field">
				<label class="form-field__label" for="tag_name">Name:</label>
				<input class="form-field__control" name="tag_name" placeholder="Name" maxlength="50" type="text" id="tag_name">
			</div>
			<div class="form-field">
				<label class="form-field__label" for="tag_description">Description:</label>
				<textarea class="form-field__control" name="tag_description" rows="3" placeholder="Description" maxlength="500" cols="30" id="tag_description"></textarea>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="tag_reference">Reference:</label>
				<input class="form-field__control" name="tag_reference" placeholder="Reference" maxlength="500" type="text" id="tag_reference">
			</div>
			<p>Does the tag give a hint on the solution?</p>
			<div class="field">
				<label class="field__option"><input type="radio" id="tag_hint_true" name="tag_hint" value="1"> yes</label>
				<label class="field__option"><input type="radio" id="tag_hint_false" name="tag_hint" value="0" checked="checked"> no</label>
			</div>
			<input type="submit" value="submit" id="submit_tag">
		</div>
	</form>
	<br><br><br><br><br><br>
	</div>
	<div class="existing-tags-list">
		Other tags:
		<?php echo implode(', ', array_map(fn($tag) => '<a href="/tags/view/' . $tag['id'] . '">' . h($tag['name']) . '</a>', $allTags)); ?>
	</div>
</div>
