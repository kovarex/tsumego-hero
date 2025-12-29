<div class="tags-container">
<div class="tags-content">

<h1>Edit Tag: <?php echo $tag['name'] ?></h1>

<form method="post" action="/tags/editAction/<?php echo $tag['id'] ?>">
	<table>
		<tr>
			<td><label for="tag_name">Name:</label></td>
			<td><input name="tag_name" value="<?php echo $tag['name'] ?>" placeholder="Name" maxlength="50" type="text" id="tag_name" disabled="true"></td>
		</tr>
		<tr>
			<td><label for="tag_description">Description:</label></td>
			<td><textarea name="tag_description" rows="3" placeholder="Description" maxlength="3000" cols="30" id="tag_description"><?php echo $tag['description'] ?></textarea></td>
		</tr>
		<tr>
			<td><label for="tag_link">Reference:</label></td>
			<td><input name="tag_link" value="<?php echo $tag['link'] ?>" placeholder="Reference" maxlength="500" type="text" id="tag_link"></td>
		</tr>
	</table>
	<br>
	Does the tag give a hint on the solution?<br>
	<input type="radio" id="tag_hint_true" name="tag_hint" value="1"<?php echo $tag['hint'] ? ' checked="checked"' : ''; ?>><label for="tag_hint_true">yes</label>
	<input type="radio" id="tag_hint_false" name="tag_hint" value="0"<?php echo $tag['hint'] ? '' : ' checked="checked"'; ?>><label for="tag_hint_false">no</label><br><br>
	<br><br>
	<input type="submit" value="submit" id="submit_tag">
</form>

<br> <br><br> <br><br> <br>
</div>
	<div class="existing-tags-list">
		Other tags:
		<?php
			foreach ($tags as $index => $tag)
			{
				echo '<a href="/tags/view/' . $tag['id'] . '">' . $tag['name'] . '</a>';
				if ($index < count($tags) - 2)
					echo ', ';
			}
		?> <a class="add-tag-list-anchor" href="/tags/add">[Create new tag]</a>
	</div>
</div>
