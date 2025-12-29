<?php
require_once __DIR__ . "/../../Utility/ValueGraphRenderer.php";
require_once __DIR__ . "/../../Utility/TimeGraphRenderer.php";
?>

<div class="homeCenter2">
	<div class="user-header-container">
		<div class="user-header1">
		<p class="title6">Profile</p>
		</div>
	<div class="user-header2">
		<a href="/tags/user/<?php echo $user['User']['id']; ?>" id="navigate-to-contributions" class="new-button-time">contributions</a>
	</div>
</div>
<div class="userInfoContainerRow1">
	<div class="userStatsGreen">
		<table class="userTopTable1" id="name-and-email-table">
		<tr>
			<td><?php echo $user['User']['name']; ?></td>
			<td><?php User::renderPremium($user['User']); ?></td>
		</tr>
		<?php
		if (Auth::getUserID() == $user['User']['id'])
		{
			echo '<tr>';
			echo '<td>'.$user['User']['email'].'</td>';
			echo '<td><a id="show" style="color:#74d14c;">change</a></td>';
			echo '</tr>';
			echo '<tr>';
			echo '<td colspan=2>';
			echo '<div id="msg2">';
			echo $this->Form->create('User');
			echo $this->Form->input('email', array('label' => '', 'type' => 'text', 'placeholder' => 'E-Mail'));
			echo '<div class="submit"><input style="margin:0px;" value="Submit" type="submit"></div>';
			echo '</div>';
			echo '</td></tr>';
		}
		?>
		</table>
	</div>
	<div class="userStatsGreen">
	<table class="userTopTable1" border="0" width="100%">
	<tr>
	<td>
	<div align="center">
		Your rank:<br>
		<?php
			if (RatingBounds::fromRanks('15k', '9d')->containsRating($user['User']['rating']))
				echo '<img id="profileRankImage" src="/img/' . Rating::getReadableRankFromRating($user['User']['rating']) . 'Rank.png" width="76px">';
			else
				echo Rating::getReadableRankFromRating($user['User']['rating']);
		?>
	</div>
	</td>
	</tr>
	</table>
	</div>
	<div class="userStatsGreen">
	<table class="userTopTable1" border="0">
	<tr>
	<td>
		Progress bar preference:<br><br>
	<?php
		$levelBarDisplayChecked1 = '';
		$levelBarDisplayChecked2 = '';
		if ($levelBar == 1)
			$levelBarDisplayChecked1 = 'checked="checked"';
		else
			$levelBarDisplayChecked2 = 'checked="checked"';
	?>
	<input type="radio" id="levelBarDisplay1" name="levelBarDisplay" value="1" onclick="levelBarChange(1);" <?php echo $levelBarDisplayChecked1; ?>> <b id="levelBarDisplay1text">Show level</b><br>
	<input type="radio" id="levelBarDisplay2" name="levelBarDisplay" value="2" onclick="levelBarChange(2);" <?php echo $levelBarDisplayChecked2; ?>> <b id="levelBarDisplay2text">Show rating</b><br>
	</td>
	</tr>
	</table>
	</div>
	<div class="userStatsGreen">
	<table class="userTopTable1" border="0">
	<tr>
	<td>
		<?php
		echo '<font style="font-weight:800;color:#74d14c" >' . round(Util::getPercent($user['User']['solved'], $tsumegoCount), 1) . '%. completed</font><br>';
		if (Auth::getUserID() == $user['User']['id'])
		{
			if ($canResetOldTsumegoStatuses)
				echo '<br><br><a class="new-button" href="#" onclick="delUts(); return false;" id="reset-statuses-button">Reset (' . $tsumegoStatusToRestCount . ')</a><br><br>';
			else
			{
				echo '<br><br><a class="new-button-inactive" href="#" id="reset-statuses-button">Reset (' . $tsumegoStatusToRestCount . ')</a><br><br>';
				echo 'If you have completed at least '.Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED.'%, you can reset progress older than 1 year.<br>';
			}
		}
		if ($deletedTsumegoStatusCount > 0)
			echo '<font style="font-weight:800;color:#74d14c" >The progress of '.$deletedTsumegoStatusCount.' problems has been deleted.</font><br>';
		?>
	</td>
	</tr>
	</table>
	</div>
</div>

