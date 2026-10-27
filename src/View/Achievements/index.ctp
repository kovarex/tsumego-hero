<?php

use App\Utility\Auth;
use App\Utility\Util;

/**
 * @var View $this
 * @var array $achievements
 * @var int $unlockedCount
 * @var array|null $viewedUser
 */

?>
<div class="achievementsPage">
<?php if (isset($viewedUser)): ?>
	<?php echo $this->element('user_header', ['user' => $viewedUser, 'activeTab' => 'achievements']); ?>
<?php else: ?>
	<p class="profile-username">Achievements</p>
<?php endif; ?>
	<div id="achievementWrapper" class="clearfix">
	<?php foreach ($achievements as $achievement): ?>
		<a href="<?php echo $achievement['url']; ?>">
		<div class="achievement1 <?php echo $achievement['color']; ?>">
			<div class="acTitle">
				<h1<?php if ($achievement['long_name']) echo ' class="acTitleLong"'; ?>><?php echo h($achievement['name']); ?></h1>
			</div>
			<div class="acImg">
				<img src="/img/<?php echo $achievement['image']; ?>.png"><br>
				<?php echo AchievementHelper::renderEarnedBadge($achievement['earned_count']); ?>
				<div class="acImgXp"><?php echo $achievement['xp']; ?> XP</div>
			</div>
			<div class="acDesc"><?php echo h($achievement['description']); ?></div>
		<?php if ($achievement['unlocked']): ?>
			<div class="acDate">
				<time datetime="<?php echo Util::toIso8601($achievement['unlocked_at']); ?>" data-format="datetime"><?php echo $achievement['unlocked_at']; ?></time>
			</div>
		<?php elseif ($achievement['progress'] !== null): ?>
			<div class="acDate">
				<div class="progress acProgress">
					<div class="progress__fill progress__fill--tonal" style="--percent:<?php echo $achievement['progress_percent']; ?>;width:<?php echo $achievement['progress_percent']; ?>%"></div>
					<div class="progress__label"><?php echo $achievement['progress']; ?>/<?php echo $achievement['progress_goal']; ?></div>
				</div>
			</div>
		<?php endif; ?>
		</div>
		</a>
	<?php endforeach; ?>
	</div>
	<br>
	<br>
<?php if (isset($viewedUser)): ?>
	<?php echo $viewedUser['id'] != Auth::getUserID() ? h($viewedUser['display_name']) : 'You'; ?> completed <?php echo $unlockedCount; ?> of <?php echo count($achievements); ?> achievements.
<?php endif; ?>
	<br>
	<br>
</div>
