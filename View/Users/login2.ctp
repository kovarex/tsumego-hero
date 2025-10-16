<?php
	if(isset($_SESSION['loggedInUser'])){
		echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<br>
<div align="center">
Sign in with:<br><br><br>
<a class="new-button"  href="/users/login">Name</a>
<a class="new-button-inactive">Email</a>
</div>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo $this->Session->flash(); ?>
		 <h1>Sign in</h1>
		<?php echo $this->Form->create('User', array('action' => 'login2')); ?>
		<label for="UserEmail"></label>
		<div class="input text required">
			<label for="UserEmail"></label>
			<input name="data[User][email]" maxlength="50" placeholder="Email" type="text" id="UserEmail" required="required"/>
		</div>
		<label for="UserPassword"></label>
		<div class="input password required">
			<label for="UserPw"></label>
			<input name="data[User][pw]" type="password" placeholder="Password" id="UserPw" required="required"/>
		</div>
		<?php echo $this->Form->end('Submit'); ?>
		Need an account?<br>
		<a href="/users/add">Sign Up</a><br><br>
		Forgot password?<br>
		<a href="/users/resetpassword">Reset</a>
<br><br>
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
		var cacheLifetime = new Date();
		cacheLifetime.setTime(cacheLifetime.getTime()+1*1*1*5*1000);
		cacheLifetime = cacheLifetime.toUTCString()+"";

		setCookie("z_hash", "0");
		localStorage.setItem("z_hash", "0");
	
		function setCookie(cookie, value=""){
			let paths = ["/", "/sets", "/sets/view", "/tsumegos/play", "/users", "/users/view"];
			for(let i=0;i<paths.length;i++)
				document.cookie = cookie+"="+value+";SameSite=none;Secure=false;expires="+cacheLifetime+";path="+paths[i];
		}
	</script>