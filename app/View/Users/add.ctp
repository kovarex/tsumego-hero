<?php
	if(isset($_SESSION['loggedInUser'])){
		//echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<div id="login-box" class="users form">
  <div class="left signin">
		<?php //echo $this->Session->flash(); ?>
		<h1>Sign up</h1>
		<?php echo $this->Form->create('User');?>
		<?php echo $this->Form->input('name', array('label' => '', 'placeholder' => 'Name')); ?>
		<?php echo $this->Form->input('email', array('label' => '', 'placeholder' => 'E-Mail')); ?>
		<?php echo $this->Form->input('pw', array('label' => '','type'=>'password', 'placeholder' => 'Password')); ?>
		<?php echo $this->Form->input('pw2', array('label' => '','type'=>'password', 'placeholder' => 'Retype Password')); ?>
    <?php echo $this->Form->end('Submit');?>
  	You already have an account?<br>
		<a href="/users/login">Sign In</a><br><br>
		
		<div
				id="g_id_onload"
				data-client_id="842499094931-nt12l2fehajo4k7f39bb44fsjl0l4h6u.apps.googleusercontent.com"
				data-context="signin"
				data-ux_mode="popup"
				data-login_uri="/users/googlesignin"
				data-auto_prompt="false"
			></div>
			<div
				class="g_id_signin"
				data-type="standard"
				data-shape="rectangular"
				data-theme="outline"
				data-text="sign_in_with"
				data-size="large"
			></div>
  </div>
  
  <div class="right">
  </div>
</div>
<br>

<script>
	</script>
