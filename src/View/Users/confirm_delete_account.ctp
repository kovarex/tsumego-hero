<?php

/**
 * @var View $this
 * @var bool $valid
 * @var string|null $token
 */
?>
<div align="center">
<p class="title">
	Delete account
	<br>
</p>

<br>
<?php if (!$valid): ?>
	<p id="deletion-link-invalid">This link is invalid or has expired. You can ask for a new one on your profile.</p>
<?php else: ?>
	<p>
		Do you really want to delete your account?<br>
		Your sets, comments and tags stay on the site, shown as <?php echo h(User::DELETED_NAME); ?>.
	</p>
	<br>
	<form method="post" action="/users/confirm_delete_account/<?php echo h($token); ?>">
		<button type="submit" class="btn btn--danger" id="confirm-deletion-button">Delete my account</button>
	</form>
<?php endif; ?>
</div>
