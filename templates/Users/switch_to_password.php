<?php
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;

/**
 * Switching an account that signs in with Google only to email and password
 * (AccountController::switchToPassword). The form needs a sign-in with Google from the last
 * few minutes; until then the page offers the Google button, which comes back here.
 *
 * @var \App\View\AppView $this
 * @var array $user
 * @var bool $signedInRecently
 * @var string $googleState
 * @var string $email
 * @var string $displayName
 */
?>
<div id="login-box" class="users form">
	<div class="left signin" id="switch-to-password">
		<h1>Switch to email and password</h1>
		<p>Your account signs in with Google. Give it a password and it signs in with its email and that password; the Google button keeps working too. Your progress, rating and achievements stay, it is the same account.</p>
		<?php if (!$signedInRecently): ?>
			<p id="switch-sign-in-again">For your security, sign in with Google again first (within the last <?php echo intdiv(Auth::RECENT_SIGN_IN_SECONDS, 60); ?> minutes).</p>
			<?php echo $this->element('google_signin', ['googleState' => $googleState]); ?>
		<?php else: ?>
			<form action="/users/switchToPassword" id="UserSwitchToPasswordForm" method="post" accept-charset="utf-8"><div hidden><input type="hidden" name="_method" value="POST"/></div>
			<div class="input text required">
				<label for="SwitchEmail">E-Mail</label>
				<input name="email" autocomplete="email" maxlength="<?php echo UserTable::EMAIL_MAX_LENGTH; ?>" type="email" id="SwitchEmail" required="required" value="<?php echo h($email); ?>"/>
			</div>
			<p class="hint">The address of your Google account. Another one is used once you open the link we send to it.</p>
			<div class="input text">
				<label for="SwitchDisplayName">Display name</label>
				<input name="display_name" maxlength="50" autocomplete="off" type="text" id="SwitchDisplayName" value="<?php echo h($displayName); ?>"/>
			</div>
			<p class="hint">The name other players see, 3-50 characters and unique. Change it here if you like.</p>
			<div class="input password required">
				<label for="SwitchPassword1">Password</label>
				<input name="password1" autocomplete="new-password" type="password" id="SwitchPassword1" required="required"/>
				<button type="button" class="password-toggle" data-target="SwitchPassword1">Show</button>
			</div>
			<div class="input password required">
				<label for="SwitchPassword2">Retype Password</label>
				<input name="password2" autocomplete="new-password" type="password" id="SwitchPassword2" required="required"/>
			</div>
			<p class="hint">At least <?php echo Constants::$MINIMUM_PASSWORD_LENGTH; ?> characters.</p>
			<button type="submit" class="btn btn--primary">Switch</button>
			</form>
			<?php echo $this->element('password_toggle'); ?>
		<?php endif; ?>
		<p class="hint"><a href="/users/settings">Back to the settings</a></p>
	</div>
	<div class="right">
	</div>
</div>
