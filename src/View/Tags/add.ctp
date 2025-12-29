<div class="tags-container">
<div class="tags-content" style="text-align:center">
	<h1>Add Tag</h1>
	<form method="post" action="/tags/addAction">
		<table>
			<tr>
				<td><label for="tag_name">Name:</label></td>
				<td><input name="tag_name" placeholder="Name" maxlength="50" type="text" id="tag_name"></td>
			</tr>
			<tr>
				<td><label for="tag_description">Description:</label></td>
				<td><textarea name="tag_description" rows="3" placeholder="Description" maxlength="500" cols="30" id="tag_description"></textarea></td>
			</tr>
			<tr>
				<td><label for="tag_reference">Reference:</label></td>
				<td><input name="tag_reference" placeholder="Reference" maxlength="500" type="text" id="tag_reference"></td>
			</tr>
		</table>
		<br>
		Does the tag give a hint on the solution?<br>
		<input type="radio" id="tag_hint_true" name="tag_hint" value="1"><label for="tag_hint_true">yes</label>
		<input type="radio" id="tag_hint_false" name="tag_hint" value="0"><label for="tag_hint_false">no</label><br><br>
		<input type="submit" value="submit" id="submit_tag">
	</form>
	<br><br><br><br><br><br>
	</div>
	<div class="existing-tags-list">
		Other tags:
		<?php echo implode(', ', array_map(fn($tag) => '<a href="/tags/view/' . $tag['Tag']['id'] . '">' . $tag['Tag']['name'] . '</a>', $allTags)); ?>
	</div>
</div>
