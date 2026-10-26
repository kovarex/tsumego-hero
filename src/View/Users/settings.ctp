<?php

use App\Utility\Auth;
use App\Utility\Constants;

/**
 * Everything a player can change about their account and about how the site
 * behaves. It sits next to the other profile tabs, so it is the same page for the
 * same player, only not public.
 *
 * @var View $this
 * @var array $user
 * @var int $levelBar
 * @var string $lightDark
 * @var int $tsumegoStatusToRestCount
 * @var bool $canResetOldTsumegoStatuses
 * @var string|null $unconfirmedEmail
 */
?>

<div align="center">
	<?php echo $this->element('user_header', ['user' => $user, 'activeTab' => 'settings']); ?>
</div>

<div class="cluster">
	<div class="card card--green">
		<div class="card__title">Account</div>
		<table class="stat" id="name-and-email-table">
			<tr>
				<td><strong>Email:</strong> <?php echo h($user['email']); ?></td>
				<td>
					<?php if (!User::isGoogleUser($user)): ?>
						<button type="button" id="change-email" class="link">change</button>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ($unconfirmedEmail): ?>
			<tr>
				<td colspan="2">
					<span class="hint" id="unconfirmed-email">Waiting for the confirmation of <?php echo h($unconfirmedEmail); ?></span>
					<form method="post" action="/users/cancelEmailChange/<?php echo h($user['unconfirmed_email_cancel_token']); ?>" style="display:inline;">
						<button type="submit" class="link" id="cancel-email-change">cancel</button>
					</form>
				</td>
			</tr>
			<?php endif; ?>
			<?php if (!User::isGoogleUser($user)): ?>
			<tr>
				<td colspan="2">
					<div id="email-form" style="display:none;margin-top:3px;">
						<?php
						echo $this->Form->create('User', ['url' => ['controller' => 'users', 'action' => 'emailPreference']]);
						echo '<div class="input"><input type="email" name="data[User][email]" placeholder="New e-mail" aria-label="New e-mail" maxlength="' . User::EMAIL_MAX_LENGTH . '" style="width:150px;" required="required"></div>';
						echo '<div class="input"><input type="password" name="data[User][password]" placeholder="Password" aria-label="Password" autocomplete="current-password" style="width:150px;" required="required"></div>';
						echo '<div class="hint">The address changes once you open the link sent to it.</div>';
						echo '<div class="submit"><input class="btn" value="Submit" type="submit"></div>';
						echo $this->Form->end();
						?>
					</div>
				</td>
			</tr>
			<tr>
				<td><strong>Password:</strong> ********</td>
				<td><button type="button" id="change-password" class="link">change</button></td>
			</tr>
			<tr>
				<td colspan="2">
					<div id="password-form" style="display:none;margin-top:3px;">
						<?php
						echo $this->Form->create('User', ['url' => ['controller' => 'users', 'action' => 'passwordPreference']]);
						echo '<div class="input"><input type="password" name="current" placeholder="Current password" aria-label="Current password" autocomplete="current-password" style="width:150px;" required="required"></div>';
						echo '<div class="input"><input type="password" name="new1" placeholder="New password" aria-label="New password" autocomplete="new-password" style="width:150px;" required="required"></div>';
						echo '<div class="input"><input type="password" name="new2" placeholder="New password again" aria-label="New password again" autocomplete="new-password" style="width:150px;" required="required"></div>';
						echo '<div class="hint">At least ' . Constants::$MINIMUM_PASSWORD_LENGTH . ' characters.</div>';
						echo '<div class="submit"><input class="btn" value="Submit" type="submit"></div>';
						echo $this->Form->end();
						?>
					</div>
				</td>
			</tr>
			<tr>
				<td><strong>Username:</strong> <?php echo h($user['name'] ?? ''); ?></td>
				<td></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td><strong>Avatar:</strong></td>
				<td>
					<?php
					$chosenAvatar = User::avatarChoice($user);
					foreach (User::avatarChoices($user) as $avatarValue => $avatarLabel)
					{
						$avatarChecked = $chosenAvatar === $avatarValue ? 'checked="checked"' : '';
						echo '<label class="field__option"><input type="radio" name="avatar" value="' . $avatarValue . '" onclick="avatarPreference(this.value);" ' . $avatarChecked . '> ' . h($avatarLabel) . '</label>';
					}
					if ($chosenAvatar === User::AVATAR_GRAVATAR)
						echo '<div class="hint">Change it at <a href="https://gravatar.com" target="_blank" rel="noopener">gravatar.com</a></div>';
					?>
				</td>
			</tr>
		</table>
	</div>
	<div class="card card--green">
		<div class="card__title">Messages</div>
		<div class="stack">
			<div class="field">
				<span class="field__label">Email me when a message arrives</span>
				<?php $emailOnNewMessage = (bool) $user['email_on_new_message']; ?>
				<label class="field__option"><input type="radio" name="emailOnNewMessage" value="1" onclick="emailOnNewMessagePreference(this.value);" <?php echo $emailOnNewMessage ? 'checked="checked"' : ''; ?>> Yes</label>
				<label class="field__option"><input type="radio" name="emailOnNewMessage" value="0" onclick="emailOnNewMessagePreference(this.value);" <?php echo $emailOnNewMessage ? '' : 'checked="checked"'; ?>> No</label>
				<div class="hint">One email for the first message of a player you haven't read yet, to <?php echo h($user['email']); ?>.</div>
			</div>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Appearance</div>
		<div class="stack">
			<div class="field">
				<span class="field__label">Progress bar</span>
				<?php
				$levelBarDisplay = (int) $levelBar;
				?>
				<label class="field__option"><input type="radio" id="levelBarDisplay1" name="levelBarDisplay" value="1" onclick="levelBarChange(1);" <?php echo $levelBarDisplay === User::LEVEL_BAR_LEVEL ? 'checked="checked"' : ''; ?>> Level</label>
				<label class="field__option"><input type="radio" id="levelBarDisplay2" name="levelBarDisplay" value="2" onclick="levelBarChange(2);" <?php echo $levelBarDisplay === User::LEVEL_BAR_RATING ? 'checked="checked"' : ''; ?>> Rating</label>
			</div>
			<div class="field">
				<span class="field__label">Theme</span>
				<label class="field__option"><input type="radio" name="theme" value="light" onclick="settingsTheme('light');" <?php echo $lightDark === 'dark' ? '' : 'checked="checked"'; ?>> Light</label>
				<label class="field__option"><input type="radio" name="theme" value="dark" onclick="settingsTheme('dark');" <?php echo $lightDark === 'dark' ? 'checked="checked"' : ''; ?>> Dark</label>
			</div>
			<div class="field">
				<span class="field__label">Sound</span>
				<?php $soundIsOn = User::soundIsOn($user); ?>
				<label class="field__option"><input type="radio" name="sound" value="on" onclick="settingsSound('on');" <?php echo $soundIsOn ? 'checked="checked"' : ''; ?>> On</label>
				<label class="field__option"><input type="radio" name="sound" value="off" onclick="settingsSound('off');" <?php echo $soundIsOn ? '' : 'checked="checked"'; ?>> Off</label>
			</div>
			<div class="field">
				<span class="field__label">Date format</span>
				<?php
				$dateFormat = Auth::getDateFormat();
				foreach ([
					User::DATE_FORMAT_ISO => '2026-02-28 14:05',
					User::DATE_FORMAT_DAY_MONTH_YEAR => '28. 2. 2026 14:05',
					User::DATE_FORMAT_MONTH_DAY_YEAR => '02/28/2026 2:05 PM',
					User::DATE_FORMAT_BROWSER => 'Browser language'] as $dateFormatValue => $dateFormatLabel)
				{
					$dateFormatChecked = $dateFormat === $dateFormatValue ? 'checked="checked"' : '';
					echo '<label class="field__option"><input type="radio" name="dateFormat" value="' . $dateFormatValue . '" onclick="dateFormatPreference(this.value);" ' . $dateFormatChecked . '> ' . $dateFormatLabel . '</label>';
				}
				?>
			</div>
		</div>
	</div>
