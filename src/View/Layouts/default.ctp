<?php

/**
 * @var View $this
 * @var string $lightDark
 * @var string $lightDark
 * @var string $highscoreLink
 * @var string $discussFilter
 * @var TimeMode $timeMode
 * @var bool $achievementUpdate
 * @var string $lastProfileLeft
 * @var string $lastProfileRight
 * @var string $nextDay
 * @var string $boardsBitmask
 */

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $lightDark === 'dark' ? 'dark' : 'light'; ?>">
<?php
App::uses('Level', 'Utility');
App::uses('CookieFlash', 'Utility');
App::uses('ViteManifest', 'Utility');
App::uses('AchievementChecker', 'Utility');
require_once __DIR__ . '/../../Utility/AccountWidget.php';
if (Configure::read('debug')) { ?>
<script>
	(function () {
		// global arrays
		window.__jsErrors = [];
		window.__consoleErrors = [];

		// capture uncaught errors (including early load errors)
		window.addEventListener('error', function (e) {
			window.__jsErrors.push({
				raw: JSON.stringify({
					message: e.message,
					source: e.filename,
					line: e.lineno,
					column: e.colno,
					type: e.type,
					target: e.target && e.target.tagName ? e.target.tagName : null,
					outerHTML: e.target && e.target.outerHTML ? e.target.outerHTML.slice(0,200) : null
				}),
					message: e.message,
					source: e.filename,
					line: e.lineno,
					column: e.colno,
				stack: e.error ? e.error.stack : null,
				time: Date.now()
			});
		}, true);

		// capture unhandled promise rejections
		window.addEventListener('unhandledrejection', function (ev) {
			var reason = ev.reason || {};
			window.__jsErrors.push({
				message: reason.message || String(reason),
				source: null,
				line: null,
				column: null,
				stack: reason.stack || null,
				time: Date.now()
			});
		}, true);

		// capture console.error
		(function () {
			var oldError = console.error;
			console.error = function () {
				try {
					window.__consoleErrors.push({
						args: Array.prototype.slice.call(arguments),
						time: Date.now()
					});
				} catch (e) {}
				oldError.apply(console, arguments);
			};
		})();
		})();
</script>
<?php } ?>
<?php
$cakeDescription = __d('cake_dev', 'CakePHP: the rapid development php framework');
$cakeVersion = __d('cake_dev', 'CakePHP %s', Configure::version());
?>
<?php
echo $this->Html->charset();
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
// Open Graph meta tags. Controllers may set a richer $og array
// (see src/View/Elements/open_graph_meta.ctp); otherwise a generic default is used.
if (empty($og))
{
	$og = [
		'title' => $_title ?? 'Tsumego',
		'description' => 'Interactive tsumego database. Solve go problems, get stronger, level up, have fun.',
		'image' => Router::url('/img/Tsumego-Hero-Logo.png', true),
		'url' => Router::url(null, true),
		'type' => 'website',
		'site_name' => 'Tsumego',
		'locale' => 'en_US',
	];
}
$this->element('open_graph_meta', ['og' => $og]);
echo $this->fetch('og_meta');
?>
<?php
echo ViteManifest::css('app-theme');

echo $this->Html->meta('icon');
echo $this->fetch('css');
echo $this->fetch('script');
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<?php
// Legacy app.js bundle (global-scope scripts — served as plain <script>, not type="module")
echo ViteManifest::legacyScript('legacy');
?>
</head>

<body>
<div id="container" align="center">
	<div width="100%" class="whitebox1">
		<div align="left">
			<a href="/">
			<?php
				$logo = 'tsumegoHero1';
				$logoH = 'tsumegoHero2';
