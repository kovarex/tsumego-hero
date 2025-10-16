	<?php
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			echo '<script type="text/javascript">window.location.href = "/";</script>';
		}
	?>
	<script src="https://accounts.google.com/gsi/client" async defer></script>
	<br>
	<div align="center">
	Sign in with:<br><br><br>
	<a class="new-button-inactive">Name</a>
	<a class="new-button" href="/users/login2">Email</a>
	</div>
	<div id="login-box" class="users form">
		<div class="left signin">
			<?php echo $this->Session->flash(); ?>
			 <h1>Sign in</h1>
			<?php echo $this->Form->create('User', array('action' => 'login')); ?>
			<label for="UserName"></label>
			<div class="input text required">
				<label for="UserName"></label>
				<input name="data[User][name]" maxlength="50" placeholder="Username" type="text" id="UserName" required="required"/>
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
		<?php
			if($clearSession){
				?> 
				var PHPSESSID = getCookie("PHPSESSID");
				setCookie("PHPSESSID", PHPSESSID);
				<?php
			}
		?>
		var cacheLifetime = new Date();
		cacheLifetime.setTime(cacheLifetime.getTime()+1*1*1*5*1000);
		cacheLifetime = cacheLifetime.toUTCString()+"";
		
		let cache = getCookie("cache_settings");
		if(cache == 0){
			//deleteAllCookies();
			document.cookie = "cache_settings=1;SameSite=none;expires="+cacheLifetime+";Secure=false";
		}else{
			//alert("f")
		}
		setCookie("z_hash", "0");
		localStorage.setItem("z_hash", "0");
		function deleteAllCookies() {
			const cookies = document.cookie.split(";");
			for (let i = 0; i < cookies.length; i++) {
				const cookie = cookies[i];
				const eqPos = cookie.indexOf("=");
				const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
				document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
			}
		}
		function getCookie(cname){
			var name = cname + "=";
			var decodedCookie = decodeURIComponent(document.cookie);
			var ca = decodedCookie.split(';');
			for(var i = 0; i<ca.length; i++){
				var c = ca[i];
				while (c.charAt(0) == ' '){
					c = c.substring(1);
				}
				if (c.indexOf(name) == 0){
					return c.substring(name.length, c.length);
				}
			}
			return "";
		}
		function setCookie(cookie, value=""){
			let paths = ["/", "/sets", "/sets/view", "/tsumegos/play", "/users", "/users/view"];
			for(let i=0;i<paths.length;i++)
				document.cookie = cookie+"="+value+";SameSite=none;Secure=false;expires="+cacheLifetime+";path="+paths[i];
		}
	</script>