<?php

use App\Model\Table\FriendshipTable;
use App\Model\Table\SetTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;

/**
 * The header over a player's pages: whose page it is and which of their pages you
 * are on. Every tab shows the same header, so switching tabs only changes what is
 * below it.
 *
 * Usage: $this->element('user_header', ['user' => $userRow, 'activeTab' => 'profile'])
 *
 * Tabs: profile, solveHistory, contributions, achievements, sets, and messages, friends and
 * settings while the player looks at their own pages. A tab with nothing behind it
 * is left out, so nobody opens an empty page; the tab of the page you are on always
 * stays, so the navigation never hides where you are.
 *
 * On the pages of another player, a signed-in visitor gets buttons to write to them, to challenge
 * them and to ask them to be friends (or to answer their request, or to end the friendship).
 *
 * $corner: optional HTML for the top-right corner, away from the tabs (the role form of an owner).
 *
 * @var \App\View\AppView $this
 * @var array $user Row with the id, display_name, external_id, picture, email, avatar_setting_id and uploaded_avatar_hash
 * @var string $activeTab
 * @var string|null $corner
 */

$userID = (int) $user['id'];
$viewerSeesEverything = Auth::getUserID() === $userID || Auth::isAdmin();

$tabs = [
	'profile' => ['url' => '/users/view/' . $userID, 'label' => 'Profile'],
	'solveHistory' => ['url' => '/users/solveHistory/' . $userID, 'label' => 'Solve history'],
	'contributions' => [
		'url' => '/tags/user/' . $userID,
		'label' => 'Contributions',
		'filled' => UserTable::hasContributions($userID),
	],
	'achievements' => ['url' => '/achievements/user/' . $userID, 'label' => 'Achievements'],
	'sets' => [
		'url' => '/sets/user/' . $userID,
		'label' => 'Sets',
		// The player's own collections are theirs to see; a visitor only sees what is shared.
		'filled' => SetTable::countVisibleTo($userID, $viewerSeesEverything) > 0,
	],
];

// Messages and settings are only about the player looking at the page.
if (Auth::getUserID() === $userID)
{
	$tabs['messages'] = ['url' => '/messages', 'label' => 'Messages'];
	$tabs['friends'] = ['url' => '/friends', 'label' => 'Friends'];
	$tabs['settings'] = ['url' => '/users/settings', 'label' => 'Settings'];
}
?>
<div class="profile-header">
	<?php if (!empty($corner)): ?>
	<div class="profile-header__corner"><?php echo $corner; ?></div>
	<?php endif; ?>
	<p class="profile-username"><?php
		echo UserTable::avatarImage(UserTable::getAvatarUrl($user, UserTable::AVATAR_PIXELS_PROFILE), 'user-avatar--lg', false, UserTable::boardUrl($user));
		echo h((string) $user['display_name']);
		echo ' ';
		UserTable::renderPremium($user);
	?></p>
	<?php if (Auth::isLoggedIn() && Auth::getUserID() !== $userID && !UserTable::isDeleted($user)): ?>
	<a class="btn btn--small profile-header__message" href="/messages/<?php echo $userID; ?>">Send message</a>
	<a class="btn btn--small profile-header__message" href="/challenges?opponent=<?php echo $userID; ?>" id="profile-challenge">Challenge</a>
	<?php $friendship = FriendshipTable::stateBetween(Auth::getUserID(), $userID); ?>
	<form method="post" class="profile-header__friend" id="profile-friend" data-friendship="<?php echo $friendship; ?>">
		<?php if ($friendship === FriendshipTable::NONE): ?>
		<button type="submit" class="btn btn--small" formaction="/friends/add/<?php echo $userID; ?>">Add friend</button>
		<?php elseif ($friendship === FriendshipTable::ASKED_BY_OTHER): ?>
		<button type="submit" class="btn btn--small btn--primary" formaction="/friends/accept/<?php echo $userID; ?>">Accept friend request</button>
		<button type="submit" class="btn btn--small" formaction="/friends/remove/<?php echo $userID; ?>">Decline</button>
		<?php elseif ($friendship === FriendshipTable::ASKED_BY_VIEWER): ?>
		<span class="profile-header__friend-state">Friend request sent</span>
		<button type="submit" class="btn btn--small" formaction="/friends/remove/<?php echo $userID; ?>">Take back</button>
		<?php else: ?>
		<span class="profile-header__friend-state">Friends</span>
		<button type="submit" class="btn btn--small" formaction="/friends/remove/<?php echo $userID; ?>" onclick="return confirm(<?php echo h(json_encode('Remove ' . $user['display_name'] . ' from your friends?')); ?>);">Remove friend</button>
		<?php endif; ?>
	</form>
	<?php endif; ?>
	<div class="subnav">
<?php foreach ($tabs as $key => $tab): ?>
		<?php if (($tab['filled'] ?? true) || $key === $activeTab): ?>
		<a href="<?php echo $tab['url']; ?>" class="subnav__link<?php echo $key === $activeTab ? ' subnav__link--active' : ''; ?>"><?php echo $tab['label']; ?></a>
		<?php endif; ?>
<?php endforeach; ?>
	</div>
</div>
