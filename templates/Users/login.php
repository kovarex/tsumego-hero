<?php

use App\Utility\CookieFlash;

/**
 * @var View $this
 * @var string $lightDark
 * @var string $redirectSignature
 * @var string $redirectUrl
 */

?>
	<div id="login-box" class="users form">
		<div class="left signin">
			<?php echo CookieFlash::render(); ?>
			<h1>Sign in</h1>
			<?php echo $this->Form->create(null, ['url' => '/users/login', 'id' => 'UserLoginForm']); ?>
			<input type="hidden" name="redirect" value="<?php echo h($redirectUrl); ?>"/>
			<input type="hidden" name="redirect_signature" value="<?php echo h($redirectSignature); ?>"/>
			<div class="input text required">
				<label for="UserName">Username or email</label>
				<input name="username" maxlength="50" type="text" id="UserName" autocomplete="username" required="required"/>
			</div>
			<div class="input password required">
				<label for="password">Password</label>
				<input name="password" type="password" id="password" autocomplete="current-password" required="required"/>
				<button type="button" class="password-toggle" data-target="password">Show</button>
			</div>
			<button type="submit" class="btn">Sign in</button>
			<?php echo $this->Form->end(); ?>
			<p class="hint">Need an account? <a href="/users/add">Sign up</a></p>
			<p class="hint">Forgot password? <a href="/users/resetpassword">Reset it</a></p>

			<?php
			// The redirect rides along in the state so it survives the trip through Google
			$googleState = base64_encode(json_encode([
				'redirect' => $redirectUrl,
				'signature' => $redirectSignature,
			]));
			echo $this->element('google_signin', ['googleState' => $googleState]);
			echo $this->element('password_toggle');
			?>
		</div>
		<div class="right">
		</div>
	</div>

