<?php

use App\Utility\AccountWidget;
use App\Utility\Auth;

/**
 * The menu of the bar of the signed in player (templates/element/account_bar.php), which shows when
 * the bar is hovered. It is drawn as it is when the page starts, so it does not flicker before
 * webroot/js/AccountWidget.js takes it over.
 * @var \App\View\AppView $this
 * @var array{fillClass: string, ratio: float, text: string, textClass: string, switchText: string, modeSelectorClass: string} $bar See AccountWidget::bar()
 * @var int $unreadMessageCount
 * @var string $friendsBadge The count of the players asking to be friends, empty while there are none
 */

$userID = Auth::getUserID();
?>
<div id="heroProfile" class="account-menu__item account-menu__item--first"><li><a href="/users/view/<?php echo $userID; ?>">Profile</a></li></div>
<div id="heroBar" class="account-menu__item account-menu__item--row1"><li><a id="textBarInMenu"><?php echo $bar['switchText']; ?></a></li></div>
<div id="heroAchievements" class="account-menu__item account-menu__item--row2"><li><a href="/achievements">Achievements</a></li></div>
<div id="heroMessages" class="account-menu__item account-menu__item--row3"><li><a href="/messages">Messages <?php echo AccountWidget::unreadMessagesBadge($unreadMessageCount); ?></a></li></div>
<div id="heroFriends" class="account-menu__item account-menu__item--row4"><li><a href="/friends">Friends<?php echo $friendsBadge; ?></a></li></div>
<div id="heroSettings" class="account-menu__item account-menu__item--row5"><li><a href="/users/settings">Settings</a></li></div>
<div id="heroLogout" class="account-menu__item account-menu__item--row6 account-menu__item--last"><li><a href="/users/logout">Sign Out</a></li></div>
<div id="modeSelector" class="<?php echo $bar['modeSelectorClass']; ?>"></div>