</div>

<div class="cluster">
	<div class="card card--green">
		<div class="card__title">Playing</div>
		<div class="stack">
			<div class="field">
				<span class="field__label">Player color</span>
				<?php
				$playerColor = Auth::getPrefPlayerColor();
				$playerColorRandomChecked = $playerColor === User::PREF_PLAYER_COLOR_RANDOM ? 'checked="checked"' : '';
				$playerColorOriginalChecked = $playerColor === User::PREF_PLAYER_COLOR_ORIGINAL ? 'checked="checked"' : '';
				?>
				<label class="field__option"><input type="radio" name="playerColor" value="<?php echo User::PREF_PLAYER_COLOR_RANDOM; ?>" onclick="playerColorPreference(this.value);" <?php echo $playerColorRandomChecked; ?>> Random</label>
				<label class="field__option"><input type="radio" name="playerColor" value="<?php echo User::PREF_PLAYER_COLOR_ORIGINAL; ?>" onclick="playerColorPreference(this.value);" <?php echo $playerColorOriginalChecked; ?>> Original</label>
			</div>
			<div class="field">
				<span class="field__label">Board orientation</span>
				<?php
				$boardOrientation = Auth::getPrefBoardOrientation();
				$boardOrientationRandomChecked = $boardOrientation === User::PREF_BOARD_ORIENTATION_RANDOM ? 'checked="checked"' : '';
				$boardOrientationOriginalChecked = $boardOrientation === User::PREF_BOARD_ORIENTATION_ORIGINAL ? 'checked="checked"' : '';
				?>
				<label class="field__option"><input type="radio" name="boardOrientation" value="<?php echo User::PREF_BOARD_ORIENTATION_RANDOM; ?>" onclick="boardOrientationPreference(this.value);" <?php echo $boardOrientationRandomChecked; ?>> Random</label>
				<label class="field__option"><input type="radio" name="boardOrientation" value="<?php echo User::PREF_BOARD_ORIENTATION_ORIGINAL; ?>" onclick="boardOrientationPreference(this.value);" <?php echo $boardOrientationOriginalChecked; ?>> Original</label>
			</div>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Progress</div>
		<div class="stack">
			<?php if ($canResetOldTsumegoStatuses): ?>
				<a class="btn" href="#" onclick="delUts(); return false;" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
			<?php else: ?>
				<a class="btn btn--disabled" href="#" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
				<span class="hint">Available after <?php echo Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED; ?>% completion</span>
			<?php endif; ?>
			<span class="hint">Progress on problems you have not touched for a year.</span>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Danger zone</div>
		<div class="stack">
			<a class="btn btn--danger" href="/users/delete_account">Delete account</a>
			<?php if (Auth::isAdmin()): ?>
				<a class="btn btn--danger" href="/users/demote_admin">Remove admin status</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>

