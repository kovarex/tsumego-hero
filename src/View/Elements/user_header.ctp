<?php

use App\Utility\Auth;

/**
 * The header over a player's pages: whose page it is and which of their pages you
 * are on. Every tab shows the same header, so switching tabs only changes what is
 * below it.
 *
 * Usage: $this->element('user_header', ['user' => $userRow, 'activeTab' => 'profile'])
 *
 * Tabs: profile, solveHistory, contributions, achievements, and settings while the
 * player looks at their own pages.
 *
 * @var View $this
 * @var array $user Row with the id, display_name, external_id, picture, email and pref_avatar
 * @var string $activeTab
 */

$userID = (int) $user['id'];

$tabs = [
	'profile' => ['url' => '/users/view/' . $userID, 'label' => 'Profile'],
	'solveHistory' => ['url' => '/users/solveHistory/' . $userID, 'label' => 'Solve History'],
	'contributions' => ['url' => '/tags/user/' . $userID, 'label' => 'Contributions'],
	'achievements' => ['url' => '/achievements/user/' . $userID, 'label' => 'Achievements'],
];

// Settings are only about the player looking at the page.
if (Auth::getUserID() === $userID)
	$tabs['settings'] = ['url' => '/users/settings', 'label' => 'Settings'];
?>
<div class="profile-header">
	<p class="profile-username"><?php
		echo User::avatarImage(User::getAvatarUrl($user, User::AVATAR_PIXELS_PROFILE), 'user-avatar--lg', false, User::boardUrl($user));
		echo h((string) $user['display_name']);
		echo ' ';
		User::renderPremium($user);
	?></p>
	<div class="subnav">
<?php foreach ($tabs as $key => $tab): ?>
		<a href="<?php echo $tab['url']; ?>" class="subnav__link<?php echo $key === $activeTab ? ' subnav__link--active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php endforeach; ?>
	</div>
</div>
