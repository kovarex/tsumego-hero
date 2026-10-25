<?php

use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\HeroPowers;
use App\Utility\Level;
use App\Utility\Rating;
use App\Utility\TimeGraphRenderer;
use App\Utility\Util;
use App\Utility\ValueGraphRenderer;

/**
 * @var View $this
 * @var array $aCount
 * @var int $aNum
 * @var int $aEarnedTimes
 * @var array $as
 * @var array $dailyResults
 * @var array|null $mistakeTraining
 * @var int $solvedProblemCount
 * @var array $timeGraph
 * @var array $timeModeRanks
 * @var int $tsumegoCount
 * @var array $user
 */
?>

<?php TimeGraphRenderer::renderScriptInclude(); ?>

<div>
	<?php echo $this->element('user_header', ['user' => $user['User'], 'activeTab' => 'profile']); ?>
	<?php if (Auth::isOwner() && !User::isDeleted($user['User'])): ?>
	<div class="profile-role"><?php echo $this->element('role_form', ['user' => $user['User']]); ?></div>
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
					<?php $healthPercent = $maxHealth > 0 ? round($remainingHealth / $maxHealth * 100) : 0; ?>
					<div class="progress">
						<div class="progress__fill progress__fill--tonal" style="--percent:<?php echo $healthPercent; ?>;width:<?php echo $healthPercent; ?>%"></div>
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
			<?php if ($user['User']['created']): ?>
			<tr>
				<td>Member since:</td>
				<td><?php echo date('F Y', strtotime($user['User']['created'])); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td>Completed:</td>
				<td><?php echo $solvedProblemCount . ' of ' . $tsumegoCount; ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php $completedPercent = Util::getPercentButAvoid100UntilComplete($solvedProblemCount, $tsumegoCount); ?>
					<div class="progress">
						<div class="progress__fill" style="width:<?php echo $completedPercent; ?>%"></div>
						<div class="progress__label"><?php echo $completedPercent; ?>%</div>
					</div>
				</td>
			</tr>
			<tr>
				<td>Achievements:</td>
				<td><?php echo $aNum . ' of ' . count($aCount) . AchievementHelper::renderEarnedTimes($aEarnedTimes); ?></td>
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
<?php if ($mistakeTraining !== null)
	echo $this->element('mistake_training_profile', ['training' => $mistakeTraining]); ?>
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
			<a id="userShowLevelButtonLeft" class="btn" onclick="activateSelection('level', 'Left');">Level</a>
			<a id="userShowRatingButtonLeft" class="btn" onclick="activateSelection('rating', 'Left');">Rating</a>
			<a id="userShowTimeButtonLeft" class="btn" onclick="activateSelection('time', 'Left');">Time</a>
			<a id="userShowAchievementsButtonLeft" class="btn" onclick="activateSelection('achievement', 'Left');">Achievements</a>
		</div>
	</td>
	<td width="50%">
		<div align="center">
			<a id="userShowLevelButtonRight" class="btn" onclick="activateSelection('level', 'Right');">Level</a>
			<a id="userShowRatingButtonRight" class="btn" onclick="activateSelection('rating', 'Right');">Rating</a>
			<a id="userShowTimeButtonRight" class="btn" onclick="activateSelection('time', 'Right');">Time</a>
			<a id="userShowAchievementsButtonRight" class="btn" onclick="activateSelection('achievement', 'Right');">Achievements</a>
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
		$earnedCount = (int) ($as[$i]['AchievementStatus']['a_earned_count'] ?? 1);
	?>
		<a href="/achievements/view/<?php echo $as[$i]['AchievementStatus']['a_id'] . '/' . $user['User']['id']; ?>">
		<div align="center" class="achievementSmall <?php echo $as[$i]['AchievementStatus']['a_color']; ?>">
			<div class="acTitle2">
				<b <?php echo $adjust; ?>><?php echo h($as[$i]['AchievementStatus']['a_title']); ?></b>
			</div>
			<div class="acImg">
				<img src="/img/<?php echo h($as[$i]['AchievementStatus']['a_image']); ?>.png" title="<?php echo h($as[$i]['AchievementStatus']['a_description']); ?>">
				<?php echo AchievementHelper::renderEarnedBadge($earnedCount); ?>
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

function updateButtonActivity(id, side, active)
{
	$("#" + id + 'Button' + side).toggleClass("btn--inactive", !active);
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
