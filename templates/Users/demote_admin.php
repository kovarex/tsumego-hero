<?php
/**
 * @var \App\View\AppView $this
 * @var array $admin
 * @var string|null $error Why the role stayed, after a submit that did not demote
 * @var string|null $demotionToken What a Google account confirms with, it has no password
 */
?>
<div class="legacy-center">
<p class="title">
	Remove admin status
	<br> 
</p>

<br><br>

<?php
	if ($demotionToken === null)
	{
		echo 'Confirm demotion by entering your password.<br><br>';
		echo '<form action="/users/demote_admin" id="UserDemoteAdminForm" method="post" accept-charset="utf-8"><div hidden><input type="hidden" name="_method" value="POST"/></div>';
		echo '<div class="input password"><label for="UserDemote"></label><input name="demote" value="" placeholder="password" type="password" id="UserDemote"/></div>';
		echo '<br>';
	}
	else
	{
		echo 'Submit your demotion request.<br><br>';
		echo '<form action="/users/demote_admin" id="UserDemoteAdminForm" method="post" accept-charset="utf-8"><div hidden><input type="hidden" name="_method" value="POST"/></div>';
		echo '<input type="hidden" name="demote" value="' . h($demotionToken) . '" id="UserDemote"/>';
	}

	echo '<div class="submit"><input type="submit" value="Submit"/></div></form>';
	echo '<br>';
	if ($error !== null)
		echo '<p class="demote-admin__error">' . h($error) . '</p>';
?>
</div>