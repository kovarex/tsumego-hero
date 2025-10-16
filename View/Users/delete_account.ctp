<div align="center">
<p class="title">
	Request account deletion
	<br> 
</p>

<br><br>

<?php
	if($u['User']['external_id']==null){
		echo 'Confirm deletion request by entering your password.<br><br>';
		echo $this->Form->create('User');
		echo $this->Form->input('delete', array('value' => '', 'label' => '', 'type' => 'password', 'placeholder' => 'password'));
		echo '<br>';
	}else{
		echo 'Submit your deletion request.<br><br>';
		echo $this->Form->create('User');
		echo $this->Form->input('delete', array('value' => 'o474w544a4n4q5g4y2', 'label' => '', 'type' => 'hidden'));
	}
	
	echo $this->Form->end('Submit');
	echo '<br>';
	echo $status;
?>
</div>
<?php
	if($redirect){
		echo '<script type="text/javascript">window.location.href = "/users/view/'.$u['User']['id'].'";</script>';
	}
?>