<?php
/**
 * @var View $this
 * @var bool $redirect
 */

?>

<?php
	if(!Auth::isAdmin()) echo '<script type="text/javascript">window.location.href = "/";</script>';

?>


<div align="center">
<br><h1>Delete Set</h1>
	<?php
		echo $this->Form->create('Set');
		echo $this->Form->input('id', array('label' => 'Set ID: ', 'type' => 'text', 'placeholder' => 'Set ID'));
		echo $this->Form->end('Delete');
	?>
<br><br>
<a href="/sets/sandbox"> back </a>
</div>

<?php
	//echo '<pre>'; print_r($t); echo '</pre>';
	//echo $t['Tsumego']['id'];
	if($redirect) echo '<script type="text/javascript">window.location.href = "/sets/sandbox";</script>';
