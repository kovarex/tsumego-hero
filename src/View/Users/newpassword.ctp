<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;

/**
 * @var View $this
 * @var string $checksum
 * @var bool $done
 * @var bool $valid
 */

	if(Auth::isLoggedIn()){
		echo '<script type="text/javascript">window.location.href = "/sets";</script>';
	}
?>
<?php if($valid){ ?>
<div id="login-box" class="users form">
	<div class="left signin">
		<?php echo CookieFlash::render(); ?>
		 <h1>New password:</h1>
		<form action="/users/newpassword/<?php echo urlencode($checksum); ?>" id="UserNewpasswordForm" method="post" accept-charset="utf-8">
		<div style="display:none;"><input type="hidden" name="_method" value="POST"></div>	
                <div class="input">
                        <input name="data[User][password1]" maxlength="50" placeholder="New password" aria-label="New password" type="password" id="password1" autocomplete="new-password" required="required"/>
                </div>
                <div class="input">
                        <input name="data[User][password2]" maxlength="50" placeholder="New password again" aria-label="New password again" type="password" id="password2" autocomplete="new-password" required="required"/>
                </div>
                <div class="hint">At least <?php echo Constants::$MINIMUM_PASSWORD_LENGTH; ?> characters.</div>
                <button type="submit" class="btn" id="newpassword-submit">Submit</button>
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
		 <h1>This link is not valid any more.</h1>
		 <a href="/users/resetpassword">Ask for a new one</a>
		 <br>
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