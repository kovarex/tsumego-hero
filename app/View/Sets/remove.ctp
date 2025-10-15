
<?php
	if($_SESSION['loggedInUser']['User']['isAdmin']!=1) echo '<script type="text/javascript">window.location.href = "/";</script>';

?>


<div align="center">
<br><h1>Delete Set</h1>
	<?php
		echo $this->Form->create('Set');
		echo $this->Form->input('hash', array('label' => 'Hash: ', 'type' => 'text', 'placeholder' => 'hash'));
		echo $this->Form->end('Submit');
	?>
<br><br>
<a href="/sets/beta"> back </a>
</div>

<?php
	//echo '<pre>'; print_r($t); echo '</pre>'; 
	//echo $t['Tsumego']['id'];
	if($redirect) echo '<script type="text/javascript">window.location.href = "/sets/beta";</script>';