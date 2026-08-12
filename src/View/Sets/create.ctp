<?php
/**
 * @var View $this
 */
?>

<div align="center">
<br><h1>New Set</h1>
	<?php
		if (Auth::isAdmin()) {
			echo '<p><label><input type="checkbox" name="data[Set][sandbox]" value="1" checked> Sandbox (create placeholder problem)</label></p>';
		}
		echo $this->Form->create('Set');
		echo $this->Form->input('title', array('label' => 'Title: ', 'type' => 'text', 'placeholder' => 'title'));
		echo $this->Form->end('Submit');
	?>
<br><br>
<a href="<?= Auth::isAdmin() ? '/sets/sandbox' : '/sets/mine' ?>"> back </a>
</div>
