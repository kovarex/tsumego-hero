

<?php
	if($_SESSION['loggedInUser']['User']['isAdmin']!=1) echo '<script type="text/javascript">window.location.href = "/";</script>';

	echo '<h2>Upload Image for '.$s['Set']['title'].'</h2>';
?>


	<div id="msg4">
		<br>
		<form action="" method="POST" enctype="multipart/form-data">
			<input type="file" name="adminUpload" />
			<input value="Submit" type="submit"/>
		</form>
	</div>
	<br><br>
	<?php
	echo '<a href="/sets/view/'.$id.'">back</a>';
	
	if($redirect) echo '<script type="text/javascript">window.location.href = "/sets/view/'.$id.'";</script>';
	?>
	<style>
		#msg2,
#msg4,
#msg5 {
  display: inline-block;
}
	</style>