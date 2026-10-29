<?php
/**
 * @var View $this
 * @var array $admin
 * @var string $status
 * @var bool $redirect
 */
?>
<div align="center">
<p class="title">
	Remove admin status
	<br> 
</p>

<br><br>

<?php
	if($admin['external_id']==null){
		echo 'Confirm demotion by entering your password.<br><br>';
		echo $this->Form->create(null, ['id' => 'UserDemoteAdminForm']);
		echo '<div class="input password"><label for="UserDemote"></label><input name="data[User][demote]" value="" placeholder="password" type="password" id="UserDemote"/></div>';
		echo '<br>';
	}else{
		echo 'Submit your demotion request.<br><br>';
		echo $this->Form->create(null, ['id' => 'UserDemoteAdminForm']);
		echo '<input type="hidden" name="data[User][demote]" value="o474w544a4n4q5g4y2" id="UserDemote"/>';
	}
	
	echo $this->Form->end('Submit');
	echo '<br>';
	echo $status;
?>
</div>
<?php
	if($redirect){
		echo '<script type="text/javascript">window.location.href = "/users/view/'.$admin['id'].'";</script>';
	}
?>