
<div class="tags-container">
   <div class="tags-content">
				<h1><?php echo $tn['Tag']['name']; ?></h1>
	<p><?php echo $tn['Tag']['description']; ?></p>
	<p><a href="<?php echo $tn['Tag']['link']; ?>" target="_blank"><?php echo $tn['Tag']['link']; ?></a></p>
	<?php if($tn['Tag']['hint'] == 1){ ?>
	<p><i>This tag gives a hint.</i></p>
	<?php } ?>
	<p>Created by <?php echo $tn['Tag']['user'] ?>.</p>
	<?php if(Auth::isAdmin()){ ?>
		<a href="/tags/edit/<?php echo $tn['Tag']['id']; ?> id="tag-edit">Edit</a>
		<?php if(Auth::getUserID()==72){ ?>
			|
			<a href="/tags/delete/<?php echo $tn['Tag']['id']; ?>">Delete</a>
		<?php } ?>
	<?php } ?>
				</div>
        <div class="existing-tags-list">
				Other tags:
		<?php
			for($i=0;$i<count($allTags);$i++){
				echo '<a href="/tags/view/'.$allTags[$i]['Tag']['id'].'">'.$allTags[$i]['Tag']['name'].'</a>';
				if($i<count($allTags)-1)
					echo ', ';
			}
		?> <a class="add-tag-list-anchor" href="/tags/add">[Create new tag]</a>
		</div>
  </div>
