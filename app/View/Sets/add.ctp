


<div align="center">
<br><h1>Add Problem</h1>
	<?php
		echo $this->Form->create('Tsumego');
		echo $this->Form->input('num', array('value' => $t['Tsumego']['num']+1, 'label' => 'Number: ', 'type' => 'text', 'placeholder' => 'number'));
		
		echo $this->Form->input('difficulty', array('value' => $t['Tsumego']['difficulty'], 'label' => 'Difficulty:', 'type' => 'text', 'placeholder' => 'difficulty'));
		echo $this->Form->input('set_id', array('type' => 'hidden', 'value' => $t['Tsumego']['set_id']));
		echo $this->Form->input('variance', array('type' => 'hidden', 'value' => 100));
		echo $this->Form->input('description', array('type' => 'hidden', 'value' => $t['Tsumego']['description']));
		
		
		
		echo $this->Form->end('Submit');
	?>
<br><br>
</div>

<?php
	//echo '<pre>'; print_r($t); echo '</pre>'; 