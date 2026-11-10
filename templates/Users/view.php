<?php

use App\View\Helper\AchievementHelper;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\HeroPowers;
use App\Utility\Level;
use App\Utility\Rating;
use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var int $achievementCount
 * @var array{completed: int, earned_times: int} $achievementCounts
 * @var array $achievements See UserProfile::achievementsByRarity()
 * @var array $dailyResults
 * @var array|null $mistakeTraining
 * @var int $solvedProblemCount
 * @var int $solvedPublishedCount
 * @var int $publishedProblemCount
 * @var array $timeGraph
 * @var array $timeModeRanks
 * @var int $tsumegoCount
 * @var array $user The row of the player (UserTable::row())
 */
?>

<div>
	<?php echo $this->element('user_header', [
		'user' => $user,
		'activeTab' => 'profile',
		'corner' => Auth::isOwner() && !UserTable::isDeleted($user) ? $this->element('role_form', ['user' => $user]) : null,
	]); ?>

<div class="cluster cluster--stretch">
	<div class="card card--purple">
		<table class="stat" id="level-info-table">
			<tr>
				<td>Level:</td>
				<td><?php echo $user['level']; ?></td>
			</tr>
			<tr>
				<td>Next level:</td>
				<td><?php echo $user['xp'] . '/' . Level::getXPForNext($user['level']) . ' XP'; ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php
					$xpForNext = Level::getXPForNext($user['level']);
					$xpPercent = $xpForNext > 0 ? round($user['xp'] / $xpForNext * 100) : 0;
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
					$maxHealth = Util::getHealthBasedOnLevel($user['level']);
					$remainingHealth = max(0, $maxHealth - ($user['damage'] ?? 0));
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
		<?php echo $this->element('profile_daily_streak', ['user' => $user]); ?>
	</div>

	<?php $highestRating = UserTable::getHighestRating($user); ?>
	<div class="card card--purple">
		<table class="stat" id="rank-info-table">
			<tr>
				<td colspan="2" class="rank"><span class="rank__label">Rank</span></td>
			</tr>
			<tr>
				<td colspan="2" class="rank"><span class="rank-icon"><?php echo Rating::getReadableRankFromRating($user['rating']); ?></span></td>
			</tr>
			<tr>
				<td>Rating:</td>
				<td><?php echo round($user['rating']); ?></td>
			</tr>
			<tr>
				<td>Highest rank:</td>
				<td><?php echo Rating::getReadableRankFromRating($highestRating); ?></td>
			</tr>
			<tr>
				<td>Highest rating:</td>
				<td><?php echo round($highestRating); ?></td>
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
			<?php if ($user['created']): ?>
			<tr>
				<td>Member since:</td>
				<td><?php echo date('F Y', strtotime($user['created'])); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td>Completed:</td>
				<td><?php echo $solvedProblemCount . ' of ' . $tsumegoCount; ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php
					// One scale, two kinds of problems: up to the boundary the problems of the published
					// collections, from it on the collections the site holds but does not offer.
					$completedPercent = Util::getPercentButAvoid100UntilComplete($solvedProblemCount, $tsumegoCount);
					$publishedShare = $tsumegoCount > 0 ? round($publishedProblemCount / $tsumegoCount * 100, 2) : 100;
					$solvedPublished = min($solvedPublishedCount, $publishedProblemCount);
					$solvedDrafts = max(0, min($solvedProblemCount - $solvedPublished, $tsumegoCount - $publishedProblemCount));
					$publishedPercent = $tsumegoCount > 0 ? round($solvedPublished / $tsumegoCount * 100, 2) : 0;
					$draftsPercent = $tsumegoCount > 0 ? round($solvedDrafts / $tsumegoCount * 100, 2) : 0;
					?>
					<div class="progress">
						<?php if ($publishedProblemCount < $tsumegoCount): ?>
						<div class="progress__drafts" style="left:<?php echo $publishedShare; ?>%"
							title="From here on: <?php echo $tsumegoCount - $publishedProblemCount; ?> problems of collections the site does not publish yet, level evaluation and other drafts."></div>
						<?php endif; ?>
						<div class="progress__fill" style="width:<?php echo $publishedPercent; ?>%"></div>
						<?php if ($solvedDrafts > 0): ?>
						<div class="progress__fill progress__fill--drafts" style="left:<?php echo $publishedShare; ?>%;width:<?php echo $draftsPercent; ?>%"
							title="<?php echo $solvedDrafts; ?> of those problems solved"></div>
						<?php endif; ?>
						<div class="progress__label"><?php echo $completedPercent; ?>%</div>
					</div>
				</td>
			</tr>
			<tr>
				<td>Achievements:</td>
				<td><?php echo $achievementCounts['completed'] . ' of ' . $achievementCount . AchievementHelper::renderEarnedTimes($achievementCounts['earned_times']); ?></td>
			</tr>
			<tr>
				<td colspan="2">
					<?php $achievementPercent = $achievementCount > 0 ? round($achievementCounts['completed'] / $achievementCount * 100) : 0; ?>
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
$isPremium = HeroPowers::hasPremiumUnlock($user);
$hasRevelationBonus = HeroPowers::hasContributionRevelationBonus($user);
$unlockedCount = 0;
foreach ($heroPowers as $power)
	if ($user['level'] >= $power['level'])
		$unlockedCount++;
?>
<div class="hero-powers">
	<div class="hero-powers__title">Hero Powers: <?php echo $unlockedCount . ' of ' . count($heroPowers); ?> unlocked</div>
	<div class="hero-powers__timeline">
		<div class="hero-powers__track">
			<div class="hero-powers__fill" style="width:<?php echo min(100, round($user['level'] / $maxPowerLevel * 100)); ?>%"></div>
		</div>
		<?php foreach ($heroPowers as $power):
			$pos = round($power['level'] / $maxPowerLevel * 100);
			$isUnlocked = $user['level'] >= $power['level'];
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
<?php echo $this->element('profile_achievements', compact('user', 'achievements', 'achievementCount')); ?>
<?php
	$size = count($dailyResults);
	if ($size < 10)
		$height = '400';
	elseif($size < 30)
		$height = '600';
	elseif ($size < 50)
		$height = '900';
	else
		$height = '1200';
?>
<div class="userBottom1">
	<table class="profileTable">
	<tr>
	<td class="profileTable__half">
		<div class="legacy-center">
			<a id="userShowLevelButtonLeft" class="btn" data-profile-show="level" data-profile-side="Left">Level</a>
			<a id="userShowRatingButtonLeft" class="btn" data-profile-show="rating" data-profile-side="Left">Rating</a>
			<a id="userShowTimeButtonLeft" class="btn" data-profile-show="time" data-profile-side="Left">Time</a>
		</div>
	</td>
	<td class="profileTable__half">
		<div class="legacy-center">
			<a id="userShowLevelButtonRight" class="btn" data-profile-show="level" data-profile-side="Right">Level</a>
			<a id="userShowRatingButtonRight" class="btn" data-profile-show="rating" data-profile-side="Right">Rating</a>
			<a id="userShowTimeButtonRight" class="btn" data-profile-show="time" data-profile-side="Right">Time</a>
		</div>
	</td>
	</tr>
	</table>
	<br>
	<table class="profileTable">
		<tr>
			<?php foreach (['Left', 'Right'] as $side)
				echo $this->element('profile_statistics', compact('side', 'dailyResults', 'timeGraph')); ?>
	</tr></table>
</div>

<?php echo ViteManifest::legacyScript('profile'); ?>
