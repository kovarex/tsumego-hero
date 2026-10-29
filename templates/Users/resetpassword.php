<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\CookieFlash;

/**
 * @var View $this
 * @var string $lightDark
 * @var bool $sent
 */

	if(Auth::isLoggedIn()){
		echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<?php if(!$sent){ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo CookieFlash::render(); ?>
		<h1>Reset password</h1>
		<form action="/users/resetpassword" id="UserResetpasswordForm" method="post" accept-charset="utf-8"><div style="display:none;"><input type="hidden" name="_method" value="POST"/></div>
		<div class="input text required">
			<label for="UserEmail">E-Mail</label>
			<input name="email" maxlength="<?php echo UserTable::EMAIL_MAX_LENGTH; ?>" type="text" id="UserEmail" autocomplete="email" required="required"/>
		</div>
		<button type="submit" class="btn">Send reset link</button>
		</form>
		<p class="hint">Remembered it? <a href="/users/login">Sign in</a></p>
		<p class="hint">Signed up with Google? Sign in there instead:</p>
		<?php echo $this->element('google_signin'); ?>
	</div>
	<div class="right">
	</div>
</div>
<br>
<?php }else{ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo CookieFlash::render(); ?>
		<h1>Reset password</h1>
		<p class="hint">Please check your mailbox.</p>
		<p class="hint">Remembered it? <a href="/users/login">Sign in</a></p>
	</div>
	<div class="right">
	</div>
</div>
<br>
<?php } ?>
<script>
	</script>