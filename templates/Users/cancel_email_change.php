<?php

/**
 * Opened from the link sent to the address that an address change would give up.
 *
 * @var \App\View\AppView $this
 * @var bool $valid
 * @var string $address
 * @var string $token
 */
?>
<div align="center">
<p class="title">
	Cancel the address change
	<br>
</p>

<br>
<?php if (!$valid): ?>
	<p id="email-change-link-invalid">This link is invalid or has expired, so no address change is waiting.</p>
<?php else: ?>
	<p>
		Somebody asked to move your Tsumego Hero account to <strong><?php echo h($address); ?></strong>.<br>
		If that was not you, cancel it here and the account keeps its address.
	</p>
	<br>
	<form method="post" action="/users/cancelEmailChange/<?php echo h($token); ?>">
		<button type="submit" class="btn btn--danger" id="cancel-email-change-button">Cancel the change</button>
	</form>
<?php endif; ?>
</div>
