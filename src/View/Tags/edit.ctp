<?php

/**
 * @var View $this
 * @var array $allTags
 * @var array $tag
 * @var bool $canAddTag
 */

?>
<div class="tags-container">
<div class="tags-content">

<h1>Edit Tag: <?php echo h($tag['name']) ?></h1>

<form method="post" action="/tags/editAction/<?php echo $tag['id'] ?>">
	<div class="stack">
		<div class="form-field">
			<label class="form-field__label" for="tag_name">Name:</label>
			<input class="form-field__control" name="tag_name" value="<?php echo h($tag['name']) ?>" placeholder="Name" maxlength="50" type="text" id="tag_name" disabled="true">
		</div>
		<div class="form-field">
			<label class="form-field__label" for="tag_description">Description:</label>
			<textarea class="form-field__control" name="tag_description" rows="3" placeholder="Description" maxlength="3000" cols="30" id="tag_description"><?php echo h($tag['description']) ?></textarea>
		</div>
		<div class="form-field">
			<label class="form-field__label" for="tag_link">Reference:</label>
			<input class="form-field__control" name="tag_link" value="<?php echo h($tag['link']) ?>" placeholder="Reference" maxlength="500" type="text" id="tag_link">
		</div>
		<p>Does the tag give a hint on the solution?</p>
		<div class="field">
			<label class="field__option"><input type="radio" id="tag_hint_true" name="tag_hint" value="1"<?php echo $tag['hint'] ? ' checked="checked"' : ''; ?>> yes</label>
			<label class="field__option"><input type="radio" id="tag_hint_false" name="tag_hint" value="0"<?php echo $tag['hint'] ? '' : ' checked="checked"'; ?>> no</label>
		</div>
		<input type="submit" value="submit" id="submit_tag">
	</div>
</form>

<br> <br><br> <br><br> <br>
</div>
	<div class="existing-tags-list">
		Other tags:
		<?php echo implode(', ', array_map(fn($tag) => '<a href="/tags/view/' . $tag['id'] . '">' . h($tag['name']) . '</a>', $allTags)); ?>
		<?php if ($canAddTag) { ?>
			<a class="add-tag-list-anchor" href="/tags/add">[Create new tag]</a>
		<?php } ?>
	</div>
</div>
