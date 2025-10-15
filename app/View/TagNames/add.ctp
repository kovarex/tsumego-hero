<?php if(isset($saved)) echo '<script type="text/javascript">window.location.href = "/tag_names/view/'.$saved.'";</script>'; ?>

<div class="tags-container">
<div class="tags-content"  align="center">
  <h1>Add Tag</h1>

  <?php echo $this->Form->create('TagName'); ?> 
  
  <table>
    <tr>
      <td><label for="TagNameName">Name:</label></td>
      <td><input name="data[TagName][name]" placeholder="Name" maxlength="50" type="text" id="TagNameName"></td>
    </tr>
    <tr>
      <td><label for="TagNameDescription">Description:</label></td>
      <td><textarea name="data[TagName][description]" rows="3" placeholder="Description" maxlength="500" cols="30" id="TagNameDescription"></textarea></td>
    </tr>
    <tr>
      <td><label for="TagNameLink">Reference:</label></td>
      <td><input name="data[TagName][link]" placeholder="Reference" maxlength="500" type="text" id="TagNameLink"></td>
    </tr>
  </table>
    <br>
  Does the tag give a hint on the solution?<br>
  <input type="radio" id="r38" name="data[TagName][hint]" value="1"><label for="r38">yes</label>
  <input type="radio" id="r38" name="data[TagName][hint]" value="0"><label for="r38">no</label><br><br>
  <?php
    if($alreadyExists){
      echo '<div style="color:#e03c4b">Tag already exists.</div><br>';
    }
  ?>
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