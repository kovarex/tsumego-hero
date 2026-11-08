<?php

use App\Model\Table\SetTable;
use App\Model\Table\UserTable;
use App\Utility\AccountWidget;
use App\Utility\AchievementChecker;
use App\Utility\Auth;
use App\Utility\CookieFlash;
use App\Utility\HeaderCounts;
use App\Utility\TimeMode;
use App\Utility\TsumegoUtil;
use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var string $lightDark
 * @var string $highscoreLink
 * @var TimeMode $timeMode
 * @var array|null $achievementUpdates
 * @var string $boardsBitmask
 * @var string|null $canonicalPath Path a page with more than one address wants to be known by
 * @var bool $robotsNoIndex The page is reachable but must stay out of search results
 * @var array|null $og
 */

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $lightDark === 'dark' ? 'dark' : 'light'; ?>" data-date-format="<?php echo Auth::getDateFormat(); ?>">
<head>
<?php
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
if (\Cake\Core\Configure::read('debug'))
	echo $this->element('javascript_error_tracking');
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>
<?php echo $_title ?? 'Tsumego Hero'; ?>
</title>
<meta name="description" content="Interactive tsumego database. Solve go problems, get stronger, level up, have fun.">
<meta name="keywords" content="tsumego, problems, puzzles, baduk, weiqi, tesuji, life and death, solve, solving, hero, go, in-seong, level" >
<meta name="Author" content="Joschka Zimdars">
<meta name="theme-color" content="#282828">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tsumego">
<link rel="apple-touch-icon" href="/pwa/icon-192.png">
<?php
$canonicalUrl = \Cake\Routing\Router::url($canonicalPath ?? '/' . trim(strtok($this->getRequest()->getRequestTarget(), '?'), '/'), true);
?>
<link rel="canonical" href="<?php echo h($canonicalUrl); ?>">
<?php if (!empty($robotsNoIndex)): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<?php
// Controllers can describe the page themselves; what they set wins, everything
// else comes from here.
$og = ($og ?? []) + [
	'title' => $_title ?? 'Tsumego',
	'description' => 'Interactive tsumego database. Solve go problems, get stronger, level up, have fun.',
	'image' => \Cake\Routing\Router::url('/img/Tsumego-Hero-Logo.png', true),
	'type' => 'website',
	'site_name' => 'Tsumego',
	'locale' => 'en_US',
];

// The address of the page is decided here and nowhere else, so the canonical
// link and what gets shared can never point at two different addresses.
$og['url'] = $canonicalUrl;
$this->element('open_graph_meta', ['og' => $og]);
echo $this->fetch('og_meta');
?>
<?php
echo ViteManifest::css('app-theme');

echo "<link href=\"/favicon.ico\" type=\"image/x-icon\" rel=\"icon\"/><link href=\"/favicon.ico\" type=\"image/x-icon\" rel=\"shortcut icon\"/>";
echo $this->fetch('css');
echo $this->fetch('script');
?>
<?php
//Global-scope scripts, served as a plain <script>, not type="module"
echo ViteManifest::legacyScript('legacy');
?>
<script>
	window.__CHANGELOG_TS = <?php echo json_encode($changelogTimestamps ?? []); ?>;
</script>
</head>

