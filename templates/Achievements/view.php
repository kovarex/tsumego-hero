<?php

use App\View\Helper\AchievementHelper;
use App\Model\Table\SetTable;
use App\Model\Entity\Achievement;
use App\Model\Entity\AchievementStatus;
use App\Model\Table\UserTable;
use App\Utility\Auth;

/**
 * @var \App\View\AppView $this
 * @var Achievement $achievement
 * @var int $completerCount
 * @var array<array|null> $completers
 * @var array $collections
 * @var float $completionPercent
 * @var AchievementStatus|null $currentUserStatus
 * @var int|null $progress
 * @var int|null $progressGoal
 * @var string $rarity
 * @var int $userCount
 * @var array|null $viewedUser
 */

// The card is grey until the player completed the achievement, and the badge on
// it counts the completions.
$unlocked = $currentUserStatus !== null;
$aColor = $unlocked ? $achievement->color : 'achievementColorGray';
$displayImage = $unlocked ? $achievement->image : 'ac000i';
$earnedCount = $unlocked ? $currentUserStatus->value : 0;

// The player whose page this is: the one being viewed, or the logged in one.
$profileUserID = $viewedUser['id'] ?? (Auth::isLoggedIn() ? Auth::getUserID() : null);

// A repeatable achievement earned on more collections than are marked here was earned on some that no longer count
$markedCollections = count(array_filter($collections, fn(array $collection) => $collection['earned']));
?>
<div class="legacy-center">
	<div class="acHeader">
		<div class="achievemetIndexLink">
			<a href="/achievements<?php echo $viewedUser ? '/user/' . $viewedUser['id'] : ''; ?>">View Achievements</a>
		</div>
		<?php if ($viewedUser): ?>
		<p class="acOwner"><?php echo h($viewedUser['display_name']); ?>'s achievement</p>
		<?php endif; ?>
		<div class="achievemetProfileLink">
			<?php if ($profileUserID): ?><a href="/users/view/<?php echo $profileUserID; ?>">Profile</a><?php endif; ?>
		</div>
	</div>
	<div class="legacy-center" id="achievementWrapper">
	<a href="/achievements" class="achievement-view__back">
	<div class="legacy-center achievement2 <?php echo $aColor; ?>">
		<div class="acTitle">
			<h1><?php echo h($achievement->name); ?></h1>
		</div>
		<div class="acImg">
			<img src="/img/<?php echo $displayImage; ?>.png">
			<?php echo AchievementHelper::renderEarnedBadge($earnedCount); ?>
			<div class="acImgXp">
			<?php echo $achievement->xp; ?> XP
			</div>
		</div>
		<div class="acDesc">
			<?php echo h($achievement->description) . ($achievement->additional_description != null ? '*' : ''); ?>
		</div>
		<div class="acDate">
			<?php if ($unlocked): ?><time datetime="<?php echo $currentUserStatus->created->format('c'); ?>" data-format="datetime"><?php echo $currentUserStatus->created->format('Y-m-d H:i:s'); ?></time><?php endif; ?>
		</div>
	</div>
	</a>
	<?php if ($progress !== null && $progressGoal > 0):
		$progressPercent = (int) round($progress / $progressGoal * 100); ?>
		<div class="progress achievement-view__progress">
			<div class="progress__fill progress__fill--tonal" style="--percent:<?php echo $progressPercent; ?>;width:<?php echo $progressPercent; ?>%"></div>
			<div class="progress__label"><?php echo $progress; ?>/<?php echo $progressGoal; ?></div>
		</div>
	<?php endif; ?>
	<div class="acRarity">
		<span class="rarity rarity--<?php echo strtolower(str_replace(' ', '-', $rarity)); ?>" title="<?php echo $completionPercent; ?>% of all users"><?php echo h($rarity); ?></span>
	</div>
	<span class="text-muted">
	<?php if ($achievement->additional_description != null) echo '*' . h($achievement->additional_description); ?></span>
	<?php if ($collections): ?>
	<div class="acCollections">
		<p class="acCollections__title">Collections that can earn this</p>
		<?php if ($earnedCount > $markedCollections): ?>
		<p class="acCollections__hint"><?= (int) ($earnedCount - $markedCollections) ?> of the collections it was earned on no longer count.</p>
		<?php endif; ?>
		<div class="acCollections__list">
			<?php foreach ($collections as $collection): ?>
				<a href="<?= SetTable::url((int) $collection['id']) ?>" class="acCollections__link<?= $collection['earned'] ? ' acCollections__link--earned' : '' ?>"><?= h($collection['title']) ?><?php if (!$collection['earned'] && $collection['accuracy'] > 0): ?><span class="acCollections__percent"><?= h($collection['accuracy']) ?>%</span><?php endif; ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	<br>
	<br>
	<span class="text-muted">
	<?php if ($completerCount == 0): ?>
	Nobody completed this achievement.
	<?php else: ?>
	<div>Recently completed by</div>
	<div>
	<?php
	// a completer the users table no longer has keeps its place in the list, without a name
	echo implode(', ', array_map(fn(?array $completer) => $completer !== null ? UserTable::renderLink($completer) : '', $completers));
	$moreCount = $completerCount - count($completers);
	?>
	</div>
	<div>
	<?php echo $moreCount > 0 ? 'and ' . $moreCount . ' others.' : ''; ?>
	</div>
	<?php endif; ?>
	</span>
	</div>
</div>