?>
				<img id="logo1" alt="Tsumego Hero" title="Tsumego Hero" src="/img/tsumegoHero1.png" onmouseover="typeof logoHover==='function'&&logoHover(this)" onmouseout="typeof logoNoHover==='function'&&logoNoHover(this)" height="55px">
			</a>
		</div>
		<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
		<label for="nav-toggle" class="nav-toggle-btn" aria-label="Menu">
			<span></span><span></span><span></span>
		</label>
		<div class="site-nav">
			<?php
			// Account access at the top of the drawer (logged-in). The username
			// links to the profile; only Sign Out is listed as a separate action.
			if (Auth::isLoggedIn()):
			?>
			<div class="site-nav__account">
				<div class="site-nav__account-user">
					<a href="/users/view/<?php echo Auth::getUserID(); ?>"><?php echo h(Auth::getUser()['name']); ?></a>
				</div>
				<nav class="site-nav__account-links">
					<ul>
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
				<?php
			$lv = (int)($_COOKIE['lastVisit'] ?? 15352);

			if(Auth::isLoggedIn()){
				if(Auth::hasPremium()) $sand = 'onmouseover="sandboxHover()" onmouseout="sandboxNoHover()"';
				else $sand = '';
				if(Auth::hasPremium()) $leaderboard = 'onmouseover="leaderboardHover()" onmouseout="leaderboardNoHover()"';
				else $leaderboard = '';
			}else{
				$sand = '';
				$leaderboard = '';
			}
			$homeA = '';
			$collectionsA = '';
			$playA = '';
			$highscoreA = '';
			$discussA = '';
			$sandboxA = '';
			$leaderboardA = '';
			$refreshLinkToStart = '';
			$refreshLinkToSets = '';
			$refreshLinkToHighscore = '';
			$refreshLinkToLeaderboard = '';
			$refreshLinkToSandbox = '';
			$refreshLinkToFavs = '';
			$refreshLinkToDiscuss = '';
			$refreshLinkToLeaderboardBackup = '';
			$refreshLinkToSandboxBackup = '';
			$refreshLinkToDiscussBackup = '';
			$levelHighscoreA = '';
			$ratingHighscoreA = '';
			$timeHighscoreA = '';
			$achievementHighscoreA = '';
			$dailyHighscoreA = '';
			$tagHighscoreA = '';
			$levelModeA = '';
			$ratingModeA = '';
			$timeModeA = '';
			$websitefunctionsA = '';
			$gotutorialA = '';
			$aboutA = '';

			$_page = $_page ?? '';
			if($_page == 'home') $homeA = 'nav__link--active';
			else if($_page == 'set') $collectionsA = 'nav__link--active';
			else if($_page=='play' || $_page=='level mode' || $_page=='rating mode' || $_page=='time mode'){
				$refreshLinkToStart = 'id="refreshLinkToStart"';
				$refreshLinkToSets = 'id="refreshLinkToSets"';
				$refreshLinkToHighscore = 'id="refreshLinkToHighscore"';
				$refreshLinkToLeaderboard = 'id="refreshLinkToLeaderboard"';
				$refreshLinkToSandbox = 'id="refreshLinkToSandbox"';
				$refreshLinkToDiscuss = 'id="refreshLinkToDiscuss"';
				if($_page == 'level mode') $levelModeA = 'nav__link--active';
				else if($_page == 'rating mode') $ratingModeA = 'nav__link--active';
				else if($_page == 'time mode') $timeModeA = 'nav__link--active';
				} else if ($_page == 'highscore') $highscoreA = 'nav__link--active';
			else if($_page == 'discuss') $discussA = 'nav__link--active';
			else if($_page == 'sandbox') $sandboxA = 'nav__link--active';
			else if($_page == 'leaderboard') $leaderboardA = 'nav__link--active';
			else if($_page == 'websitefunctions') $websitefunctionsA = 'nav__link--active';
			else if($_page == 'gotutorial') $gotutorialA = 'nav__link--active';
			else if($_page == 'about') $aboutA = 'nav__link--active';
			else if($_page == 'levelHighscore') $levelHighscoreA = 'nav__link--active';
			else if($_page == 'ratingHighscore') $ratingHighscoreA = 'nav__link--active';
			else if($_page == 'achievementHighscore') $achievementHighscoreA = 'nav__link--active';
			else if($_page == 'timeHighscore') $timeHighscoreA = 'nav__link--active';
			else if($_page == 'dailyHighscore') $dailyHighscoreA = 'nav__link--active';
			else if($_page == 'tagHighscore') $tagHighscoreA = 'nav__link--active';
			else if($_page == 'favs') $refreshLinkToFavs = 'nav__link--active';

			if(Auth::isLoggedIn()){
				if(!Auth::isAdmin())
					$discussFilter = '';
				else
					$discussFilter = '?filter=false';
				$refreshLinkToSandboxBackup = '<a id="refreshLinkToSandbox"></a>';
				if(Auth::hasPremium()){
			}else{
					$refreshLinkToLeaderboardBackup = '<a id="refreshLinkToLeaderboard"></a>';
			}
			}else{
				$refreshLinkToDiscussBackup = '<a id="refreshLinkToDiscuss"></a>';
				}

			?>
			<div id="newMenu">
				<nav>
					<ul>
						<?php echo '<li><a class="homeMenuLink '.$homeA.'" href="/" '.$refreshLinkToStart.'>Home</a>';
						echo '<ul class="newMenuLi1">';
						echo '<li><a class="'.$websitefunctionsA.'" href="/sites/websitefunctions">Functions & Modes</a></li>';
						echo '<li><a class="'.$gotutorialA.'" href="/sites/gotutorial">Go Rules</a></li>';
						echo '<li class="newMenuLi1-forum"><a id="forumLink" href="/forums">Forums</a></li>';
						echo '<li><a class="'.$aboutA.'" href="/sites/about">About</a></li>';
						echo '</ul>';
						echo '</li>';
						echo '<li><a '.$refreshLinkToSets.' class="'.$collectionsA.'" href="/sets">Collections</a>';
						if(Auth::isLoggedIn()){
							echo '<ul class="newMenuLi2">';
							echo '<li><a href="/sets/mine">My Sets</a></li>';
							echo '<li><a class="'.$refreshLinkToFavs.'" href="/sets/view/favorites">Favorites</a></li>';
							if(Auth::hasPremium() || Auth::isAdmin())
								echo '<li><a '.$refreshLinkToSandbox.' class="'.$sandboxA.'" href="/sets/sandbox">Sandbox</a></li>';
							if(Auth::isAdmin()){
								echo '<li><a class="adminLink" href="/users/adminstats">Activities</a></li>';
									echo '<li><a class="adminLink" href="/tsumego-issues">Issues</a></li>';
								echo '<li class="additional-adminLink2"><a id="adminLink-more" class="adminLink adminLink3"><i>more</i></a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/users/uploads">Uploads</a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/tsumegos/mergeForm">Merge Duplicates</a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/sets/duplicatesearch">Duplicate Search Results</a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/schedule">Publish Schedule</a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/app/webroot/editor">Editor</a></li>';
								echo '<li class="additional-adminLink"><a class="adminLink" href="/users/userstats">User Activities</a></li>';
							}
							echo '</ul>';
						}
						$sessionLastVisit = (int)($_COOKIE['lastVisit'] ?? 15352);
						echo '</li>';
						echo '<li><a class="homeMenuLink '.$playA.'" href="/tsumegos/play/'.$lv.'">Play</a>';
						echo '<ul class="newMenuLi3">';
						echo '<li><a class="'.$levelModeA.'" href="/tsumegos/play/'.$sessionLastVisit.'?mode=1">Level</a></li>';
						if(Auth::isLoggedIn()){
							echo '<li><a class="'.$ratingModeA.'" href="/ratingMode">Rating</a></li>';
							echo '<li><a class="'.$timeModeA.'" href="/timeMode/overview">Time</a></li>';
						}
								echo '</ul>';
						echo '<li><a '.$refreshLinkToHighscore.' class="'.$highscoreA.'" href="/users/'.$highscoreLink.'">Highscore</a>';
						echo '<ul class="newMenuLi4">';
						echo '<li><a id="tutorialLink" class="'.$levelHighscoreA.'" href="/users/highscore">Level Highscore</a></li>';
						echo '<li><a id="tutorialLink" class="'.$ratingHighscoreA.'" href="/users/rating">Rating Highscore</a></li>';
						echo '<li><a id="tutorialLink" class="'.$achievementHighscoreA.'" href="/users/achievements">Achievement Highscore</a></li>';
						echo '<li><a id="tutorialLink" class="'.$tagHighscoreA.'" href="/users/added_tags">Tag Highscore</a></li>';
						echo '<li><a id="tutorialLink" class="'.$dailyHighscoreA.'" href="/users/leaderboard">Daily Highscore</a></li>';
						echo '<li><a id="tutorialLink" class="'.$timeHighscoreA.'" href="/users/time_mode">Time Mode Highscore</a></li>';
						echo '</ul>';
						if(Auth::isLoggedIn())
							echo '<li><a  '.$refreshLinkToDiscuss.'  class="'.$discussA.'" href="/comments'.$discussFilter.'">Discuss</a></li>';
						else
							echo '<li class="discuss-disabled"><a style="color:var(--surface-muted-text);">Discuss</a></li>';
						if(Auth::isLoggedIn())
							if(Auth::getUser()['sound'] == 'off')
								$soundButtonImageValue = 'sound-icon2.png';
							else if(Auth::getUser()['sound'] == 'on')
								$soundButtonImageValue = 'sound-icon1.png';
						else
								$soundButtonImageValue = 'sound-icon1.png';
						else
								$soundButtonImageValue = 'sound-icon1.png';

						echo '<li class="menuIcons1">
						<a href="#" id="soundButton" onclick="changeSound(); return false;"><img id="soundButtonImage" src="/img/'.$soundButtonImageValue.'" width="25px"></a>
					</li>';
						?>
						<li class="menuIcons1">
								<div class="board-picker" id="check3">
								<label for="dropdown-1" id="boardsInMenu" class="dropdown__trigger">
									<img id="boardsButtonImage" src="/img/boards-icon1.png" width="25px">
								</label>
								<input class="dropdown__open" type="checkbox" id="dropdown-1" style="display:none;" onchange="check1()">
								<label for="dropdown-1" class="dropdown__overlay"></label>
								<div class="dropdown__menu" id="dropdown-inner-propagation">
									<table id="dropdowntable" border="0"></table>
									<br>
									<div id="dropdowntable2" align="center">
										<a class="btn btn--primary" href="<?php echo h($_SERVER['REQUEST_URI']); ?>">Save</a>
										<br><br>
									</div>
									</div>
									</div>
						</li>
						<li class="menuIcons1">
							<a class="menuIcons2" id="darkButton" onclick="darkAndLight();"><img id="darkButtonImage"></a>
						</li>
					</ul>
				</nav>
			</div>
		</div>
		<?php
		$currentPage = '';
		if($_page == 'login')
			$currentPage = 'nav__link--active ';
		if(!Auth::isLoggedIn())
			echo '<div class="site-nav__signin"><a class="menuLi '.$currentPage.'" id="signInMenu" href="/users/login">Sign In</a></div>';
		?>
		</div>
			<?php AccountWidget::render($timeMode); ?>
	<div width="100%" align="left" class="whitebox2">
		<?php
		$setHeight = '';
		if(isset($set)){
			if($set['Set']['id']==60) $setHeight = 'style="height:1340px;"';
	}
		echo $refreshLinkToLeaderboardBackup.$refreshLinkToSandboxBackup.$refreshLinkToDiscussBackup;
		echo '<div id="content" '.$setHeight.'>';
		echo CookieFlash::render();
		echo $this->fetch('content');
		?>
	</div>
	</div>
	</div>
