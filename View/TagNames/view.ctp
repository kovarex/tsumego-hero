
<div class="tags-container">
   <div class="tags-content">
				<h1><?php echo $tn['TagName']['name']; ?></h1>
	<p><?php echo $tn['TagName']['description']; ?></p>
	<p><a href="<?php echo $tn['TagName']['link']; ?>" target="_blank"><?php echo $tn['TagName']['link']; ?></a></p>
	<?php if($tn['TagName']['hint'] == 1){ ?>
	<p><i>This tag gives a hint.</i></p>
	<?php } ?>
	<p>Created by <?php echo $tn['TagName']['user'] ?>.</p>
	<?php if($_SESSION['loggedInUser']['User']['isAdmin']>0){ ?>
		<a href="/tag_names/edit/<?php echo $tn['TagName']['id']; ?>">Edit</a> 
		<?php if($_SESSION['loggedInUser']['User']['id']==72){ ?>
			| 
			<a href="/tag_names/delete/<?php echo $tn['TagName']['id']; ?>">Delete</a>
		<?php } ?>
	<?php } ?>
				</div>
        <div class="existing-tags-list">
				Other tags: 
		<?php 
			for($i=0;$i<count($allTags);$i++){
				echo '<a href="/tag_names/view/'.$allTags[$i]['TagName']['id'].'">'.$allTags[$i]['TagName']['name'].'</a>';
				if($i<count($allTags)-1)
					echo ', ';
			}
		?> <a class="add-tag-list-anchor" href="/tag_names/add">[Create new tag]</a>
		</div>
  </div>