<body>
<?php
$_page = $_page ?? '';
//Marks the menu item of the page being shown
$active = fn(string ...$pages): string => in_array($_page, $pages, true) ? 'nav__link--active' : '';
$playUrl = TsumegoUtil::playUrl();
if (Auth::isLoggedIn())
{
	$headerCounts = HeaderCounts::forUser(Auth::getUserID());
	$unreadMessageCount = $headerCounts->unreadMessages;
	//Invitations to a class and requests to join one wait for the player's answer, which
	//the Classes item counts, and so does Collections, where the item is hidden.
	$classesAwaitingAnswer = $headerCounts->classesAwaitingAnswer;
	//Challenges the player is in and hasn't played yet: the Challenges item counts them,
	//and so does Play, where the item is hidden.
	$challengesWaiting = $headerCounts->challengesWaiting;
	//Players asking to be friends: the Friends item of the account menu counts them.
	$friendRequests = $headerCounts->friendRequests;
	//Problems of the mistake training due for review: its icon in the menu bar counts them.
	$mistakeTrainingDue = $headerCounts->mistakeTrainingDue;
	$soundSetting = UserTable::soundIsOn(Auth::getUser()) ? 'on' : 'off';
	$accountBar = AccountWidget::bar($timeMode);
}
else
{
	$unreadMessageCount = 0;
	$classesAwaitingAnswer = 0;
	$challengesWaiting = 0;
	$friendRequests = 0;
	$accountBar = null;
	$mistakeTrainingDue = 0;
	$soundSetting = in_array(Util::cookie('sound'), ['on', 'off'], true) ? Util::cookie('sound') : null;
}
$classesBadge = $classesAwaitingAnswer > 0
	? '<span class="nav__new-badge nav__new-badge--visible" title="Waiting for your answer in Classes">' . $classesAwaitingAnswer . '</span>'
	: '';
$challengesBadge = $challengesWaiting > 0
	? '<span class="nav__new-badge nav__new-badge--visible" title="Challenges waiting for you to play them">' . $challengesWaiting . '</span>'
	: '';
$friendsBadge = $friendRequests > 0
	? ' <span class="messages-unread-count" title="Players asking to be your friend">' . $friendRequests . '</span>'
	: '';