// The change buttons only reveal the form they belong to, so the page stays a list
// of what is set now until the player asks to edit one of them.
function editing(buttonID, formID)
{
	const button = document.getElementById(buttonID);
	const form = document.getElementById(formID);
	if (!button || !form)
		return;

	button.addEventListener('click', function() {
		const opened = form.style.display !== 'none';
		form.style.display = opened ? 'none' : 'block';
		button.textContent = opened ? 'change' : 'cancel';
	});
}

editing('change-email', 'email-form');
editing('change-password', 'password-form');

function settingsTheme(mode)
{
	// darkAndLight() switches and stores the choice, so it is only called when the
	// player picked the other theme.
	if ((mode === 'dark') === !light)
		return;
	darkAndLight();
}

function settingsSound(value)
{
	setSoundCookie(value);

	updateSoundValue(value === 'on');

	const icon = document.getElementById('soundButtonImage');
	if (icon)
		icon.src = value === 'on' ? '/img/sound-icon1.png' : '/img/sound-icon2.png';
}

function avatarPreference(avatar)
{
	fetch('/users/avatarPreference', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'avatar=' + encodeURIComponent(avatar)
	}).then(function(response) {
		// The avatar is drawn by the server, so the page is reloaded to show the new one
		if (response.ok)
			location.reload();
	});
}

function playerColorPreference(color)
{
	fetch('/users/playerColorPreference', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'color=' + encodeURIComponent(color)
	});
}

function boardOrientationPreference(orientation)
{
	fetch('/users/boardOrientationPreference', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'orientation=' + encodeURIComponent(orientation)
	});
}

function emailOnNewMessagePreference(enabled)
{
	fetch('/users/emailOnNewMessagePreference', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'enabled=' + encodeURIComponent(enabled)
	});
}

function dateFormatPreference(format)
{
	fetch('/users/dateFormatPreference', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'format=' + encodeURIComponent(format)
	}).then(function(response) {
		// The dates are written when the page loads, so it is reloaded to show them in the new format
		if (response.ok)
			location.reload();
	});
}

function delUts()
{
	var confirmed = confirm("Are you sure that you want to delete your progress on <?php echo $tsumegoStatusToRestCount; ?> problems?");
	if (!confirmed)
		return;

	var form = document.createElement('form');
	form.method = 'POST';
	form.action = '/users/deleteOldTsumegoStatuses';
	document.body.appendChild(form);
	form.submit();
}
</script>
