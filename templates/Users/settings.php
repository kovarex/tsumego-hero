<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\UploadedAvatar;
use App\Utility\ViteManifest;

/**
 * Everything a player can change about their account and about how the site
 * behaves. It sits next to the other profile tabs, so it is the same page for the
 * same player, only not public.
 *
 * @var \App\View\AppView $this
 * @var array $user
 * @var int $levelBar
 * @var string $lightDark
 * @var int $tsumegoStatusToRestCount
 * @var bool $canResetOldTsumegoStatuses
 * @var string|null $unconfirmedEmail
 */
?>

<div class="legacy-center">
	<?php echo $this->element('user_header', ['user' => $user, 'activeTab' => 'settings']); ?>
</div>

<div class="cluster">
	<div class="card card--green">
		<div class="card__title">Account</div>
		<table class="stat" id="name-and-email-table">
			<tr>
				<td><strong>Email:</strong> <?php echo h($user['email']); ?></td>
				<td>
					<?php if (!UserTable::signsInOnlyWithGoogle($user)): ?>
						<button type="button" id="change-email" class="link" data-opens="email-form">change</button>
					<?php endif; ?>
				</td>
			</tr>
			<?php if (UserTable::signsInOnlyWithGoogle($user)): ?>
			<tr>
				<td><strong>Sign-in:</strong> Google</td>
				<td><a href="/users/switchToPassword" id="switch-to-password-link">switch to a username and password</a></td>
			</tr>
			<?php endif; ?>
			<?php if ($unconfirmedEmail): ?>
			<tr>
				<td colspan="2">
					<span class="hint" id="unconfirmed-email">Waiting for the confirmation of <?php echo h($unconfirmedEmail); ?></span>
					<form method="post" action="/users/cancelEmailChange/<?php echo h($user['unconfirmed_email_cancel_token']); ?>" class="inline-form">
						<button type="submit" class="link" id="cancel-email-change">cancel</button>
					</form>
				</td>
			</tr>
			<?php endif; ?>
			<?php if (!UserTable::signsInOnlyWithGoogle($user)): ?>
			<tr>
				<td colspan="2">
					<div id="email-form" style="display:none;margin-top:3px;">
						<?php
						echo '<form action="/users/emailPreference" id="UserEmailPreferenceForm" method="post" accept-charset="utf-8"><div hidden><input type="hidden" name="_method" value="POST"/></div>';
						echo '<div class="input"><input type="email" name="email" placeholder="New e-mail" aria-label="New e-mail" maxlength="' . UserTable::EMAIL_MAX_LENGTH . '" class="settings-account__field" required="required"></div>';
						echo '<div class="input"><input type="password" name="password" placeholder="Password" aria-label="Password" autocomplete="current-password" class="settings-account__field" required="required"></div>';
						echo '<div class="hint">The address changes once you open the link sent to it.</div>';
						echo '<div class="submit"><input class="btn btn--primary" value="Submit" type="submit"></div>';
						echo '</form>';
						?>
					</div>
				</td>
			</tr>
			<tr>
				<td><strong>Password:</strong> ********</td>
				<td><button type="button" id="change-password" class="link" data-opens="password-form">change</button></td>
			</tr>
			<tr>
				<td colspan="2">
					<div id="password-form" style="display:none;margin-top:3px;">
						<?php
						echo '<form action="/users/passwordPreference" id="UserPasswordPreferenceForm" method="post" accept-charset="utf-8"><div hidden><input type="hidden" name="_method" value="POST"/></div>';
						echo '<div class="input"><input type="password" name="current" placeholder="Current password" aria-label="Current password" autocomplete="current-password" class="settings-account__field" required="required"></div>';
						echo '<div class="input"><input type="password" name="new1" placeholder="New password" aria-label="New password" autocomplete="new-password" class="settings-account__field" required="required"></div>';
						echo '<div class="input"><input type="password" name="new2" placeholder="New password again" aria-label="New password again" autocomplete="new-password" class="settings-account__field" required="required"></div>';
						echo '<div class="hint">At least ' . Constants::$MINIMUM_PASSWORD_LENGTH . ' characters.</div>';
						echo '<div class="submit"><input class="btn btn--primary" value="Submit" type="submit"></div>';
						echo '</form>';
						?>
					</div>
				</td>
			</tr>
			<?php if (!empty($user['name'])): ?>
			<tr>
				<td><strong>Username:</strong> <?php echo h($user['name']); ?></td>
				<td></td>
			</tr>
			<?php endif; ?>
			<?php endif; ?>
			<tr>
				<td><strong>Avatar:</strong></td>
				<td>
					<?php
					$chosenAvatar = UserTable::avatarChoice($user);
					foreach (UserTable::avatarChoices($user) as $avatarValue => $avatarLabel)
					{
						$avatarChecked = $chosenAvatar === $avatarValue ? 'checked="checked"' : '';
						echo '<label class="field__option"><input type="radio" name="avatar" value="' . $avatarValue . '" data-save="/users/avatarPreference" data-field="avatar" data-reload ' . $avatarChecked . '> ' . h($avatarLabel) . '</label>';
					}
					if ($chosenAvatar === UserTable::AVATAR_GRAVATAR)
						echo '<div class="hint">Change it at <a href="https://gravatar.com" target="_blank" rel="noopener">gravatar.com</a></div>';
					?>
					<form method="post" action="/users/avatarUpload" enctype="multipart/form-data" id="avatar-upload">
						<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Picture to upload" required="required">
						<button type="submit" class="btn btn--primary">Upload</button>
						<div class="hint">Your own picture: JPEG, PNG, WebP or GIF up to <?php echo UploadedAvatar::maxSize(); ?>. The middle square of it is shown.</div>
					</form>
					<?php if (!empty($user['uploaded_avatar_hash'])): ?>
						<form method="post" action="/users/avatarRemove" class="inline-form">
							<button type="submit" class="link" id="avatar-remove">remove my picture</button>
						</form>
					<?php endif; ?>
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
				<label class="field__option"><input type="radio" name="emailOnNewMessage" value="1" data-save="/users/emailOnNewMessagePreference" data-field="enabled" <?php echo $emailOnNewMessage ? 'checked="checked"' : ''; ?>> Yes</label>
				<label class="field__option"><input type="radio" name="emailOnNewMessage" value="0" data-save="/users/emailOnNewMessagePreference" data-field="enabled" <?php echo $emailOnNewMessage ? '' : 'checked="checked"'; ?>> No</label>
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
				<label class="field__option"><input type="radio" id="levelBarDisplay1" name="levelBarDisplay" value="1" data-appearance="levelBar" <?php echo $levelBarDisplay === UserTable::LEVEL_BAR_LEVEL ? 'checked="checked"' : ''; ?>> Level</label>
				<label class="field__option"><input type="radio" id="levelBarDisplay2" name="levelBarDisplay" value="2" data-appearance="levelBar" <?php echo $levelBarDisplay === UserTable::LEVEL_BAR_RATING ? 'checked="checked"' : ''; ?>> Rating</label>
			</div>
			<div class="field">
				<span class="field__label">Theme</span>
				<label class="field__option"><input type="radio" name="theme" value="light" data-appearance="theme" <?php echo $lightDark === 'dark' ? '' : 'checked="checked"'; ?>> Light</label>
				<label class="field__option"><input type="radio" name="theme" value="dark" data-appearance="theme" <?php echo $lightDark === 'dark' ? 'checked="checked"' : ''; ?>> Dark</label>
			</div>
			<div class="field">
				<span class="field__label">Sound</span>
				<?php $soundIsOn = UserTable::soundIsOn($user); ?>
				<label class="field__option"><input type="radio" name="sound" value="on" data-appearance="sound" <?php echo $soundIsOn ? 'checked="checked"' : ''; ?>> On</label>
				<label class="field__option"><input type="radio" name="sound" value="off" data-appearance="sound" <?php echo $soundIsOn ? '' : 'checked="checked"'; ?>> Off</label>
			</div>
			<div class="field">
				<span class="field__label">Date format</span>
				<?php
				$dateFormat = Auth::getDateFormat();
				foreach ([
					UserTable::DATE_FORMAT_ISO => '2026-02-28 14:05',
					UserTable::DATE_FORMAT_DAY_MONTH_YEAR => '28. 2. 2026 14:05',
					UserTable::DATE_FORMAT_MONTH_DAY_YEAR => '02/28/2026 2:05 PM',
					UserTable::DATE_FORMAT_BROWSER => 'Browser language'] as $dateFormatValue => $dateFormatLabel)
				{
					$dateFormatChecked = $dateFormat === $dateFormatValue ? 'checked="checked"' : '';
					echo '<label class="field__option"><input type="radio" name="dateFormat" value="' . $dateFormatValue . '" data-save="/users/dateFormatPreference" data-field="format" data-reload ' . $dateFormatChecked . '> ' . $dateFormatLabel . '</label>';
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
				$playerColor = Auth::getPlayerColorSetting();
				$playerColorRandomChecked = $playerColor === UserTable::PLAYER_COLOR_SETTING_RANDOM ? 'checked="checked"' : '';
				$playerColorOriginalChecked = $playerColor === UserTable::PLAYER_COLOR_SETTING_ORIGINAL ? 'checked="checked"' : '';
				?>
				<label class="field__option"><input type="radio" name="playerColor" value="<?php echo UserTable::PLAYER_COLOR_SETTING_RANDOM; ?>" data-save="/users/playerColorPreference" data-field="color" <?php echo $playerColorRandomChecked; ?>> Random</label>
				<label class="field__option"><input type="radio" name="playerColor" value="<?php echo UserTable::PLAYER_COLOR_SETTING_ORIGINAL; ?>" data-save="/users/playerColorPreference" data-field="color" <?php echo $playerColorOriginalChecked; ?>> Original</label>
			</div>
			<div class="field">
				<span class="field__label">Board orientation</span>
				<?php
				$boardOrientation = Auth::getBoardOrientationSetting();
				$boardOrientationRandomChecked = $boardOrientation === UserTable::BOARD_ORIENTATION_SETTING_RANDOM ? 'checked="checked"' : '';
				$boardOrientationOriginalChecked = $boardOrientation === UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL ? 'checked="checked"' : '';
				?>
				<label class="field__option"><input type="radio" name="boardOrientation" value="<?php echo UserTable::BOARD_ORIENTATION_SETTING_RANDOM; ?>" data-save="/users/boardOrientationPreference" data-field="orientation" <?php echo $boardOrientationRandomChecked; ?>> Random</label>
				<label class="field__option"><input type="radio" name="boardOrientation" value="<?php echo UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL; ?>" data-save="/users/boardOrientationPreference" data-field="orientation" <?php echo $boardOrientationOriginalChecked; ?>> Original</label>
			</div>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Progress</div>
		<div class="stack">
			<?php if ($canResetOldTsumegoStatuses): ?>
				<a class="btn btn--danger" href="#" data-reset-count="<?php echo $tsumegoStatusToRestCount; ?>" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
			<?php else: ?>
				<a class="btn btn--danger btn--disabled" href="#" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
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

<?php echo ViteManifest::legacyScript('settings'); ?>