?>
<div id="container" class="legacy-center">
	<div class="whitebox1">
		<div class="legacy-left">
			<a href="/">
				<img id="logo1" alt="Tsumego Hero" title="Tsumego Hero" src="/img/tsumegoHero1.png" onmouseover="this.src='/img/tsumegoHero2.png'" onmouseout="this.src='/img/tsumegoHero1.png'" height="55px">
				<?php //A phone shows the name as plain text, shorter than the logo ?>
				<span class="site-logo-text">Tsumego</span>
			</a>
		</div>
		<?php if (Auth::isLoggedIn()): ?>
		<?php //The icons of the mistake training and the challenges, the flame and the account bar share one row at the right, so they can't overlap ?>
		<div class="site-header-account">
			<?php echo $this->element('header_icons', ['mistakeTrainingDue' => $mistakeTrainingDue, 'challengesWaiting' => $challengesWaiting]); ?>
			<?php echo $this->element('daily_streak'); ?>
			<?php echo $this->element('account_bar', ['bar' => $accountBar, 'unreadMessageCount' => $unreadMessageCount]); ?>
		</div>
		<?php endif; ?>
		<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
		<label for="nav-toggle" class="nav-toggle-btn" aria-label="Menu">
			<span></span><span></span><span></span>
		</label>
		<div class="site-nav">
			<?php if (Auth::isLoggedIn()): ?>
			<?php //Account access at the top of the drawer: the name links to the profile, the actions follow ?>
			<div class="site-nav__account">
				<div class="site-nav__account-user">
					<a href="/users/view/<?php echo Auth::getUserID(); ?>"><?php echo h(Auth::getUser()['display_name']); ?></a>
				</div>
				<nav class="site-nav__account-links">
					<ul>
						<li><a href="/messages">Messages <?php echo AccountWidget::unreadMessagesBadge($unreadMessageCount); ?></a></li>
						<li><a href="/friends">Friends<?php echo $friendsBadge; ?></a></li>
						<li><a href="/users/settings">Settings</a></li>
						<li><a href="/users/logout">Sign Out</a></li>
					</ul>
				</nav>
			</div>
			<?php else: ?>
			<div class="site-nav__account site-nav__signin-drawer">
				<div class="site-nav__account-user">
					<a href="/users/login">Sign In</a>
				</div>
			</div>
			<?php endif; ?>
			<div id="newMenu">
				<nav>
					<ul>
						<li><a class="homeMenuLink <?php echo $active('home'); ?>" href="/">Home</a>
							<ul class="newMenuLi1">
								<li><a class="<?php echo $active('websitefunctions'); ?>" href="/sites/websitefunctions">Functions & Modes</a></li>
								<li><a class="<?php echo $active('gotutorial'); ?>" href="/sites/gotutorial">Go Rules</a></li>
								<li class="newMenuLi1-forum"><a href="/forums">Forums</a></li>
								<li><a class="<?php echo $active('changelog'); ?>" href="/changelog">What's new<span class="nav__new-badge"></span></a></li>
								<li><a class="<?php echo $active('about'); ?>" href="/sites/about">About</a></li>
							</ul>
						</li>
						<li><a class="<?php echo $active('set'); ?>" href="/sets">Collections<?php echo $classesBadge; ?></a>
							<?php if (Auth::isLoggedIn()): ?>
							<ul class="newMenuLi2">
								<li><a href="/sets/mine">My Sets</a></li>
								<li><a class="<?php echo $active('favs'); ?>" href="<?php echo SetTable::url('favorites'); ?>">Favorites</a></li>
								<li><a class="<?php echo $active('classes'); ?>" href="/classes">Classes<?php echo $classesBadge; ?></a></li>
								<?php if (Auth::hasPremium() || Auth::isAdmin()): ?>
								<li><a class="<?php echo $active('sandbox'); ?>" href="/sets/sandbox">Sandbox</a></li>
								<?php endif; ?>
								<?php if (Auth::isAdmin()): ?>
								<?php //Everything an admin does is linked from the admin page ?>
								<li><a class="adminLink <?php echo $active('admin'); ?>" href="/admin">Admin</a></li>
								<?php endif; ?>
							</ul>
							<?php endif; ?>
						</li>
						<li><a class="homeMenuLink nav__play-link" href="<?php echo $playUrl; ?>">Play<?php echo $challengesBadge; ?></a>
							<ul class="newMenuLi3">
								<li><a class="<?php echo $active('level mode'); ?>" href="<?php echo $playUrl; ?>?mode=1">Level</a></li>
								<?php if (Auth::isLoggedIn()): ?>
								<li><a class="<?php echo $active('rating mode'); ?>" href="/ratingMode">Rating</a></li>
								<li><a class="<?php echo $active('time mode'); ?>" href="/timeMode/overview">Time</a></li>
								<li><a class="<?php echo $active('challenges'); ?>" href="/challenges">Challenges<?php echo $challengesBadge; ?></a></li>
								<?php endif; ?>
							</ul>
						</li>
						<li><a class="<?php echo $active('highscore'); ?>" href="/users/<?php echo $highscoreLink; ?>">Highscore</a>
							<ul class="newMenuLi4">
								<li><a class="<?php echo $active('levelHighscore'); ?>" href="/users/highscore">Level Highscore</a></li>
								<li><a class="<?php echo $active('ratingHighscore'); ?>" href="/users/rating">Rating Highscore</a></li>
								<li><a class="<?php echo $active('achievementHighscore'); ?>" href="/users/achievements">Achievement Highscore</a></li>
								<li><a class="<?php echo $active('tagHighscore'); ?>" href="/users/added_tags">Tag Highscore</a></li>
								<li><a class="<?php echo $active('dailyHighscore'); ?>" href="/users/leaderboard">Daily Highscore</a></li>
								<li><a class="<?php echo $active('timeHighscore'); ?>" href="/users/time_mode">Time Mode Highscore</a></li>
							</ul>
						</li>
						<?php if (Auth::isLoggedIn()): ?>
						<li><a class="<?php echo $active('discuss'); ?>" href="/comments<?php echo Auth::isAdmin() ? '?filter=false' : ''; ?>">Discuss</a></li>
						<?php else: ?>
						<li class="discuss-disabled"><a class="discuss-disabled__link">Discuss</a></li>
						<?php endif; ?>
						<li class="menuIcons1">
							<a href="#" id="soundButton" onclick="changeSound(); return false;"><img id="soundButtonImage" src="/img/<?php echo $soundSetting === 'off' ? 'sound-icon2.png' : 'sound-icon1.png'; ?>" width="25px"></a>
						</li>
						<li class="menuIcons1">
							<div class="board-picker">
								<label for="board-picker-toggle" id="boardsInMenu" class="dropdown__trigger">
									<img id="boardsButtonImage" src="/img/boards-icon1.png" width="25px">
								</label>
								<input class="dropdown__open" type="checkbox" id="board-picker-toggle" style="display:none;">
								<label for="board-picker-toggle" class="dropdown__overlay"></label>
								<div class="dropdown__menu">
									<table id="dropdowntable"></table>
									<br>
									<div id="dropdowntable2" class="legacy-center">
										<a class="btn btn--primary" href="<?php echo h(Util::requestUri()); ?>">Save</a>
										<br><br>
									</div>
								</div>
							</div>
						</li>
						<li class="menuIcons1">
							<a class="menuIcons2" id="darkButton" onclick="darkAndLight();" title="Light or dark theme" aria-label="Switch between light and dark theme"><span class="theme-icon"></span></a>
						</li>
					</ul>
				</nav>
			</div>
		</div>
		<?php if (!Auth::isLoggedIn()): ?>
		<div class="site-nav__signin"><a class="menuLi <?php echo $_page == 'login' ? 'nav__link--active ' : ''; ?>" id="signInMenu" href="/users/login">Sign In</a></div>
		<?php endif; ?>
	</div>
	<?php if (Auth::isLoggedIn()): ?>
		<?php echo $this->element('account_widget', ['bar' => $accountBar, 'unreadMessageCount' => $unreadMessageCount, 'friendsBadge' => $friendsBadge]); ?>
	<?php endif; ?>
	<div class="legacy-left whitebox2">
		<div id="content">
			<?php
			echo CookieFlash::render();
			echo $this->fetch('content');
			?>
		</div>
	</div>