<div class="userInfoContainerRow2">
	<div class="userStatsPurple">
		<table class="userTopTable1" id="level-info-table">
			<tr>
				<td>Level:</td>
				<td><?php echo $user['User']['level']; ?></td>
			</tr>
			<tr>
				<td>Level up:</td>
				<td><?php echo $user['User']['xp'].'/'.Level::getXPForNext($user['User']['level']); ?></td>
			</tr>
			<tr>
				<td>XP earned:</td>
				<td><?php echo Level::getOverallXPGained($user['User']).' XP'; ?></td>
			</tr>
			<tr>
				<td>Health:</td>
				<td><?php echo Util::getHealthBasedOnLevel($user['User']['level']).' HP'; ?></td>
			</tr>
			<tr>
				<td>Hero powers:</td>
				<td><?php echo User::getHeroPowersCount($user['User']); ?></td>
			</tr>
		</table>
	</div>

	<?php $highestRating = User::getHighestRating($user['User']); ?>
	<div class="userStatsPurple">
		<table class="userTopTable1" id="rank-info-table">
			<tr>
				<td>Rank:</td>
				<td><?php echo Rating::getReadableRankFromRating($user['User']['rating']); ?></td>
			</tr>
			<tr>
				<td>Rating:</td>
				<td><?php echo round($user['User']['rating']); ?></td>
			</tr>
			<tr>
				<td>Highest rank:</td>
				<td><?php echo Rating::getReadableRankFromRating($highestRating); ?></td>
			</tr>
			<tr>
				<td>Highest rating:</td>
				<td><?php echo $highestRating; ?></td>
			</tr>
		</table>
	</div>

	<div class="userStatsPurple">
		<table class="userTopTable1" id="time-mode-info-table">
			<?php
			foreach ($timeModeRanks as $timeModeRank)
			{
				echo '<tr><td>' . $timeModeRank['category_name'] . ' mode rank:</td><td>' . ($timeModeRank['best_solved_rank_name'] ?: 'N/A') . '</td></tr>';
				echo '<tr><td>' . $timeModeRank['category_name'] . ' mode runs:</td><td>' . $timeModeRank['session_count'] . '</td></tr>';
			}?>
		</table>
	</div>

	<div class="userStatsPurple">
		<table class="userTopTable1" id="final-info-table">
			<tr>
				<td>Overall solved:</td>
				<td><?php echo $user['User']['solved'] . ' of ' . $tsumegoCount; ?></td>
			</tr>
			<tr>
				<td>Overall %:</td>
				<td><?php echo Util::getPercentButAvoid100UntilComplete($user['User']['solved'], $tsumegoCount) . '%'; ?></td>
			</tr>
			<tr>
				<td>Achievements:</td>
				<td><?php echo $aNum.'/'.count($aCount); ?></td>
			</tr>
		</table>
	</div>
</div>
<?php
	$size = count($dailyResults);
	if ($size < 10)
		$height = '400';
	else if($size < 30)
		$height = '600';
	else if ($size < 50)
		$height = '900';
	else
		$height = '1200';
?>
<div class="userBottom1">
	<table class="profileTable" width="100%" border="0">
	<tr>
	<td width="50%">
		<div align="center">
			<a id="userShowLevelButtonLeft" class="new-button-time" onclick="activateSelection('level', 'Left');">Level</a>
			<a id="userShowRatingButtonLeft" class="new-button-time" onclick="activateSelection('rating', 'Left');">Rating</a>
			<a id="userShowTimeButtonLeft" class="new-button-time" onclick="activateSelection('time', 'Left');">Time</a>
			<a id="userShowAchievementsButtonLeft" class="new-button-time" onclick="activateSelection('achievement', 'Left');">Achievements</a>
		</div>
	</td>
	<td width="50%">
		<div align="center">
			<a id="userShowLevelButtonRight" class="new-button-time" onclick="activateSelection('level', 'Right');">Level</a>
			<a id="userShowRatingButtonRight" class="new-button-time" onclick="activateSelection('rating', 'Right');">Rating</a>
			<a id="userShowTimeButtonRight" class="new-button-time" onclick="activateSelection('time', 'Right');">Time</a>
			<a id="userShowAchievementsButtonRight" class="new-button-time" onclick="activateSelection('achievement', 'Right');">Achievements</a>
		</div>
	</td>
	</tr>
	</table>
	<br>
	<table class="profileTable" width="100%" border="0">
		<tr>
			<?php
