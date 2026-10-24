<?php

use App\Utility\Auth;

/**
 * The header over a player's pages: whose page it is and which of their pages you
 * are on. Every tab shows the same header, so switching tabs only changes what is
 * below it.
 *
 * Usage: $this->element('user_header', ['user' => $userRow, 'activeTab' => 'profile'])
 *
 * Tabs: profile, solveHistory, contributions, achievements, sets, and messages and
 * settings while the player looks at their own pages. A tab with nothing behind it
 * is left out, so nobody opens an empty page; the tab of the page you are on always
 * stays, so the navigation never hides where you are.
 *
 * On the pages of another player, a signed-in visitor gets a button to write to them.
 *
 * @var View $this
 * @var array $user Row with the id, display_name, external_id, picture, email and pref_avatar
 * @var string $activeTab
 */

$userID = (int) $user['id'];
$viewerSeesEverything = Auth::getUserID() === $userID || Auth::isAdmin();

$tabs = [
	'profile' => ['url' => '/users/view/' . $userID, 'label' => 'Profile'],
	'solveHistory' => ['url' => '/users/solveHistory/' . $userID, 'label' => 'Solve History'],
	'contributions' => [
		'url' => '/tags/user/' . $userID,
		'label' => 'Contributions',
		'filled' => User::hasContributions($userID),
	],
	'achievements' => ['url' => '/achievements/user/' . $userID, 'label' => 'Achievements'],
	'sets' => [
		'url' => '/sets/user/' . $userID,
		'label' => 'Sets',
		// The player's own collections are theirs to see; a visitor only sees what is shared.
		'filled' => Set::countVisibleTo($userID, $viewerSeesEverything) > 0,
	],
];

// Messages and settings are only about the player looking at the page.
if (Auth::getUserID() === $userID)
{
	$tabs['messages'] = ['url' => '/messages', 'label' => 'Messages'];
	$tabs['settings'] = ['url' => '/users/settings', 'label' => 'Settings'];
}
?>
<div class="profile-header">
	<p class="profile-username"><?php
		echo User::avatarImage(User::getAvatarUrl($user, User::AVATAR_PIXELS_PROFILE), 'user-avatar--lg', false, User::boardUrl($user));
		echo h((string) $user['display_name']);
		echo ' ';
		User::renderPremium($user);
	?></p>
	<?php if (Auth::isLoggedIn() && Auth::getUserID() !== $userID && !User::isDeleted($user)): ?>
	<a class="btn btn--small profile-header__message" href="/messages/<?php echo $userID; ?>">Send message</a>
	<?php endif; ?>
	<div class="subnav">
<?php foreach ($tabs as $key => $tab): ?>
		<?php if (($tab['filled'] ?? true) || $key === $activeTab): ?>
		<a href="<?php echo $tab['url']; ?>" class="subnav__link<?php echo $key === $activeTab ? ' subnav__link--active' : ''; ?>"><?php echo $tab['label']; ?></a>
		<?php endif; ?>
<?php endforeach; ?>
	</div>
</div>
