<!DOCTYPE html>
<html lang="en">
<?php
App::uses('Level', 'Utility');
if (Configure::read('debug')) { ?>
	<script>
		(function() {
			// global arrays
			window.__jsErrors = [];
			window.__consoleErrors = [];

			// capture uncaught errors (including early load errors)
			window.addEventListener('error', function(e) {
				window.__jsErrors.push({
					raw: JSON.stringify({
						message: e.message,
						source: e.filename,
						line: e.lineno,
						column: e.colno,
						type: e.type,
						target: e.target && e.target.tagName ? e.target.tagName : null,
						outerHTML: e.target && e.target.outerHTML ? e.target.outerHTML.slice(0, 200) : null
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
			window.addEventListener('unhandledrejection', function(ev) {
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
			(function() {
				var oldError = console.error;
				console.error = function() {
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
if ($this->Session->check('redirect') && $this->Session->read('redirect') == 'loading') {
	$this->Session->delete('redirect');
	$this->Session->write('initialLoading', 'true');
	echo '<script type="text/javascript">window.location.href = "/users/loading";</script>';
}
echo $this->Html->charset();
?>
<title>
	<?php
	if (!$this->Session->check('title')) echo 'Tsumego Hero';
	else echo $this->Session->read('title');
	?>
</title>
<meta name="description" content="Interactive tsumego database. Solve go problems, get stronger, level up, have fun.">
<meta name="keywords" content="tsumego, problems, puzzles, baduk, weiqi, tesuji, life and death, solve, solving, hero, go, in-seong, level">
<meta name="Author" content="Joschka Zimdars">
<meta property="og:title" content="Tsumego Hero">
<link rel="stylesheet" type="text/css" href="/css/default.css?v=<?php echo filemtime(WWW_ROOT . 'css/default.css'); ?>">
<?php
if ($lightDark == 'dark')
	echo '<link rel="stylesheet" type="text/css" href="/css/dark.css?v=4.3">';

echo $this->Html->meta('icon');
echo $this->fetch('meta');
echo $this->fetch('css');
echo $this->fetch('script');
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script type="text/javascript" src="/dist/jgoboard-latest.js"></script>
<script type="text/javascript" src="/js/util.js?v=7"></script>
<script src="/js/dark.js?v=<?php echo filemtime(WWW_ROOT . 'js/dark.js'); ?>"></script>
<?php
$modeSelector = 2;
$accountBarLevelToRating = 'account-bar-user';
if ($mode != 3) {
	if ($levelBar == 1) {
		if (Auth::isLoggedIn())
			$levelNum = 'Level ' . Auth::getUser()['level'];
		else
			$levelNum = 1;
		$xpBarFill = 'xp-bar-fill-c1';
		$modeSelector = 2;
		$accountBarLevelToRating = 'account-bar-user';
	} else {
		$xpBarFill = 'xp-bar-fill-c2';
		if (Auth::isLoggedIn())
			$levelNum = Rating::getReadableRankFromRating(Auth::getUser()['rating']);
		$modeSelector = 1;
		$accountBarLevelToRating = 'account-bar-user2';
	}
} else {
	$levelNum = '15k';
	$xpBarFill = 'xp-bar-fill-c3';
}
?>
</head>

<body class="<?php echo $lightDark === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
	<div id="container" align="center">
		<div width="100%" class="whitebox1">
			<div align="left">
				<a href="/">
					<?php
					$logo = 'tsumegoHero1';
					$logoH = 'tsumegoHero2';
					?>
					<img id="logo1" alt="Tsumego Hero" title="Tsumego Hero" src="/img/tsumegoHero1.png" onmouseover="logoHover(this)" onmouseout="logoNoHover(this)" height="55px">
				</a>
			</div>
			<div class="outerMenu1">
				<?php
				if ($this->Session->check('lastVisit')) $lv = $this->Session->read('lastVisit');
				else $lv = '15352';

				if (Auth::isLoggedIn()) {
					if (Auth::hasPremium()) $sand = 'onmouseover="sandboxHover()" onmouseout="sandboxNoHover()"';
					else $sand = '';
					if (Auth::hasPremium()) $leaderboard = 'onmouseover="leaderboardHover()" onmouseout="leaderboardNoHover()"';
					else $leaderboard = '';
				} else {
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
				$levelModeA = '';
				$ratingModeA = '';
				$timeModeA = '';
				$websitefunctionsA = '';
				$gotutorialA = '';
				$aboutA = '';

				if ($this->Session->read('page') == 'home') $homeA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'set') $collectionsA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'play' || $this->Session->read('page') == 'level mode' || $this->Session->read('page') == 'rating mode' || $this->Session->read('page') == 'time mode') {
					$refreshLinkToStart = 'id="refreshLinkToStart"';
					$refreshLinkToSets = 'id="refreshLinkToSets"';
					$refreshLinkToHighscore = 'id="refreshLinkToHighscore"';
					$refreshLinkToLeaderboard = 'id="refreshLinkToLeaderboard"';
					$refreshLinkToSandbox = 'id="refreshLinkToSandbox"';
					$refreshLinkToDiscuss = 'id="refreshLinkToDiscuss"';
					if ($this->Session->read('page') == 'level mode') $levelModeA = 'style="color:#74d14c;"';
					else if ($this->Session->read('page') == 'rating mode') $ratingModeA = 'style="color:#74d14c;"';
					else if ($this->Session->read('page') == 'time mode') $timeModeA = 'style="color:#74d14c;"';
				} else if ($this->Session->read('page') == 'highscore') $highscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'discuss') $discussA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'sandbox') $sandboxA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'leaderboard') $leaderboardA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'websitefunctions') $websitefunctionsA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'gotutorial') $gotutorialA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'about') $aboutA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'levelHighscore') $levelHighscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'ratingHighscore') $ratingHighscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'achievementHighscore') $achievementHighscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'timeHighscore') $timeHighscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'dailyHighscore') $dailyHighscoreA = 'style="color:#74d14c;"';
				else if ($this->Session->read('page') == 'favs') $refreshLinkToFavs = 'style="color:#74d14c;"';

				if (isset($nextMode['Tsumego']['id'])) {
					if ($nextMode['Tsumego']['id'] == null) $nextMode['Tsumego']['id'] = 15352;
				} else {
					$nextMode['Tsumego']['id'] = 15352;
				}
				if (Auth::isLoggedIn()) {
					if (!Auth::isAdmin())
						$discussFilter = '';
					else
						$discussFilter = '?filter=false';
					$refreshLinkToSandboxBackup = '<a id="refreshLinkToSandbox"></a>';
					if (Auth::hasPremium()) {
					} else {
						$refreshLinkToLeaderboardBackup = '<a id="refreshLinkToLeaderboard"></a>';
					}
				} else {
					$refreshLinkToDiscussBackup = '<a id="refreshLinkToDiscuss"></a>';
				}

				?>
				<div id="newMenu">
					<nav>
						<ul>
							<?php echo '<li><a class="homeMenuLink" href="/" ' . $refreshLinkToStart . ' ' . $homeA . '>Home</a>';
							echo '<ul class="newMenuLi1">';
							echo '<li><a id="tutorialLink" href="/sites/websitefunctions" ' . $websitefunctionsA . '>Functions & Modes</a></li>';
							echo '<li><a id="tutorialLink" href="/sites/gotutorial" ' . $gotutorialA . '>Go Rules</a></li>';
							echo '<li><a id="forumLink" href="/forums">Forums</a></li>';
							echo '<li><a href="/users/authors" ' . $aboutA . '>About</a></li>';
							echo '</ul>';
							echo '</li>';
							echo '<li><a ' . $refreshLinkToSets . ' ' . $collectionsA . ' href="/sets">Collections</a>';
							if (Auth::isLoggedIn()) {
								if (Auth::hasPremium() || Auth::isAdmin() || $hasFavs) {
									echo '<ul class="newMenuLi2">';
									if (Auth::hasPremium() || Auth::isAdmin())
										echo '<li><a ' . $refreshLinkToSandbox . ' ' . $sandboxA . ' href="/sets/sandbox">Sandbox</a></li>';
									echo '<li><a ' . $refreshLinkToFavs . ' href="/sets/view/favorites">Favorites</a></li>';
									if (Auth::isAdmin()) {
										echo '<li><a class="adminLink" href="/users/adminstats">Activities</a></li>';
										echo '<li><a class="adminLink" href="/tsumego-issues">Issues</a></li>';
										echo '<li class="additional-adminLink2"><a id="adminLink-more" class="adminLink adminLink3"><i>more</i></a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/users/uploads">Uploads</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/users/duplicates">Merge Duplicates</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/sets/duplicatesearch">Duplicate Search Results</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/users/publish">Publish Schedule</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/app/webroot/editor">Editor</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/users/userstats">User Activities</a></li>';
										echo '<li class="additional-adminLink"><a class="adminLink" href="/users/uservisits">Users Per Day</a></li>';
									}
									echo '</ul>';
								}
							}
							if ($this->Session->check('lastVisit')) $sessionLastVisit = $this->Session->read('lastVisit');
							else $sessionLastVisit = 15352;
							echo '</li>';
							echo '<li><a class="homeMenuLink" ' . $playA . ' href="/tsumegos/play/' . $lv . '">Play</a>';
							echo '<ul class="newMenuLi3">';
							echo '<li><a href="/tsumegos/play/' . $sessionLastVisit . '?mode=1" ' . $levelModeA . '>Level</a></li>';
							if (Auth::isLoggedIn()) {
								echo '<li><a href="/tsumegos/play/' . $nextMode['Tsumego']['id'] . '?mode=2" ' . $ratingModeA . '>Rating</a></li>';
								echo '<li><a href="/timeMode/overview" ' . $timeModeA . '>Time</a></li>';
							}
							echo '</ul>';
							echo '<li><a ' . $refreshLinkToHighscore . ' ' . $highscoreA . ' href="/users/' . $highscoreLink . '">Highscore</a>';
							echo '<ul class="newMenuLi4">';
							echo '<li><a id="tutorialLink" href="/users/highscore" ' . $levelHighscoreA . '>Level Highscore</a></li>';
							echo '<li><a id="tutorialLink" href="/users/rating" ' . $ratingHighscoreA . '>Rating Highscore</a></li>';
							echo '<li><a id="tutorialLink" href="/users/achievements" ' . $achievementHighscoreA . '>Achievement Highscore</a></li>';
							echo '<li><a id="tutorialLink" href="/users/added_tags" ' . $timeHighscoreA . '>Tag Highscore</a></li>';
							echo '<li><a id="tutorialLink" href="/users/leaderboard" ' . $dailyHighscoreA . '>Daily Highscore</a></li>';
							echo '</ul>';
							if (Auth::isLoggedIn())
								echo '<li><a  ' . $refreshLinkToDiscuss . '  ' . $discussA . 'href="/comments' . $discussFilter . '">Discuss</a></li>';
							else
								echo '<li><a style="color:#aaa;">Discuss</a></li>';
							if (Auth::isLoggedIn())
								if (Auth::getUser()['sound'] == 'off')
									$soundButtonImageValue = 'sound-icon2.png';
								else if (Auth::getUser()['sound'] == 'on')
									$soundButtonImageValue = 'sound-icon1.png';
								else
									$soundButtonImageValue = 'sound-icon1.png';
							else
								$soundButtonImageValue = 'sound-icon1.png';

							echo '<li class="menuIcons1">
						<a href="#" id="soundButton" onclick="changeSound(); return false;"><img id="soundButtonImage" src="/img/' . $soundButtonImageValue . '" width="25px"></a>
					</li>';
							?>
							<li class="menuIcons1">
								<div class="dropdown" id="check3">
									<label for="dropdown-1" id="boardsInMenu" class="dropdown-button">
										<img id="boardsButtonImage" src="/img/boards-icon1.png" width="25px">
									</label>
									<input class="dropdown-open" type="checkbox" id="dropdown-1" style="display:none;" onchange="check1()">
									<label for="dropdown-1" class="dropdown-overlay"></label>
									<div class="dropdown-inner" id="dropdown-inner-propagation">
										<table id="dropdowntable" border="0">
											<tr>
												<?php
												$tr = 1;
												for ($i = 1; $i <= 51; $i++) {
													if (isset($boardNames[$i])) {
														echo '
														<td width="19px" align="right" style="position:relative;top:1px;padding:4px;">
															<input type="checkbox" class="newCheck" id="newCheck' . $i . '" ' . $enabledBoards[$i] . '>
														</td>
														<td width="19px" align="center" style="position:relative;top:3px;padding:2px;">

															<div class="img-' . $boardPositions[$i][0] . 'small"></div>
														</td>
														<td width="115px" style="padding:0px;text-align:left;">
															' . $boardNames[$i] . '
														</td>
													';
													}
													if ($tr % 4 == 0 && $tr > 0) echo '</tr><tr>';
													$tr++;
												}
												?>
												<td colspan="3">
													<div class="boards-tile tiles-submit-inner-select" id="boards-unselect-all">Unselect all</div>
												</td>
											</tr>
										</table>
										<br>
										<div id="dropdowntable2" align="center">
											<a class="new-button" href="<?php echo $_SERVER['REQUEST_URI']; ?>">Save</a>
											<br><br>
										</div>
									</div>
								</div>
							</li>
							<?php
							if ($lightDark == 'dark')
								$lightDarkImage = 'dark-icon1';
							else
								$lightDarkImage = 'light-icon1x';
							echo '<li class="menuIcons1">
						<a class="menuIcons2" id="darkButton" onclick="darkAndLight();"><img id="darkButtonImage" src="/img/' . $lightDarkImage . '.png?v=3.6" width="30px"></a>
					</li>';
							?>
						</ul>
					</nav>
				</div>
			</div>
			<div class="outerMenu2">
				<li><a></a></li>
			</div>
			<div class="outerMenu3">
				<?php
				$currentPage = '';
				if ($this->Session->read('page') == 'user')
					$currentPage = 'style="color:#74d14c;" ';
				if (!Auth::isLoggedIn())
					echo '<li><a class="menuLi" id="signInMenu" ' . $currentPage . 'href="/users/login">Sign In</a></li>';
				?>
			</div>

		</div>
		<?php
		if (Auth::isLoggedIn()) {
			if ($levelBar == 1)
				$textBarInMenu = "Rating Bar";
			else
				$textBarInMenu = "Level Bar";
			echo '<div id="account-bar-wrapper" onmouseover="xpHover()" onmouseout="xpNoHover()">
					  <div id="account-bar">
							<div id="' . $accountBarLevelToRating . '" class="account-bar-user-class">
								<a href="/users/view/' . Auth::getUserID() . '">
									' . Auth::getUser()['name'] . '
								</a>
							</div>
							<div id="xp-bar">
								  <div id="xp-bar-fill" class="' . $xpBarFill . '">
										<div id="xp-increase-fx">
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
								<div id="account-bar-xp">' . $levelNum . '</div>
							</div>
					  </div>
				</div>
				<div id="heroProfile" onmouseover="xpHover()" onmouseout="xpNoHover()">
					<li><a href="/users/view/' . Auth::getUserID() . '">Profile</a></li>
				</div>
				<div id="heroBar" onmouseover="xpHover()" onmouseout="xpNoHover()">
					<li><a id="textBarInMenu" onclick="switchBarInMenu()">' . $textBarInMenu . '</a></li>
				</div>
				<div id="heroAchievements" onmouseover="xpHover()" onmouseout="xpNoHover()">
					<li><a href="/achievements">Achievements</a></li>
				</div>
				<div id="heroLogout" onmouseover="xpHover()" onmouseout="xpNoHover()">
					<li><a href="/users/logout">Sign Out</a></li>
				</div>';
			if ($mode != 3)
				echo '<div id="modeSelector" class="modeSelector' . $modeSelector . '"></div>';
		}
		?>
		<div width="100%" align="left" class="whitebox2">
			<?php
			$setHeight = '';
			if (isset($set)) {
				if ($set['Set']['id'] == 60) $setHeight = 'style="height:1340px;"';
			}
			echo $refreshLinkToLeaderboardBackup . $refreshLinkToSandboxBackup . $refreshLinkToDiscussBackup;
			echo '<div id="content" ' . $setHeight . '>';
			echo $this->Session->flash();
			echo $this->Flash->render();
			echo $this->fetch('content');
			?>
		</div>
	</div>
	</div>
	<div id="footer" class="footerLinks">
		<div class="footer-space"></div>
		<?php if (!Auth::hasPremium()) { ?>
			<div class="footer-element">
				<a href="/users/donate">
					<img id="donateH2" onmouseover="upgradeHover2()" onmouseout="upgradeNoHover2()" width="180px" src="/img/upgradeButton1.png">
				</a>
			</div>
		<?php } else { ?>
			<div class="footer-element">
				<a href="/users/donate">
					<img id="donateH2" onmouseover="donateHover2()" onmouseout="donateNoHover2()" width="180px" src="/img/donateButton1.png">
				</a>
			</div>
		<?php } ?>
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
			<a href="mailto:joschka.zimdars@googlemail.com">joschka.zimdars@googlemail.com</a>
		</div>
		<div class="footer-element">
			<a href="/sites/impressum">Legal notice</a>
		</div>
		<div class="footer-element">
			<a href="/users/authors">About</a>
		</div>
		<br><br><br>
	</div>
	<?php
	$achievementUpdate = $achievementUpdate ?? [];
	if (Auth::isLoggedIn() && !$_COOKIE['disable-achievements']) {
		$xpBonus = 0;
		$count = count($achievementUpdate);
		for ($i = 0; $i < $count; $i++) {
			echo '
			<label>
		    <input type="checkbox" class="alertCheckbox1" id="alertCheckbox' . $i . '" autocomplete="off" />
		    <div class="alertBox alertInfo ' . $achievementUpdate[$i][3] . '3" id="achievementAlerts' . $i . '">
			<div class="alertBanner" align="center">
			Achievement Completed
			<span class="alertClose">x</span>
			</div>
			<span class="alertText"><img id="hpIcon1" src="/img/' . $achievementUpdate[$i][2] . '.png">
			<b>' . $achievementUpdate[$i][0] . ' - ' . $achievementUpdate[$i][1] . '</b>&nbsp; (' . $achievementUpdate[$i][4] . ' XP)&nbsp; <a href="/achievements/view/' . $achievementUpdate[$i][5] . '">view</a>
			<br>
			<br class="clear1"/></span>
		    </div>
			</label>
			';
			$xpBonus += $achievementUpdate[$i][4];
		}
		if (Auth::getUser()['xp'] + $xpBonus >= Level::getXPForNext(Auth::getUser()['level'])) {
			$increaseValue = 100;
		} else
			$increaseValue = 50;
	}
	?>
	<script type="text/javascript">
		var lifetime = new Date();
		let boardsUnselectAll = false;
		let boardsUnselectAllCounter = 0;
		<?php
		for ($i = 1; $i <= 51; $i++)
			if ($enabledBoards[$i] == 'checked')
				echo 'boardsUnselectAllCounter++;';
		?>
		if (boardsUnselectAllCounter == 0) {
			boardsUnselectAll = true;
			$("#boards-unselect-all").html("Select all");
		}

		lifetime.setTime(lifetime.getTime() + 8 * 24 * 60 * 60 * 1000);
		lifetime = lifetime.toUTCString() + "";
		<?php
		if (isset($removeCookie)) {
			echo 'setCookie("' . $removeCookie . '", "0");';
		}
		if (Auth::isLoggedIn()) { ?>
			var barPercent1 = <?php echo Util::getPercent(Auth::getUser()['xp'], Level::getXPForNext(Auth::getUser()['level'])); ?>;
			var barPercent2 = <?php echo substr(round(Auth::getUser()['rating']), -2); ?>;
			var barLevelNum = "<?php echo 'Level ' . Auth::getUser()['level']; ?>";
			var barRatingNum = "<?php echo Rating::getReadableRankFromRating(Auth::getUser()['rating']); ?>";
			var levelToRatingHover = <?php echo $levelBar; ?>;
		<?php } ?>
		<?php
		if ($this->Session->read('page') != 'level mode' && $this->Session->read('page') != 'rating mode' && $this->Session->read('page') != 'time mode')
			echo 'setCookie("mode", 1);';

		$count = is_array($achievementUpdate) ? count($achievementUpdate) : $achievementUpdate;

		for ($i = 0; $i < $count; $i++) {
			echo '$("#achievementAlerts' . $i . '").fadeIn(600);';
			echo '
		$("#alertCheckbox' . $i . '").change(function(){
			$("#achievementAlerts' . $i . '").fadeOut(500);
		});
		';
		}
		?>
		let light = true;
		<?php
		if ($lightDark == 'dark') {
			echo 'light = false;';
			if ($this->Session->read('page') == 'home') {
				echo '$("#darkButtonImage2").attr("src","/img/dark-icon1.png");';
				echo '$("#darkButtonImage3").attr("src","/img/dark-icon1.png");';
			}
		}
		?>

		function updateSoundValue(value) {
			if (typeof besogo !== 'undefined') {
				if (typeof value === 'undefined' || value === null)
					value = false;
				besogo.editor.setSoundEnabled(value);
			}
			soundsEnabled = value;
		}
		document.cookie = "score=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "misplays=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "preId=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "sprint=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "intuition=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "rejuvenation=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "rejuvenationx=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "refinement=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "favorite=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "mode=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "skip=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "transition=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "difficulty=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "seconds=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "sequence=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "rank=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "lastMode=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "sound=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "correctNoPoints=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "ui=0;SameSite=Lax;expires=" + lifetime + ";path=/";
		document.cookie = "requestProblem=0;SameSite=Lax;expires=" + lifetime + ";path=/";

		setCookie("lightDark", "<?php echo $lightDark; ?>");
		<?php if (Auth::isLoggedIn()) { ?>
			setCookie("levelBar", "<?php echo $levelBar; ?>");
		<?php } ?>
		setCookie("lastProfileLeft", "<?php echo $lastProfileLeft; ?>");
		setCookie("lastProfileRight", "<?php echo $lastProfileRight; ?>");
		setCookie("type", "0");

		setCookie("noScore", "0");
		setCookie("noPreId", "0");

		if (getCookie("z_hash" !== "1"))
			setCookie("z_hash", "0");

		setCookie("query", "");
		setCookie("collection_size", "");
		setCookie("filtered_sets", "");
		setCookie("filtered_ranks", "");
		setCookie("filtered_tags", "");
		setCookie("revelation", "");
		setCookie("texture", "0");
		<?php
		if (isset($textureCookies))
			echo 'document.cookie = "texture="+"' . $textureCookies . '"+";SameSite=Lax;expires="+lifetime+";path=/";';
		//echo 'setCookie("texture", '.$textureCookies.');';
		?>
		var soundsEnabled = true;
		var notMode3 = true;

		<?php if (Auth::isLoggedIn()) { ?>
			var userXP = <?php echo Auth::getUser()['xp']; ?>;
			var userLevel = <?php echo Auth::getUser()['level']; ?>;
			var userNextLvl = <?php echo Level::getXPForNext(Auth::getUser()['level']); ?>;
			var userElo = <?php echo round(Auth::getUser()['rating']); ?>;
			var soundValue = 0;
			let modeSelector = <?php echo $modeSelector; ?>;
			let levelBar = <?php echo $levelBar; ?> + "";
		<?php
			echo 'soundValue = "' . Auth::getUser()['sound'] . '";';
		} else {
		?>
			let levelBar = 1;
			soundValue = getCookie("sound");
		<?php } ?>
		updateSoundValue(soundValue == 'on');

		$(document).ready(function() {
			loadBar();
			if (soundValue == "off") {
				document.getElementById("soundButtonImage").src = "/img/sound-icon2.png";
				setCookie("sound", "off");
				updateSoundValue(false);
			}
			if (soundValue == "on") {
				document.getElementById("soundButtonImage").src = "/img/sound-icon1.png";
				setCookie("sound", "on");
				updateSoundValue(true);
			}

			$("#modeSelector").click(function() {
				levelBarChange(modeSelector);
			});

			$("#adminLink-more").click(function() {
				$(".additional-adminLink").show();
				$(".additional-adminLink2").hide();
			});

			<?php
			if ($mode == 1 || $mode == 2) {
			?>
				if (levelBar == 1) {
					$(".account-bar-user-class").removeAttr("id");
					$(".account-bar-user-class").attr("id", "account-bar-user");
				} else {
					$(".account-bar-user-class").removeAttr("id");
					$(".account-bar-user-class").attr("id", "account-bar-user2");
				}
			<?php
			}
			if (Auth::isLoggedIn()) {
				echo 'var end = new Date("' . $nextDay . ' 00:00 AM");';
			?>
				var _second = 1000;
				var _minute = _second * 60;
				var _hour = _minute * 60;
				var _day = _hour * 24;
				var timer;
				var now = new Date();
				var distance = end - now;
				if (distance < 0) {
					clearInterval(timer);
					return;
				}
				var days = Math.floor(distance / _day);
				var hours = Math.floor((distance % _day) / _hour);
				var minutes = Math.floor((distance % _hour) / _minute);
				var seconds = Math.floor((distance % _minute) / _second);
				if (hours < 10) hours = "0" + hours;
				if (minutes < 10) minutes = "0" + minutes;
				if (seconds < 10) seconds = "0" + seconds;
				if (document.getElementById("homeCountdown")) {
					document.getElementById("homeCountdown").innerHTML = hours + ":";
					document.getElementById("homeCountdown").innerHTML += minutes + ":";
					document.getElementById("homeCountdown").innerHTML += seconds;
				}
				timer = setInterval(showRemaining, 1000);

				function showRemaining() {
					var now = new Date();
					var distance = end - now;
					if (distance < 0) {
						clearInterval(timer);
						return;
					}
					var days = Math.floor(distance / _day);
					var hours = Math.floor((distance % _day) / _hour);
					var minutes = Math.floor((distance % _hour) / _minute);
					var seconds = Math.floor((distance % _minute) / _second);
					if (hours < 10) hours = "0" + hours;
					if (minutes < 10) minutes = "0" + minutes;
					if (seconds < 10) seconds = "0" + seconds;
					if (document.getElementById("homeCountdown")) {
						document.getElementById("homeCountdown").innerHTML = hours + ":";
						document.getElementById("homeCountdown").innerHTML += minutes + ":";
						document.getElementById("homeCountdown").innerHTML += seconds;
					}
				}
			<?php } ?>
			<?php if ($resetCookies) { ?>
				setCookie("preId", 0);
				setCookie("score", 0);
				setCookie("seconds", 0);
			<?php } ?>
		});

		function updateCookie(c1, c2) {
			document.cookie = c1 + c2;
		}

		function logoHover(img) {
			img.src = '/img/<?php echo $logoH ?>.png';
		}

		function logoNoHover(img) {
			img.src = "/img/<?php echo $logo ?>.png";
		}

		function boardsHover() {
			document.getElementById("boardsInMenu").style.color = "#74D14C";
			document.getElementById("boardsInMenu").style.backgroundColor = "grey";
		}

		function boardsNoHover() {
			document.getElementById("boardsInMenu").style.color = "#d19fe4";
			document.getElementById("boardsInMenu").style.backgroundColor = "transparent";
		}

		function check1() {
			if (document.getElementById("dropdown-1").checked == true) {
				document.getElementById("dropdowntable").style.display = "inline-block";
				document.getElementById("dropdowntable2").style.display = "inline-block";
				$(".dropdown-inner").css("opacity", "1");
				$(".dropdown-inner").css("display", "inline-block");
			}
			if (document.getElementById("dropdown-1").checked == false) {
				document.getElementById("dropdowntable").style.display = "none";
				document.getElementById("dropdowntable2").style.display = "none";
				$(".dropdown-inner").css("opacity", "0");
				$(".dropdown-inner").css("display", "none");
			}
		}
		$("#check3").click(function(e) {
			if (document.getElementById("dropdown-1").checked == true) {
				document.getElementById("dropdown-1").checked = false;
			} else {
				document.getElementById("dropdown-1").checked = true;
			}
			check1();
			e.stopPropagation();
		});

		$("#dropdown-inner-propagation").click(function(e) {
			e.stopPropagation();
		});

		<?php for ($i = 1; $i <= 51; $i++) { ?>
			$("#newCheck<?php echo $i; ?>").click(function(e) {
				let boardSettings = [];
				let boardSettingsString = "";
				for (let i = 1; i <= 51; i++) {
					if (document.getElementById("newCheck" + i).checked)
						boardSettingsString += "2";
					else
						boardSettingsString += "1";
				}
				//setCookie("texture", boardSettingsString);
				document.cookie = "texture=" + boardSettingsString + ";SameSite=Lax;expires=" + lifetime + ";path=/";
				e.stopPropagation();
			});
		<?php } ?>

		$("#boards-unselect-all").click(function(e) {
			if (!boardsUnselectAll) {
				for (let i = 1; i <= 51; i++)
					document.getElementById("newCheck" + i).checked = false;
				//setCookie("texture", "111111111111111111111111111111111111111111111111111");
				document.cookie = "texture=111111111111111111111111111111111111111111111111111;SameSite=Lax;expires=" + lifetime + ";path=/";
				$("#boards-unselect-all").html("Select all");
			} else {
				for (let i = 1; i <= 51; i++)
					document.getElementById("newCheck" + i).checked = true;
				//setCookie("texture", "222222222222222222222222222222222222222222222222222");
				document.cookie = "texture=222222222222222222222222222222222222222222222222222;SameSite=Lax;expires=" + lifetime + ";path=/";
				$("#boards-unselect-all").html("Unselect all");
			}
			boardsUnselectAll = !boardsUnselectAll;
			e.stopPropagation();
		});

		function changeSound() {
			if (getCookie("sound") == "off") {
				document.getElementById("soundButtonImage").src = "/img/sound-icon1.png";
				document.cookie = "sound=on;path=/";
				document.cookie = "sound=on;path=/sets/view";
				document.cookie = "sound=on;path=/tsumegos/play";
				document.cookie = "sound=on;path=/users";
				document.cookie = "sound=on;path=/users/view";
				updateSoundValue(true);
			} else if (getCookie("sound") == "on") {
				document.getElementById("soundButtonImage").src = "/img/sound-icon2.png";
				document.cookie = "sound=off;path=/";
				document.cookie = "sound=off;path=/sets/view";
				document.cookie = "sound=off;path=/tsumegos/play";
				document.cookie = "sound=off;path=/users";
				document.cookie = "sound=off;path=/users/view";
				updateSoundValue(false);
			} else {
				document.getElementById("soundButtonImage").src = "/img/sound-icon2.png";
				document.cookie = "sound=off;path=/";
				document.cookie = "sound=off;path=/sets/view";
				document.cookie = "sound=off;path=/tsumegos/play";
				document.cookie = "sound=off;path=/users";
				document.cookie = "sound=off;path=/users/view";
				updateSoundValue(false);
			}
		}

		function getCookie(cname) {
			var name = cname + "=";
			var decodedCookie = decodeURIComponent(document.cookie);
			var ca = decodedCookie.split(';');
			for (var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') {
					c = c.substring(1);
				}
				if (c.indexOf(name) == 0) {
					return c.substring(name.length, c.length);
				}
			}
			return "";
		}

		function loadBar() {
			<?php if (Auth::isLoggedIn()) { ?>

				if (notMode3) {
					<?php
					$barPercent2 = substr(round(Auth::getUser()['rating']), -2);

					if ($mode != 3) { ?>

						if (levelBar == 1) {
							$("#xp-increase-fx").css("display", "inline-block");
							$("#xp-bar-fill").css("box-shadow", "-5px 0px 10px #fff inset");
							$("#xp-bar-fill").css("width", barPercent1 + "%");
							$("#xp-increase-fx").fadeOut(0);
							$("#xp-bar-fill").css({
								"-webkit-transition": "all 0.5s ease",
								"box-shadow": ""
							});
						} else {
							$("#xp-increase-fx").css("display", "inline-block");
							$("#xp-bar-fill").css("box-shadow", "-5px 0px 10px #fff inset");
							$("#xp-bar-fill").css("width", barPercent2 + "%");
							$("#xp-increase-fx").fadeOut(0);
							$("#xp-bar-fill").css({
								"-webkit-transition": "all 0.5s ease",
								"box-shadow": ""
							});
						}
					<?php } else { ?>
						<?php $barPercent = 100; ?>
						$("#xp-increase-fx").css("display", "inline-block");
						$("#xp-bar-fill").css("box-shadow", "-5px 0px 10px #fff inset");
						<?php echo '$("#xp-bar-fill").css("width","' . $barPercent . '%");'; ?>
						$("#xp-increase-fx").fadeOut(0);
						$("#xp-bar-fill").css({
							"-webkit-transition": "all 0.5s ease",
							"box-shadow": ""
						});
					<?php } ?>
				}
			<?php } ?>
		}

		function xpHover() {
			if (notMode3) {
				<?php
				if (Auth::isLoggedIn()) {
					if ($mode == 1 || $mode == 2) {
				?>
						if (levelBar == 1)
							document.getElementById("account-bar-xp").innerHTML = Math.round(userXP) + "/" + userNextLvl;
						else
							document.getElementById("account-bar-xp").innerHTML = userElo;
				<?php
					} else {
						echo 'document.getElementById("account-bar-xp").innerHTML = userXP+"/"+userNextLvl;';
					}
				} else {
					echo 'document.getElementById("account-bar-xp").innerHTML = "Level"+userXP+"/"+userNextLvl;';
				}
				?>
			}
			document.getElementById("heroProfile").style.display = "inline-block";
			document.getElementById("heroBar").style.display = "inline-block";
			document.getElementById("heroAchievements").style.display = "inline-block";
			document.getElementById("heroLogout").style.display = "inline-block";
		}

		function xpNoHover() {
			if (notMode3) {
				<?php if ($mode == 1 || $mode == 2) { ?>
					if (levelBar == 1)
						document.getElementById("account-bar-xp").innerHTML = barLevelNum;
					else {
						document.getElementById("account-bar-xp").innerHTML = barRatingNum;
					}
				<?php } ?>
			}
			document.getElementById("heroProfile").style.display = "none";
			document.getElementById("heroBar").style.display = "none";
			document.getElementById("heroAchievements").style.display = "none";
			document.getElementById("heroLogout").style.display = "none";
		}

		function sandboxHover() {
			if (document.getElementById("sandboxLink")) document.getElementById("sandboxLink").style.display = "inline-block";
			if (document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.color = "#74d14c";
			if (document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.backgroundColor = "grey";
		}

		function sandboxNoHover() {
			if (document.getElementById("sandboxLink")) document.getElementById("sandboxLink").style.display = "none";
			if (document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.backgroundColor = "rgba(0,0,0,0)";
			if (document.getElementById("collectionsInMenu")) document.getElementById("collectionsInMenu").style.color = "#d19fe4";
		}

		function leaderboardHover() {
			if (document.getElementById("leaderboardLink")) document.getElementById("leaderboardLink").style.display = "inline-block";
			if (document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.color = "#74d14c";
			if (document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.backgroundColor = "grey";
		}

		function leaderboardNoHover() {
			if (document.getElementById("leaderboardLink")) document.getElementById("leaderboardLink").style.display = "none";
			if (document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.backgroundColor = "rgba(0,0,0,0)";
			if (document.getElementById("highscoreInMenu")) document.getElementById("highscoreInMenu").style.color = "#d19fe4";
		}

		function upgradeHover2() {
			document.getElementById("donateH2").src = '/img/upgradeButton1h.png';
		}

		function upgradeNoHover2() {
			document.getElementById("donateH2").src = "/img/upgradeButton1.png";
		}

		function donateHover2() {
			document.getElementById("donateH2").src = '/img/donateButton1h.png';
		}

		function donateNoHover2() {
			document.getElementById("donateH2").src = "/img/donateButton1.png";
		}

		function runXPBar2() {
			<?php
			if ($mode == 1) {
			?>
				newXP2 = 100;
				newXP = 100;
				if (newXP2 >= 100) {
					newXP2 = 100;
				}

				$("#xp-bar-fill").css({
					"width": newXP2 + "%"
				});
				$("#xp-bar-fill").css("-webkit-transition", "all 1s ease");
				$("#xp-increase-fx").fadeIn(0);
				$("#xp-bar-fill").css({
					"-webkit-transition": "all 1s ease",
					"box-shadow": ""
				});
				setTimeout(function() {
					$("#xp-increase-fx").fadeOut(500);
					$("#xp-bar-fill").css({
						"-webkit-transition": "all 1s ease",
						"box-shadow": ""
					});
				}, 1000);
			<?php
			}
			?>
		}

		function runXPNumber2(id, start, end, duration, ulvl) {
			userXP = end;
			userLevel = ulvl;
			var range = end - start;
			var current = start;
			var increment = end > start ? 1 : -1;
			var stepTime = Math.abs(Math.floor(duration / range));
			var obj = document.getElementById(id);
			var nextlvl = 0;
			var timer = setInterval(function() {
				current += increment;
				obj.innerHTML = current + nextlvl;
				if (current == end) {
					clearInterval(timer);
				}
			}, stepTime);
		}

		function switchBarInMenu() {
			if (levelBar == 1) {
				$("#textBarInMenu").text("Level Bar");
				levelBarChange(2);
			} else {
				$("#textBarInMenu").text("Rating Bar");
				levelBarChange(1);
			}
		}

		function deleteAllCookies() {
			const cookies = document.cookie.split(";");

			for (let i = 0; i < cookies.length; i++) {
				const cookie = cookies[i];
				const eqPos = cookie.indexOf("=");
				const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
				document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
			}
		}
	</script>
	<?php
	if (!Auth::isLoggedIn())
		echo '<style>.outerMenu1{left: 224px;}</style>';
	?>
</body>

</html>

<head>