function showStatistics($side, $as, $user)
{
	echo '
		<td width="50%">
			<div id="userShowLevel' . $side . '">
				<div id="chartContainer">
					<div id="chart-level-' . $side . '"></div>
				</div>
			</div>
			<div id="userShowRating' . $side . '">
				<div id="chartContainer">
					<div id="chart-rating-' . $side . '"></div>
				</div>
				<div align="center">
					<a href="/users/solveHistory/' .$user['User']['id'] . '">Show solve history</a>
				</div>
			</div>
			<div id="userShowTime' . $side . '">
				<div id="chartContainer">
					<div id="chart-time-' . $side . '"></div>
				</div>
			</div>
			<div id="userShowAchievements' . $side . '">
				<table width="95%" border="0">
					<tr>
						<td class="h1profile"><h1 class="h1">Achievements</h1></td>
						<td style="text-align:right;"><b class="profileTable2"><a href="/achievements">View Achievements</a></b></td>
					</tr>
				</table>';
	for($i=0; $i<count($as); $i++)
	{
		if (strlen($as[$i]['AchievementStatus']['a_title']) > 30)
			$adjust = 'style="font-weight:normal;font-size:17px;"';
		else $adjust = '';
	?>
		<a href="/achievements/view/<?php echo $as[$i]['AchievementStatus']['a_id']; ?>">
		<div align="center" class="achievementSmall <?php echo $as[$i]['AchievementStatus']['a_color']; ?>">
			<div class="acTitle2">
				<b <?php echo $adjust; ?>><?php echo $as[$i]['AchievementStatus']['a_title']; ?></b>
			</div>
			<div class="acImg">
				<img src="/img/<?php echo $as[$i]['AchievementStatus']['a_image']; ?>.png" title="<?php echo $as[$i]['AchievementStatus']['a_description']; ?>">
				<div class="acImgXp">
				<?php echo $as[$i]['AchievementStatus']['a_xp']; ?> XP
				</div>
			</div>
			<div class="acDate2">
				<?php
				$date = date_create($as[$i]['AchievementStatus']['created']);
				echo date_format($date,"d.m.Y H:i");
				?>
			</div>
		</div>
		</a>
		<?php } ?>
	</div>
	</td>
<?php
}
showStatistics('Left', $as, $user);
showStatistics('Right', $as, $user); ?>
	</tr></table>
	<div width="100%" align="right">
		<?php
		if (Auth::getUserID() == $user['User']['id'])
		{
			if($user['User']['dbstorage'] != 1111)
				echo '<div><a style="color:gray;" href="/users/delete_account">Request account deletion</a></div><br>';
			else
			{
				echo '<p style="color:#d63a49;">You have requested account deletion.&nbsp;';
				echo '<a class="new-button-default" href="/users/view/'.$user['User']['id'].'?undo='.($user['User']['id']*1111).'">Undo</a></p>';
			}
			if(Auth::isAdmin())
				echo '<div><a style="color:gray;" href="/users/demote_admin">Remove admin status</a></div><br>';
		}
		?>
	</div>
</div>

<script>
activateSelection(getCookie('lastProfileLeft'), 'Left');
activateSelection(getCookie('lastProfileRight'), 'Right');

$("#msg2").hide();
$("#show").click(function(){
	$("#msg2").show();
});

function updateButtonActivity(id, side, active)
{
	$("#" + id + 'Button' + side).addClass("new-button-time-" + (!active ? 'inactive' : ''));
	$("#" + id + 'Button' + side).removeClass("new-button-time-" + (active ? 'inactive' : ''));
	if (active)
		$("#" + id + side).fadeIn(250);
	else
		$("#" + id + side).hide();
}

function activateSelection(selection, side)
{
	if (selection != 'level' &&
		selection != 'rating' &&
		selection != 'time' &&
		selection != 'achievement')
		if (side == 'Left')
			selection = 'level';
		else
			selection = 'rating';
	setCookie('lastProfile' + side, selection);
	updateButtonActivity('userShowLevel', side, selection == 'level');
	updateButtonActivity('userShowRating', side, selection == 'rating');
	updateButtonActivity('userShowTime', side, selection == 'time');
	updateButtonActivity('userShowAchievements', side, selection == 'achievement');
}

function delUts(){
	var dNum = "<?php echo $tsumegoStatusToRestCount; ?>";
	var confirmed = confirm("Are you sure that you want to delete your progress on "+dNum+" problems?");
	if (confirmed)
		window.location.href = '/users/deleteOldTsumegoStatuses/<?php echo Auth::getUserID(); ?>';
}
</script>
<script>
  window.Promise ||
	document.write(
	  '<script src="https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js"><\/script>'
	)
  window.Promise ||
	document.write(
	  '<script src="https://cdn.jsdelivr.net/npm/eligrey-classlist-js-polyfill@1.2.20171210/classList.min.js"><\/script>'
	)
  window.Promise ||
	document.write(
	  '<script src="https://cdn.jsdelivr.net/npm/findindex_polyfill_mdn"><\/script>'
	)
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
<?php
	foreach (['Left', 'Right'] as $side)
	{
		 ValueGraphRenderer::render(
			'Problems in level mode',
			'chart-level-' . $side,
			[
				['name' => 'Solves', 'color' => '#74d14c'],
				['name' => 'Fails', 'color' => '#d63a49']
			],
			$dailyResults,
			'day',
			true /* reverseOrder*/);
		TimeGraphRenderer::render('Overall rating', 'chart-rating-' . $side, $dailyResults, 'Rating');
		ValueGraphRenderer::render(
			'Time mode runs',
			'chart-time-' . $side,
			[
				['name' => 'Passes', 'color' => '#c8723d'],
				['name' => 'Fails', 'color' => '#888888']
			],
			$timeGraph,
			'category');
	}
	?>
</script>
<script>
</script>
<style>
.new-button-time-inactive
{
	cursor:pointer;
}

.userTopTable1 td
{
	vertical-align:top;padding:7px;
	text-align:left;
	width:50%;
}
.user-header-container{
	width:100%;
	height:50px;
}
.user-header1{
	width:50%;
	float:left;
}
.user-header2{
	width:50%;
	float:left;
	margin-top: 14px;
}
</style>
