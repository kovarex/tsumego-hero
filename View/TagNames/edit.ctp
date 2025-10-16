<?php if(!isset($_SESSION['loggedInUser']['User']['id']) || $_SESSION['loggedInUser']['User']['isAdmin']==0)
		echo '<script type="text/javascript">window.location.href = "/";</script>'; ?>
<?php if(isset($saved)) echo '<script type="text/javascript">window.location.href = "/tag_names/view/'.$saved.'";</script>'; ?>

<div class="tags-container">
<div class="tags-content">

<h1>Edit Tag: <?php echo $tn['TagName']['name'] ?></h1>

<?php echo $this->Form->create('TagName'); ?> 

<table>
	<tr>
		<td><label for="TagNameName">Name:</label></td>
		<td><input name="data[TagName][name]" value="<?php echo $tn['TagName']['name'] ?>" placeholder="Name" maxlength="50" type="text" id="TagNameName" disabled="true"></td>
	</tr>
	<tr>
		<td><label for="TagNameDescription">Description:</label></td>
		<td><textarea name="data[TagName][description]" rows="3" placeholder="Description" maxlength="3000" cols="30" id="TagNameDescription"><?php echo $tn['TagName']['description'] ?></textarea></td>
	</tr>
	<tr>
		<td><label for="TagNameLink">Reference:</label></td>
		<td><input name="data[TagName][link]" value="<?php echo $tn['TagName']['link'] ?>" placeholder="Reference" maxlength="500" type="text" id="TagNameLink"></td>
	</tr>
</table>
	<br>
Does the tag give a hint on the solution?<br>
<input type="radio" id="r38" name="data[TagName][hint]" value="1" <?php echo $setHint[0] ?>><label for="r38">yes</label>
<input type="radio" id="r38" name="data[TagName][hint]" value="0" <?php echo $setHint[1] ?>><label for="r38">no</label><br><br>

<?php echo $this->Form->end('Save'); ?>

<br> <br><br> <br><br> <br>
</div>
	<div class="existing-tags-list">
		Other tags: 
		<?php 
			for($i=0;$i<count($allTags)-1;$i++){
				echo '<a href="/tag_names/view/'.$allTags[$i]['TagName']['id'].'">'.$allTags[$i]['TagName']['name'].'</a>';
				if($i<count($allTags)-2)
					echo ', ';
			}
		?> <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>
	</div>
</div>