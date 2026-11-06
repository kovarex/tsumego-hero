<?php

use App\Utility\AccountWidget;
use App\Utility\Auth;

/**
 * The bar of the signed in player in the header, with its menu. It is drawn as it is when the
 * page starts, so it does not flicker before webroot/js/AccountWidget.js takes it over.
 * @var \App\View\AppView $this
 * @var array{fillClass: string, ratio: float, text: string, textClass: string, switchText: string, modeSelectorClass: string} $bar See AccountWidget::bar()
 * @var int $unreadMessageCount
 * @var string $friendsBadge The count of the players asking to be friends, empty while there are none
 */

$userID = Auth::getUserID();
?>
<div id="account-bar-wrapper">
	<div id="account-bar">
		<div id="account-bar-user" class="account-bar-user-class">
			<a href="/users/view/<?php echo $userID; ?>"><?php echo h(Auth::getUser()['display_name']); ?></a>
		</div>
		<a id="account-bar-messages" href="/messages"><?php echo AccountWidget::unreadMessagesBadge($unreadMessageCount); ?></a>
		<div id="xp-bar">
			<div id="xp-bar-fill" class="<?php echo $bar['fillClass']; ?>" style="width: <?php echo $bar['ratio'] * 100; ?>%">
				<div id="xp-increase-fx" style="display:none;">
					<div id="xp-increase-fx-flicker">
						<div class="xp-increase-glow1"></div>
						<div class="xp-increase-glow2"></div>
						<div class="xp-increase-glow3"></div>
					</div>
					<div class="xp-increase-glow2"></div>
				</div>
			</div>
		</div>
		<div id="account-bar-xp-wrapper">
			<div id="account-bar-xp" class="<?php echo $bar['textClass']; ?>"><?php echo h($bar['text']); ?></div>
		</div>
	</div>
</div>
<div id="heroProfile"><li><a href="/users/view/<?php echo $userID; ?>">Profile</a></li></div>
<div id="heroBar"><li><a id="textBarInMenu"><?php echo $bar['switchText']; ?></a></li></div>
<div id="heroAchievements"><li><a href="/achievements">Achievements</a></li></div>
<div id="heroMessages"><li><a href="/messages">Messages <?php echo AccountWidget::unreadMessagesBadge($unreadMessageCount); ?></a></li></div>
<div id="heroFriends"><li><a href="/friends">Friends<?php echo $friendsBadge; ?></a></li></div>
<div id="heroSettings"><li><a href="/users/settings">Settings</a></li></div>
<div id="heroLogout"><li><a href="/users/logout">Sign Out</a></li></div>
<div id="modeSelector" class="<?php echo $bar['modeSelectorClass']; ?>"></div>
