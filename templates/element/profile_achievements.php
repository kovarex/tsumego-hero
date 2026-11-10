<?php

use App\Model\Table\AchievementTable;
use App\Utility\UserProfile;
use App\Utility\Util;
use App\View\Helper\AchievementHelper;

/**
 * The achievements a player unlocked on their profile, the rarest first: a row each with the icon, the name and
 * the description, and on the right the day it was unlocked and the share of the active players who have it
 * (AchievementRarity), as a number and a thin bar. The rarest UserProfile::SHOWN_ACHIEVEMENT_COUNT show, the
 * rest after Show all (ProfilePage.js).
 *
 * @var \App\View\AppView $this
 * @var array $user
 * @var array $achievements See UserProfile::achievementsByRarity()
 * @var int $achievementCount How many achievements the site has
 */

$shownCount = UserProfile::SHOWN_ACHIEVEMENT_COUNT;
?>
<section class="profile-achievements" id="profile-achievements" aria-labelledby="profile-achievements-title">
	<div class="profile-achievements__header">
		<h2 class="profile-achievements__title" id="profile-achievements-title">Achievements
			<span class="profile-achievements__count"><?php echo count($achievements); ?> / <?php echo $achievementCount; ?></span></h2>
		<a class="profile-achievements__all" href="/achievements/user/<?php echo $user['id']; ?>">All achievements</a>
	</div>
	<?php if (!$achievements): ?>
	<p class="profile-achievements__empty">No achievements unlocked yet.</p>
	<?php else: ?>
	<div class="profile-achievements__list">
		<?php foreach ($achievements as $index => $achievement):
			$url = '/achievements/view/' . $achievement['id'] . '/' . $user['id'];
			$rarity = AchievementTable::getRarityLabel($achievement['percent']); ?>
		<div class="profile-achievement" data-achievement-id="<?php echo $achievement['id']; ?>"<?php if ($index >= $shownCount) echo ' hidden data-profile-achievement-more'; ?>>
			<a class="profile-achievement__icon" href="<?php echo $url; ?>"><img src="/img/<?php echo h($achievement['image']); ?>.png" alt="" width="48" height="48" loading="lazy"></a>
			<div class="profile-achievement__text">
				<a class="profile-achievement__name" href="<?php echo $url; ?>"><?php echo h($achievement['name']); ?></a>
				<?php if ((int) $achievement['earned_count'] > 1): ?>
				<span class="profile-achievement__times" title="Earned <?php echo (int) $achievement['earned_count']; ?> times">×<?php echo (int) $achievement['earned_count']; ?></span>
				<?php endif; ?>
				<div class="profile-achievement__description"><?php echo h($achievement['description']); ?></div>
			</div>
			<div class="profile-achievement__rarity">
				<time datetime="<?php echo Util::toIso8601($achievement['created']); ?>" data-format="date"><?php echo h(substr($achievement['created'], 0, 10)); ?></time>
				<span class="profile-achievement__percent" title="<?php echo h($rarity); ?>"><?php echo h(AchievementHelper::formatPercent($achievement['percent'])); ?> of players</span>
				<div class="profile-achievement__bar profile-achievement__bar--<?php echo strtolower(str_replace(' ', '-', $rarity)); ?>">
					<div class="profile-achievement__bar-fill" style="width:<?php echo round($achievement['percent'], 2); ?>%"></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php if (count($achievements) > $shownCount): ?>
	<button type="button" class="btn profile-achievements__toggle" id="profile-achievements-toggle" aria-expanded="false"
		data-show-all="Show all <?php echo count($achievements); ?>" data-show-fewer="Show the rarest <?php echo $shownCount; ?>">Show all <?php echo count($achievements); ?></button>
	<?php endif; ?>
	<?php endif; ?>
</section>