<div id="footer" class="footerLinks">
	<div class="footer-space"></div>
	<div class="footer-space"></div>
		<div class="footer-element">
		Supported by Wube Software
		</div>
		<div class="footer-element">
		<a href="https://www.factorio.com">
			<img src="/img/wube-software-logo.png" title="Wube Software" alt="Wube Software">
			</a>
		</div>
	<div class="footer-space"></div>
		<div class="footer-element">
			Tsumego Hero © <?php echo date('Y'); ?>
	</div>
		<div class="footer-element">
		<a href="mailto:kovarex@gmail.com">kovarex@gmail.com</a>
	</div>
		<div class="footer-element">
		<a href="/sites/impressum">Legal notice</a>
	</div>
		<div class="footer-element">
		<a href="/sites/about">About</a>
	</div>
	<br><br><br>
	</div>
<?php
// Achievement popups are rendered client-side by the shared renderer in
// webroot/js/AchievementAlerts.js; here we only hand it the pure data.
?>
<script type="text/javascript">
	<?php AccountWidget::renderJS($timeMode); ?>
	<?php
	if (Auth::isLoggedIn() && !$_COOKIE['disable-achievements'] && !empty($achievementUpdates))
	{
		$popupData = array_map(['AchievementChecker', 'toPopupData'], array_values($achievementUpdates));
		echo 'var achievementUpdates = ' . json_encode($popupData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';';
		echo 'achievementUpdates.forEach(showAchievementPopup);';
	}
	?>
	let light = <?php echo Util::boolString($lightDark !== 'dark'); ?>;
	function updateSoundValue(value)
	{
		if (typeof besogo !== 'undefined')
		{
			if(typeof value === 'undefined' || value === null)
				value = false;
			besogo.editor.setSoundEnabled(value);
		}
		soundsEnabled = value;
	}

	setCookie("lightDark", <?php echo json_encode($lightDark, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
	setCookie("lastProfileLeft", <?php echo json_encode($lastProfileLeft, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
	setCookie("lastProfileRight", <?php echo json_encode($lastProfileRight, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>);
	var soundsEnabled = true;
	var notMode3 = true;

	<?php if(Auth::isLoggedIn()){ ?>
	var soundValue = 0;
	<?php echo 'soundValue = "'.Auth::getUser()['sound'].'";';
	}else{
	?>
	soundValue = getCookie("sound");
	<?php } ?>
	updateSoundValue(soundValue == 'on');

	$(document).ready(function(){
		if(soundValue=="off")
		{
			document.getElementById("soundButtonImage").src="/img/sound-icon2.png";
			setCookie("sound", "off");
			updateSoundValue(false);
		}
		if(soundValue=="on")
		{
			document.getElementById("soundButtonImage").src="/img/sound-icon1.png";
			setCookie("sound", "on");
			updateSoundValue(true);
		}

		$("#adminLink-more").click(function(){
			$(".additional-adminLink").show();
			$(".additional-adminLink2").hide();
		});

		<?php
		if(Auth::isLoggedIn()){
		echo 'var end = new Date("'.$nextDay.' 00:00 AM");';
	?>
		var _second = 1000;
		var _minute = _second * 60;
		var _hour = _minute * 60;
		var _day = _hour * 24;
		var timer;
		var now = new Date();
		var distance = end - now;
		if (distance < 0)
		{
			clearInterval(timer);
			return;
		}
		var hours = Math.floor((distance % _day) / _hour);
		var minutes = Math.floor((distance % _hour) / _minute);
		var seconds = Math.floor((distance % _minute) / _second);
		if(hours<10) hours="0"+hours;
		if(minutes<10) minutes="0"+minutes;
		if(seconds<10) seconds="0"+seconds;
		if (document.getElementById("homeCountdown"))
		{
			document.getElementById("homeCountdown").innerHTML = hours + ":";
			document.getElementById("homeCountdown").innerHTML += minutes + ":";
			document.getElementById("homeCountdown").innerHTML += seconds;
		}
		timer = setInterval(showRemaining, 1000);

		function showRemaining()
		{
			var now = new Date();
			var distance = end - now;
			if (distance < 0)
			{
				clearInterval(timer);
				return;
			}
			var hours = Math.floor((distance % _day) / _hour);
			var minutes = Math.floor((distance % _hour) / _minute);
			var seconds = Math.floor((distance % _minute) / _second);
			if(hours<10) hours="0"+hours;
			if(minutes<10) minutes="0"+minutes;
			if(seconds<10) seconds="0"+seconds;
			if(document.getElementById("homeCountdown"))
			{
				document.getElementById("homeCountdown").innerHTML = hours + ":";
				document.getElementById("homeCountdown").innerHTML += minutes + ":";
				document.getElementById("homeCountdown").innerHTML += seconds;
			}
		}
		<?php } ?>
	});

	function updateCookie(c1,c2)
	{
		document.cookie = c1+c2;
	}

	function logoHover(img)
	{
		img.src = '/img/<?php echo $logoH ?>.png';
	}

	function logoNoHover(img)
	{
		img.src = "/img/<?php echo $logo ?>.png";
	}

	function boardsHover(){
		document.getElementById("boardsInMenu").style.color = "var(--color-green)";
		document.getElementById("boardsInMenu").style.backgroundColor = "var(--color-gray)";
	}

	function boardsNoHover(){
		document.getElementById("boardsInMenu").style.color = "var(--color-purple-bright)";
		document.getElementById("boardsInMenu").style.backgroundColor = "transparent";
	}

	function check1(){
		if(document.getElementById("dropdown-1").checked == true){
			document.getElementById("dropdowntable").style.display = "inline-block";
			document.getElementById("dropdowntable2").style.display = "inline-block";
			$(".dropdown__menu").css("opacity", "1");
			$(".dropdown__menu").css("display", "inline-block");
	}
		if(document.getElementById("dropdown-1").checked == false){
			document.getElementById("dropdowntable").style.display = "none";
			document.getElementById("dropdowntable2").style.display = "none";
			$(".dropdown__menu").css("opacity", "0");
			$(".dropdown__menu").css("display", "none");
	}
	}
	$("#check3").click(function(e){
		if(document.getElementById("dropdown-1").checked == true){
			document.getElementById("dropdown-1").checked = false;
		}else{
			document.getElementById("dropdown-1").checked = true;
	}
		check1();
		e.stopPropagation();
	});

	$("#dropdown-inner-propagation").click(function(e){
		e.stopPropagation();
	});

	function changeSound()
	{
		if(getCookie("sound")=="off")
		{
			document.getElementById("soundButtonImage").src="/img/sound-icon1.png";
			document.cookie = "sound=on;path=/";
			document.cookie = "sound=on;path=/sets/view";
			document.cookie = "sound=on;path=/tsumegos/play";
			document.cookie = "sound=on;path=/users";
			document.cookie = "sound=on;path=/users/view";
			updateSoundValue(true);
		}
		else if(getCookie("sound")=="on")
		{
			document.getElementById("soundButtonImage").src="/img/sound-icon2.png";
			document.cookie = "sound=off;path=/";
			document.cookie = "sound=off;path=/sets/view";
			document.cookie = "sound=off;path=/tsumegos/play";
			document.cookie = "sound=off;path=/users";
			document.cookie = "sound=off;path=/users/view";
			updateSoundValue(false);
		}
		else
		{
			document.getElementById("soundButtonImage").src="/img/sound-icon2.png";
			document.cookie = "sound=off;path=/";
			document.cookie = "sound=off;path=/sets/view";
			document.cookie = "sound=off;path=/tsumegos/play";
			document.cookie = "sound=off;path=/users";
			document.cookie = "sound=off;path=/users/view";
			updateSoundValue(false);
		}
	}

	function getCookie(cname){
		var name = cname + "=";
		var decodedCookie = decodeURIComponent(document.cookie);
		var ca = decodedCookie.split(';');
		for(var i = 0; i<ca.length; i++){
			var c = ca[i];
			while (c.charAt(0) == ' '){
				c = c.substring(1);
	}
			if (c.indexOf(name) == 0){
				return c.substring(name.length, c.length);
	}
	}
		return "";
	}

	function sandboxHover(){
		if(document.getElementById("sandboxLink")) document.getElementById("sandboxLink").style.display = "inline-block";
		if(document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.color = "var(--color-green)";
		if(document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.backgroundColor = "var(--color-gray)";
	}

	function sandboxNoHover(){
		if(document.getElementById("sandboxLink")) document.getElementById("sandboxLink").style.display = "none";
		if(document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.backgroundColor = "rgba(0,0,0,0)";
		if(document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.color = "var(--color-purple-bright)";
	}

	function leaderboardHover(){
		if(document.getElementById("leaderboardLink")) document.getElementById("leaderboardLink").style.display = "inline-block";
		if(document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.color = "var(--color-green)";
		if(document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.backgroundColor = "var(--color-gray)";
	}

	function leaderboardNoHover(){
		if(document.getElementById("leaderboardLink")) document.getElementById("leaderboardLink").style.display = "none";
		if(document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.backgroundColor = "rgba(0,0,0,0)";
		if(document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.color = "var(--color-purple-bright)";
	}

	function deleteAllCookies()
	{
		const cookies = document.cookie.split(";");

		for (let i = 0; i < cookies.length; i++)
		{
			const cookie = cookies[i];
			const eqPos = cookie.indexOf("=");
			const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
			document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
		}
	}

	boardSelector = new BoardSelector(<?php echo $boardsBitmask . 'n';?>);

	// Localize <time datetime="..."> elements.
	//
	// Convention:
	// - Timestamps (full ISO 8601, e.g. 2026-08-15T12:00:00+00:00) are instants:
	//   render them in the user's local timezone, where the day may cross over.
	// - Calendar dates (date-only YYYY-MM-DD) are timezone-free: anchor them at
	//   UTC so the day never changes across timezones.
	document.querySelectorAll('time[datetime]').forEach(function(el) {
		var raw = el.getAttribute('datetime');
		var dateOnly = raw.indexOf('T') === -1;
		var d = new Date(dateOnly ? raw + 'T00:00:00Z' : raw);
		if (isNaN(d)) return;
		var fmt = el.getAttribute('data-format') || 'datetime';
		if (fmt === 'date') el.textContent = d.toLocaleDateString(undefined, dateOnly ? { timeZone: 'UTC' } : undefined);
		else if (fmt === 'month-day') el.textContent = d.toLocaleDateString(undefined, dateOnly ? { timeZone: 'UTC', month: 'short', day: 'numeric' } : { month: 'short', day: 'numeric' });
		else if (fmt === 'time') el.textContent = d.toLocaleTimeString([], { timeStyle: 'short' });
		else el.textContent = d.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
	});
</script>

<?php
// React app bundle
echo ViteManifest::script('app');
?>
</body>
</html>
