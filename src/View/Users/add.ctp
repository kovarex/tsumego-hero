<?php
use App\Model\Table\UserTable;
use App\Utility\Constants;

/**
 * @var View $this
 * @var string $lightDark
 * @var string $redirectUrl
 * @var string $redirectSignature
 */
?>
<div id="login-box" class="users form">
  <div class="left signin">
		<h1>Sign up</h1>
		<?php echo $this->Form->create(false, ['id' => 'UserAddForm']);?>
		<div class="input text required">
			<label for="UserName">Name</label>
			<input name="data[User][name]" autocomplete="username" maxlength="50" type="text" id="UserName"/>
		</div>
		<div class="input text required">
			<label for="UserEmail">E-Mail</label>
			<input name="data[User][email]" autocomplete="email" maxlength="<?php echo UserTable::EMAIL_MAX_LENGTH; ?>" type="email" id="UserEmail" required="required"/>
		</div>
		<div class="input password required">
			<label for="UserPassword1">Password</label>
			<input name="data[User][password1]" autocomplete="new-password" type="password" id="UserPassword1"/>
			<button type="button" class="password-toggle" data-target="UserPassword1">Show</button>
		</div>
		<div class="input password required">
			<label for="UserPassword2">Retype Password</label>
			<input name="data[User][password2]" autocomplete="new-password" type="password" id="UserPassword2"/>
		</div>
		<p class="hint">At least <?php echo Constants::$MINIMUM_PASSWORD_LENGTH; ?> characters.</p>
		<button type="submit" class="btn">Sign up</button>
		<?php echo $this->Form->end(); ?>
		<p class="hint">Already have an account? <a href="/users/login">Sign in</a></p>

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
<br>

<script>
	</script>
