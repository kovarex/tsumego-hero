<?php

use App\Model\Table\AchievementTable;
use App\Utility\Util;
use App\View\Helper\AchievementHelper;

/**
 * Every achievement as a row, the most common first: the icon, the name and the description, and on the right the
 * day it was unlocked, or the progress toward a locked one with a counter, and the share of the active players who
 * have it (AchievementRarity) as a number and a thin bar. A locked row is greyed out.
 *
 * @var \App\View\AppView $this
 * @var array $achievements See AchievementsController::renderAchievementsPage()
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
	<section class="achievement-list" aria-labelledby="achievement-list-title">
		<h2 class="achievement-list__title" id="achievement-list-title">
		<?php if (isset($viewedUser)): ?>
			<?php echo $unlockedCount; ?> / <?php echo count($achievements); ?> unlocked
		<?php else: ?>
			<?php echo count($achievements); ?> achievements
		<?php endif; ?>
		</h2>
		<div class="achievement-list__rows">
		<?php foreach ($achievements as $achievement):
			$rarity = AchievementTable::getRarityLabel($achievement['percent']); ?>
			<div class="achievement-row achievement-row--<?php echo $achievement['unlocked'] ? 'unlocked' : 'locked'; ?>" data-achievement-id="<?php echo $achievement['id']; ?>">
				<a class="achievement-row__icon" href="<?php echo $achievement['url']; ?>"><img src="/img/<?php echo h($achievement['image']); ?>.png" alt="" width="48" height="48" loading="lazy"></a>
				<div class="achievement-row__text">
					<a class="achievement-row__name" href="<?php echo $achievement['url']; ?>"><?php echo h($achievement['name']); ?></a>
					<?php if ($achievement['earned_count'] > 1): ?>
					<span class="achievement-row__times" title="Earned <?php echo $achievement['earned_count']; ?> times">×<?php echo $achievement['earned_count']; ?></span>
					<?php endif; ?>
					<div class="achievement-row__description"><?php echo h($achievement['description']); ?></div>
				</div>
				<div class="achievement-row__status">
				<?php if ($achievement['unlocked']): ?>
					<time datetime="<?php echo Util::toIso8601($achievement['unlocked_at']); ?>" data-format="date"><?php echo substr($achievement['unlocked_at'], 0, 10); ?></time>
				<?php elseif ($achievement['progress'] !== null): ?>
					<div class="progress achievement-row__progress">
						<div class="progress__fill progress__fill--tonal" style="--percent:<?php echo $achievement['progress_percent']; ?>;width:<?php echo $achievement['progress_percent']; ?>%"></div>
						<div class="progress__label"><?php echo $achievement['progress']; ?>/<?php echo $achievement['progress_goal']; ?></div>
					</div>
				<?php endif; ?>
					<span class="achievement-row__percent" title="<?php echo h($rarity); ?>"><?php echo h(AchievementHelper::formatPercent($achievement['percent'])); ?> of players</span>
					<div class="achievement-row__bar achievement-row__bar--<?php echo strtolower(str_replace(' ', '-', $rarity)); ?>">
						<div class="achievement-row__bar-fill" style="width:<?php echo round($achievement['percent'], 2); ?>%"></div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	</section>
</div>
