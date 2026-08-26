<?php

/**
 * @var View $this
 * @var array $aCount
 * @var int $aNum
 * @var array $as
 * @var bool $canResetOldTsumegoStatuses
 * @var array $dailyResults
 * @var int $deletedTsumegoStatusCount
 * @var int $levelBar
 * @var string $timeGraph
 * @var array $timeModeRanks
 * @var int $tsumegoCount
 * @var string $tsumegoStatusToRestCount
 * @var array $user
 */

require_once __DIR__ . "/../../Utility/ValueGraphRenderer.php";
require_once __DIR__ . "/../../Utility/TimeGraphRenderer.php";
?>

<?php TimeGraphRenderer::renderScriptInclude(); ?>

<div class="homeCenter2">
	<div class="profile-header">
		<p class="profile-username"><?php echo h($user['User']['name']); ?> <?php User::renderPremium($user['User']); ?></p>
		<?php echo $this->element('user_subnav', ['userID' => $user['User']['id'], 'activeTab' => 'profile']); ?>
	</div>
<?php if (Auth::getUserID() == $user['User']['id']): ?>
<div class="cluster">
	<div class="card card--green">
		<div class="card__title">Email</div>
		<table class="stat" id="name-and-email-table">
			<tr>
				<td><?php echo h($user['User']['email']); ?></td>
				<td><a id="show" class="link">change</a></td>
			</tr>
			<tr>
				<td colspan="2">
					<div id="msg2">
						<?php
						echo $this->Form->create('User');
						echo $this->Form->input('email', array('label' => '', 'type' => 'text', 'placeholder' => 'E-Mail'));
						echo '<div class="submit"><input class="btn" value="Submit" type="submit"></div>';
						?>
					</div>
				</td>
			</tr>
		</table>
	</div>
	<div class="card card--green">
		<div class="card__title">Progress bar</div>
		<div class="field">
			<?php
			$levelBarDisplayChecked1 = '';
			$levelBarDisplayChecked2 = '';
			if ($levelBar == 1)
				$levelBarDisplayChecked1 = 'checked="checked"';
			else
				$levelBarDisplayChecked2 = 'checked="checked"';
			?>
			<label class="field__option"><input type="radio" id="levelBarDisplay1" name="levelBarDisplay" value="1" onclick="levelBarChange(1);" <?php echo $levelBarDisplayChecked1; ?>> Level</label>
			<label class="field__option"><input type="radio" id="levelBarDisplay2" name="levelBarDisplay" value="2" onclick="levelBarChange(2);" <?php echo $levelBarDisplayChecked2; ?>> Rating</label>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Reset progress</div>
		<div class="stack">
			<?php if ($canResetOldTsumegoStatuses): ?>
				<a class="btn" href="#" onclick="delUts(); return false;" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
			<?php else: ?>
				<a class="btn btn--disabled" href="#" id="reset-statuses-button">Reset old progress (<?php echo $tsumegoStatusToRestCount; ?>)</a>
				<span class="hint">Available after <?php echo Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED; ?>% completion</span>
			<?php endif; ?>
			<?php if ($deletedTsumegoStatusCount > 0): ?>
				<span class="hint">Progress of <?php echo $deletedTsumegoStatusCount; ?> problems has been deleted.</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="card card--green">
		<div class="card__title">Account</div>
		<div class="stack">
			<?php if ($user['User']['dbstorage'] != 1111): ?>
				<a class="btn btn--danger" href="/users/delete_account">Request account deletion</a>
			<?php else: ?>
				<span class="hint hint--danger">Account deletion requested.&nbsp;</span>
				<a class="btn" href="/users/view/<?php echo $user['User']['id']; ?>?undo=<?php echo ($user['User']['id'] * 1111); ?>">Undo</a>
			<?php endif; ?>
			<?php if (Auth::isAdmin()): ?>
				<a class="btn btn--danger" href="/users/demote_admin">Remove admin status</a>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="cluster cluster--stretch">
	<div class="card card--purple">
		<table class="stat" id="level-info-table">
			<tr>
				<td>Level:</td>
				<td><?php echo $user['User']['level']; ?></td>
			</tr>
			<tr>
				<td>Next level:</td>
				<td><?php echo $user['User']['xp'] . '/' . Level::getXPForNext($user['User']['level']) . ' XP'; ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php
					$xpForNext = Level::getXPForNext($user['User']['level']);
					$xpPercent = $xpForNext > 0 ? round($user['User']['xp'] / $xpForNext * 100) : 0;
					?>
					<div class="progress">
						<div class="progress__fill" style="width:<?php echo $xpPercent; ?>%"></div>
						<div class="progress__label"><?php echo $xpPercent; ?>%</div>
					</div>
				</td>
			</tr>
			<tr>
				<td>Health:</td>
				<td>
					<?php
					$maxHealth = Util::getHealthBasedOnLevel($user['User']['level']);
					$remainingHealth = max(0, $maxHealth - ($user['User']['damage'] ?? 0));
					echo $remainingHealth . '/' . $maxHealth . ' HP';
					?>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<?php
					$healthPercent = $maxHealth > 0 ? round($remainingHealth / $maxHealth * 100) : 0;
					$healthClass = 'progress__fill--high';
					if ($healthPercent <30)
						$healthClass = 'progress__fill--low';
					elseif ($healthPercent <60)
						$healthClass = 'progress__fill--medium';
					?>
					<div class="progress">
						<div class="progress__fill <?php echo $healthClass; ?>" style="width:<?php echo $healthPercent; ?>%"></div>
					</div>
				</td>
			</tr>
		</table>
	</div>

	<?php $highestRating = User::getHighestRating($user['User']); ?>
	<div class="card card--purple">
		<table class="stat" id="rank-info-table">
			<tr>
				<td colspan="2" class="rank"><span class="rank__label">Rank</span></td>
			</tr>
			<tr>
				<td colspan="2" class="rank"><span class="rank-icon"><?php echo Rating::getReadableRankFromRating($user['User']['rating']); ?></span></td>
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

	<div class="card card--purple">
		<table class="stat" id="time-mode-info-table">
			<?php
			foreach ($timeModeRanks as $timeModeRank)
			{
				echo '<tr><td>' . $timeModeRank['category_name'] . ' mode rank:</td><td>' . ($timeModeRank['best_solved_rank_name'] ?: 'N/A') . '</td></tr>';
				echo '<tr><td>' . $timeModeRank['category_name'] . ' mode runs:</td><td>' . $timeModeRank['session_count'] . '</td></tr>';
			}?>
		</table>
	</div>

	<div class="card card--purple">
		<table class="stat" id="final-info-table">
			<tr>
				<td>Completed:</td>
				<td><?php echo $user['User']['solved'] . ' of ' . $tsumegoCount; ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php $completedPercent = Util::getPercentButAvoid100UntilComplete($user['User']['solved'], $tsumegoCount); ?>
					<div class="progress">
						<div class="progress__fill" style="width:<?php echo $completedPercent; ?>%"></div>
						<div class="progress__label"><?php echo $completedPercent; ?>%</div>
					</div>
				</td>
			</tr>
			<tr>
				<td>Achievements:</td>
				<td><?php echo $aNum . ' of ' . count($aCount); ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php $achievementPercent = count($aCount) > 0 ? round($aNum / count($aCount) * 100) : 0; ?>
					<div class="progress">
						<div class="progress__fill" style="width:<?php echo $achievementPercent; ?>%"></div>
						<div class="progress__label"><?php echo $achievementPercent; ?>%</div>
					</div>
				</td>
			</tr>
			<?php if ($user['User']['created']): ?>
			<tr>
				<td>Member since:</td>
				<td><?php echo date('F Y', strtotime($user['User']['created'])); ?></td>
			</tr>
			<?php endif; ?>
		</table>
	</div>
