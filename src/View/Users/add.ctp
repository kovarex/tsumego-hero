<?php
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
		<?php echo $this->Form->create('User');?>
		<div class="input text required">
			<label for="UserName">Name</label>
			<?php echo $this->Form->input('name', ['label' => false, 'div' => false, 'autocomplete' => 'username']); ?>
		</div>
		<div class="input text required">
			<label for="UserEmail">E-Mail</label>
			<?php echo $this->Form->input('email', ['label' => false, 'div' => false, 'autocomplete' => 'email']); ?>
		</div>
		<div class="input password required">
			<label for="UserPassword1">Password</label>
			<?php echo $this->Form->input('password1', ['label' => false, 'div' => false, 'type' => 'password', 'autocomplete' => 'new-password']); ?>
			<button type="button" class="password-toggle" data-target="UserPassword1">Show</button>
		</div>
		<div class="input password required">
			<label for="UserPassword2">Retype Password</label>
			<?php echo $this->Form->input('password2', ['label' => false, 'div' => false, 'type' => 'password', 'autocomplete' => 'new-password']); ?>
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
