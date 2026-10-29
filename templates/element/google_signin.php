<?php

/**
 * Sign in with Google: the button Google renders, in the variant that matches the theme.
 * The state travels with the credential so the redirect survives the round trip.
 *
 * @var View $this
 * @var string $lightDark
 * @var string|null $googleState
 */
$googleState = $googleState ?? '';

$this->start('script');
?>
<script src="https://accounts.google.com/gsi/client?hl=en" async defer></script>
<?php
$this->end();
?>
<div id="g_id_onload"
	data-client_id="986748597524-05gdpjqrfop96k6haga9gvj1f61sji6v.apps.googleusercontent.com"
	data-context="signin"
	data-ux_mode="popup"
	data-login_uri="/users/googlesignin"
	data-auto_prompt="false"></div>
<div class="g_id_signin"
	data-type="standard"
	data-shape="rectangular"
	data-theme="<?php echo $lightDark === 'dark' ? 'outline_dark' : 'outline'; ?>"
	data-text="sign_in_with"
	data-size="large"
	data-state="<?php echo h($googleState); ?>"></div>
