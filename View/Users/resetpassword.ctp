<?php
	if(isset($_SESSION['loggedInUser'])){
		echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<?php if(!$sent){ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo $this->Session->flash(); ?>
		 <h1>Please enter your E-Mail</h1>
		<?php echo $this->Form->create('User', array('action' => 'resetpassword')); ?>
		<label for="UserEmail"></label>
		<div class="input text required">
			<label for="UserEmail"></label>
			<input name="data[User][email]" maxlength="50" placeholder="E-Mail" type="text" id="UserEmail" required="required"/>
		</div>
		<?php echo $this->Form->end('Submit'); ?>
	</div>
	<div class="right">
	</div>
</div>
<br>
<?php }else{ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo $this->Session->flash(); ?>
		 <h3>Please check your mailbox.</h3>
		
	</div>
	<div class="right">
	</div>
</div>
<br>
<?php } ?>
<script>
	</script>