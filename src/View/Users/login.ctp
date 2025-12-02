	<script src="https://accounts.google.com/gsi/client" async defer></script>
	<br>
	<div id="login-box" class="users form">
		<div class="left signin">
			<?php echo $this->Flash->render(); ?>
			 <h1>Sign in</h1>
			<?php echo $this->Form->create('User', ['action' => 'login']); ?>
			<input type="hidden" name="redirect" value="<?php echo h($redirectUrl); ?>"/>
			<input type="hidden" name="redirect_signature" value="<?php echo h($redirectSignature); ?>"/>
			<label for="UserName"></label>
			<div class="input text required">
				<label for="UserName"></label>
				<input name="username" maxlength="50" placeholder="Username or email" type="text" id="UserName" required="required"/>
			</div>
			<label for="UserPassword"></label>
			<div class="input password required">
				<label for="password"></label>
				<input name="password" type="password" placeholder="Password" id="password" required="required"/>
			</div>
			<?php echo $this->Form->end('Submit'); ?>
			Need an account?<br>
			<a href="/users/add">Sign Up</a><br><br>
			Forgot password?<br>
			<a href="/users/resetpassword">Reset</a>
			<br><br>

			<?php
			// Encode redirect URL + signature in state for Google Sign-In (stateless)
			$googleState = base64_encode(json_encode([
				'redirect' => $redirectUrl,
				'signature' => $redirectSignature,
			]));
			?>
			<div
				id="g_id_onload"
				data-client_id="986748597524-05gdpjqrfop96k6haga9gvj1f61sji6v.apps.googleusercontent.com"
				data-context="signin"
				data-ux_mode="popup"
				data-login_uri="/users/googlesignin"
				data-auto_prompt="false"></div>
			<div
				class="g_id_signin"
				data-type="standard"
				data-shape="rectangular"
				data-theme="outline"
				data-text="sign_in_with"
				data-size="large"
				data-state="<?php echo h($googleState); ?>"></div>
		</div>
		<div class="right">
		</div>
	</div>
	<br>

