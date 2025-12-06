<?php
class AccountWidget
{
	// to avoid flickering, I need to setup the original values also directly in the html
	// as the javascript update is too late and it would "flicker" when the page is begin loaded
	static public function render()
	{
		if(!Auth::isLoggedIn())
			return;
		if (Auth::isInTimeMode())
		{

		}
		elseif (Util::getCookie('showInAccountWidget') == 'rating')
		{
			$textBarInMenu = "Level Bar";
			$barClassname = 'xp-bar-fill-c2';
			$accountBarClassname = 'account-bar-rating';
			$rank = Rating::getRankFromRating(Auth::getUser()['rating']);
			$rankStart = Rating::getRankMinimalRating($rank);
			$nextRank = Rating::getRankMinimalRating($rank + 1);
			$rankSize = $nextRank - $rankStart;
			$barRatio = (Auth::getUser()['rating'] - $rankStart) / $rankSize;
			$barText = Rating::getReadableRankFromRating(Auth::getUser()['rating']);
		}
		else
		{
			$textBarInMenu = "Rating Bar";
			$barClassname = 'xp-bar-fill-c1';
			$accountBarClassname = 'account-bar-level';
			$barRatio = Auth::getUser()['xp'] / Level::getXPForNext(Auth::getUser()['level']);
			$barText = 'Level ' . Auth::getUser()['level'];
		}

		echo '<div id="account-bar-wrapper" onmouseover="accountWidget.hover();" onmouseout="accountWidget.noHover();">
				  <div id="account-bar">
						<div id="account-bar-user" class="account-bar-user-class">
							<a href="/users/view/'.Auth::getUserID().'">
								'.Auth::getUser()['name'].'
							</a>
						</div>
						<div id="xp-bar">
							  <div id="xp-bar-fill" class="'.$barClassname.'" style="width: ' . $barRatio * 100 .'%">
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
							<div id="account-bar-xp" class="' . $accountBarClassname . '">' . $barText . '</div>
						</div>
					</div>
				</div>
			<div id="heroProfile" onmouseover="accountWidget.hover();" onmouseout="accountWidget.noHover();">
				<li><a href="/users/view/'.Auth::getUserID().'">Profile</a></li>
			</div>';

		echo '<div id="heroBar" onmouseover="accountWidget.hover();" onmouseout="accountWidget.noHover();">
				<li><a id="textBarInMenu" onclick="accountWidget.switchBarInMenu()">'.$textBarInMenu.'</a></li>
			</div>
			<div id="heroAchievements" onmouseover="accountWidget.hover();" onmouseout="accountWidget.noHover();">
				<li><a href="/achievements">Achievements</a></li>
			</div>
			<div id="heroLogout" onmouseover="accountWidget.hover();" onmouseout="accountWidget.noHover();">
				<li><a href="/users/logout">Sign Out</a></li>
			</div>';
		if (!Auth::isInTimeMode())
			echo '<div id="modeSelector" class="modeSelector'.$modeSelector.'"></div>';
	}

	static public function renderJS()
	{
		echo "var accountWidget =";
		if (Auth::isLoggedIn())
			echo " new AccountWidget(
				{
					rating: " . Auth::getUser()['rating'] .",
					xp: " . Auth::getUser()['xp'] . ",
					level: " . Auth::getUser()['level'] . ",
					show: '" . (Util::getCookie('showInAccountWidget') == 'rating' ? 'rating' : 'level') . "'});";
		else
			echo "null;";
	}
}