</div>
<div id="footer" class="footerLinks">
	<div class="footer-space"></div>
	<div class="footer-space"></div>
	<div class="footer-element">Supported by Wube Software</div>
	<div class="footer-element">
		<a href="https://www.factorio.com"><img src="/img/wube-software-logo.png" title="Wube Software" alt="Wube Software"></a>
	</div>
	<div class="footer-space"></div>
	<div class="footer-element">Tsumego Hero © <?php echo date('Y'); ?></div>
	<div class="footer-element"><a href="mailto:kovarex@gmail.com">kovarex@gmail.com</a></div>
	<div class="footer-element"><a href="/sites/impressum">Legal notice</a></div>
	<div class="footer-element"><a href="/sites/about">About</a></div>
	<br><br><br>
</div>
<script>
	<?php AccountWidget::renderJS($timeMode); ?>
	<?php if (Auth::isLoggedIn() && empty(Util::cookie('disable-achievements')) && !empty($achievementUpdates)): ?>
	<?php //The popups are drawn by webroot/js/AchievementAlerts.js, the page only hands over the data ?>
	var achievementUpdates = <?php echo json_encode(array_map([AchievementChecker::class, 'toPopupData'], array_values($achievementUpdates)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
	if (typeof showAchievementPopup === "function")
		achievementUpdates.forEach(showAchievementPopup);
	<?php endif; ?>
	let light = <?php echo json_encode($lightDark !== 'dark'); ?>;
	<?php if (!Auth::isLoggedIn()): ?>
	<?php //A guest's settings live in the cookies only, kept for another year by every page ?>
	setCookie("lightDark", <?php echo json_encode($lightDark, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
	<?php if ($soundSetting !== null): ?>
	setCookie("sound", <?php echo json_encode($soundSetting); ?>);
	<?php endif; ?>
	<?php endif; ?>
	initSound(<?php echo json_encode($soundSetting); ?>);
	boardSelector = new BoardSelector(<?php echo $boardsBitmask; ?>n);
</script>

<?php echo ViteManifest::script('app'); ?>
</body>
</html>
