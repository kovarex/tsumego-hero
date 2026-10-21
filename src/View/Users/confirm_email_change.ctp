<?php

/**
 * Opened from the link sent to the new address of an address change.
 *
 * @var View $this
 * @var bool $valid
 * @var string $address
 * @var string $token
 */
?>
<div align="center">
<p class="title">
	Confirm address
	<br>
</p>

<br>
<?php if (!$valid): ?>
	<p id="email-change-link-invalid">This link is invalid or has expired. You can ask for a new address in your settings.</p>
<?php else: ?>
	<p>
		Use <strong><?php echo h($address); ?></strong> as the address of your Tsumego Hero account?<br>
		Until you do, the account keeps the address it has now.
	</p>
	<br>
	<form method="post" action="/users/confirmEmailChange/<?php echo h($token); ?>">
		<button type="submit" class="btn" id="confirm-email-change-button">Use this address</button>
	</form>
<?php endif; ?>
</div>
