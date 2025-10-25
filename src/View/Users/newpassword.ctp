<?php
	if(Auth::isLoggedIn()){
		echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<?php if($valid){ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo $this->Session->flash(); ?>
		 <h1>New password:</h1>
		<form action="/users/newpassword/<?php echo $checksum; ?>" id="UserNewpasswordForm" method="post" accept-charset="utf-8">
		<div style="display:none;"><input type="hidden" name="_method" value="POST"></div>	
		<div class="input text required">
			<label for="password"></label>
			<input name="data[User][password]" maxlength="50" placeholder="Password" type="text" id="password" required="required"/>
		</div>
		<?php echo $this->Form->end('Submit'); ?>
		
	</div>
	<div class="right">
	</div>
</div>
<br>
<?php }else{ 
	if($done){
?>
	<div id="login-box" class="users form">
	<div class="left signin">
		 <h1>Your password has been changed.</h1>
		 <a href="/users/login">Back to Sign In</a>
	</div>
	
	<div class="right">
	</div>
	</div>
<?php }else{ ?>
	<div id="login-box" class="users form">
	<div class="left signin">
		 <h1>Chechsum error.</h1>
		 <a href="/users/login">Back to Sign In</a>
	</div>
	
	<div class="right">
	</div>
	</div>
	<?php
}


} ?>
<script>
	</script>