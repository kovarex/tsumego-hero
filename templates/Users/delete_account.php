<?php

/**
 * @var View $this
 * @var bool $sent
 */

use App\Model\Table\UserTable;
?>
<div align="center">
<p class="title">
	Delete account
	<br>
</p>

<br>
<?php if ($sent): ?>
	<p id="deletion-email-sent">
		We sent you an email with a link to confirm the deletion.<br>
		Your account is deleted only after you confirm it there.
	</p>
<?php else: ?>
	<p>
		When your account is deleted, you are signed out and can't sign in with it anymore.<br>
		Your sets, comments and tags stay on the site, shown as <?php echo h(UserTable::DELETED_NAME); ?>.<br>
		If you change your mind later, contact us and we can restore your account.
	</p>
	<p>To make sure it's you, we'll send you an email with a link to confirm the deletion.</p>
	<br>
	<form method="post" action="/users/delete_account">
		<button type="submit" class="btn btn--danger" id="send-deletion-email-button">Send confirmation email</button>
	</form>
<?php endif; ?>
</div>
