<?php

use App\Utility\ChartRenderer;
use App\Utility\Util;
use App\View\Helper\AchievementHelper;

/**
 * One of the two columns of statistics under the profile, each with a switch of what it shows.
 *
 * @var \App\View\AppView $this
 * @var string $side Left or Right
 * @var array $user
 * @var array $latestAchievements See UserProfile::latestAchievements()
 * @var array $dailyResults
 * @var array $timeGraph
 */
?>
		<td width="50%">
			<div id="userShowLevel<?php echo $side; ?>">
				<?php echo ChartRenderer::bar('Problems in level mode', array_reverse($dailyResults), 'day', [
					['label' => 'Solves', 'color' => '#74d14c'],
					['label' => 'Fails', 'color' => '#d63a49']]); ?>
			</div>
			<div id="userShowRating<?php echo $side; ?>">
				<?php echo ChartRenderer::ratingLine('Overall rating', $dailyResults); ?>
			</div>
			<div id="userShowTime<?php echo $side; ?>">
				<?php echo ChartRenderer::bar('Time mode runs', $timeGraph, 'category', [
					['label' => 'Passes', 'color' => '#c8723d'],
					['label' => 'Fails', 'color' => '#888888']]); ?>
			</div>
			<div id="userShowAchievements<?php echo $side; ?>">
				<table class="profile-statistics__heading">
					<tr>
						<td class="h1profile"><h1 class="h1">Achievements</h1></td>
						<td class="text-right"><b class="profileTable2"><a href="/achievements/user/<?php echo $user['id']; ?>">View Achievements</a></b></td>
					</tr>
				</table>
				<?php foreach ($latestAchievements as $achievement): ?>
				<a href="/achievements/view/<?php echo $achievement['id'] . '/' . $user['id']; ?>">
				<div class="legacy-center achievementSmall <?php echo $achievement['color']; ?>">
					<div class="acTitle2">
						<b<?php echo strlen($achievement['name']) > 30 ? ' class="acTitle2__long"' : ''; ?>><?php echo h($achievement['name']); ?></b>
					</div>
					<div class="acImg">
						<img src="/img/<?php echo h($achievement['image']); ?>.png" title="<?php echo h($achievement['description']); ?>">
						<?php echo AchievementHelper::renderEarnedBadge((int) ($achievement['earned_count'] ?? 1)); ?>
						<div class="acImgXp">
						<?php echo $achievement['xp']; ?> XP
						</div>
					</div>
					<div class="acDate2">
						<time datetime="<?php echo Util::toIso8601($achievement['created']) ?>" data-format="datetime"><?php echo h($achievement['created']) ?></time>
					</div>
				</div>
				</a>
				<?php endforeach; ?>
			</div>
		</td>
