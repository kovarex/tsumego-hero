
<?php
	if($_SESSION['loggedInUser']['User']['isAdmin']!=1) echo '<script type="text/javascript">window.location.href = "/";</script>';

?>


<div align="center">
<br><h1>New Set</h1>
	<?php
		echo $this->Form->create('Set');
		echo $this->Form->input('title', array('label' => 'Title: ', 'type' => 'text', 'placeholder' => 'title'));
		echo $this->Form->end('Submit');
	?>
<br><br>
<a href="/sets/beta"> back </a>
</div>

<?php
	//echo '<pre>'; print_r($t); echo '</pre>'; 
	//echo $t['Tsumego']['id'];
	if($redirect) echo '<script type="text/javascript">window.location.href = "/sets/beta";</script>';