</div>
<?php
$heroPowers = HeroPowers::getPowers();
$maxPowerLevel = max(array_column($heroPowers, 'level'));
$isPremium = HeroPowers::hasPremiumUnlock($user['User']);
$hasRevelationBonus = HeroPowers::hasContributionRevelationBonus($user['User']);
$unlockedCount = 0;
foreach ($heroPowers as $power)
{
	if ($user['User']['level'] >= $power['level'])
		$unlockedCount++;
}
?>
<div class="hero-powers">
	<div class="hero-powers__title">Hero Powers: <?php echo $unlockedCount . ' of ' . count($heroPowers); ?> unlocked</div>
	<div class="hero-powers__timeline">
		<div class="hero-powers__track">
			<div class="hero-powers__fill" style="width:<?php echo min(100, round($user['User']['level'] / $maxPowerLevel * 100)); ?>%"></div>
		</div>
		<?php foreach ($heroPowers as $power):
			$pos = round($power['level'] / $maxPowerLevel * 100);
			$isUnlocked = $user['User']['level'] >= $power['level'];
		?>
		<div class="hero-powers__marker<?php echo $isUnlocked ? ' hero-powers__marker--unlocked' : ''; ?>" style="left:<?php echo $pos; ?>%" title="<?php echo $power['description']; ?>">
			<div class="hero-powers__dot"></div>
			<div class="hero-powers__name"><?php echo $power['name']; ?></div>
			<div class="hero-powers__level">Lv <?php echo $power['level']; ?></div>
			<?php if (!empty($power['premium']) && $isPremium): ?>
				<div class="badge badge--active" title="Unlocked via premium">Premium</div>
			<?php endif; ?>
			<?php if (!empty($power['bonus']) && $hasRevelationBonus): ?>
				<div class="badge badge--active" title="<?php echo $power['bonus']; ?>">+1 use</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
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
if (!function_exists('showStatistics'))
{
function showStatistics($side, $as, $user, $dailyResults)
{
	echo '
		<td width="50%">
			<div id="userShowLevel' . $side . '">
				<div id="chartContainer">
					<div id="chart-level-' . $side . '"></div>
				</div>
			</div>
			<div id="userShowRating' . $side . '">
				<div id="chartContainer">';
	TimeGraphRenderer::render('Overall rating', 'chart-rating-' . $side, $dailyResults, 'Rating');
	echo '</div>
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
						<td style="text-align:right;"><b class="profileTable2"><a href="/achievements/user/' . $user['User']['id'] . '">View Achievements</a></b></td>
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
				<b <?php echo $adjust; ?>><?php echo h($as[$i]['AchievementStatus']['a_title']); ?></b>
			</div>
			<div class="acImg">
				<img src="/img/<?php echo h($as[$i]['AchievementStatus']['a_image']); ?>.png" title="<?php echo h($as[$i]['AchievementStatus']['a_description']); ?>">
				<div class="acImgXp">
				<?php echo $as[$i]['AchievementStatus']['a_xp']; ?> XP
				</div>
			</div>
			<div class="acDate2">
				<time datetime="<?php echo Util::toIso8601($as[$i]['AchievementStatus']['created']) ?>" data-format="datetime"><?php echo h($as[$i]['AchievementStatus']['created']) ?></time>
			</div>
		</div>
		</a>
		<?php } ?>
	</div>
	</td>
<?php
}
} // function_exists
showStatistics('Left', $as, $user, $dailyResults);
showStatistics('Right', $as, $user, $dailyResults); ?>
	</tr></table>
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
	{
		var form = document.createElement('form');
		form.method = 'POST';
		form.action = '/users/deleteOldTsumegoStatuses';
		document.body.appendChild(form);
		form.submit();
	}
}
</script>
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
