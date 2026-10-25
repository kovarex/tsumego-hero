<?php

use App\Utility\Auth;
use App\Utility\Util;

/**
 * @var View $this
 * @var array $achievement
 * @var int $completerCount
 * @var array $completers
 * @var array $collections
 * @var float $completionPercent
 * @var array $currentUserStatus
 * @var int|null $progress
 * @var int|null $progressGoal
 * @var string $rarity
 * @var int $userCount
 * @var array|null $viewedUser
 */

// The card is grey until the player completed the achievement, and the badge on
// it counts the completions.
$aColor = empty($currentUserStatus) ? 'achievementColorGray' : $achievement['Achievement']['color'];
$earnedCount = empty($currentUserStatus) ? 0 : (int) $currentUserStatus['AchievementStatus']['value'];

// The player whose page this is: the one being viewed, or the logged in one.
$profileUserID = $viewedUser['id'] ?? (Auth::isLoggedIn() ? Auth::getUserID() : null);

?>
	
	<div align="center" >
	<div class="acHeader">
		<div class="achievemetIndexLink">
			<a href="/achievements<?php echo $viewedUser ? '/user/' . $viewedUser['id'] : ''; ?>">View Achievements</a>
		</div>
		<?php if ($viewedUser): ?>
		<p class="acOwner"><?php echo h($viewedUser['display_name']); ?>'s achievement</p>
		<?php endif; ?>
		<div class="achievemetProfileLink">
			<?php if ($profileUserID) echo '<a href="/users/view/' . $profileUserID . '">Profile</a>'; ?>
		</div>
	</div>
		<div align="center" id="achievementWrapper">
	<a href="/achievements" style="text-decoration:none;">
	<div align="center" class="achievement2 <?php
	echo $aColor;
$displayImage = $achievement['Achievement']['image'];
if (empty($currentUserStatus))
	$displayImage = 'ac000i';
?>">
		<div class="acTitle">
			<h1><?php echo h($achievement['Achievement']['name']); ?></h1>
		</div>
		<div class="acImg">
			<img src="/img/<?php echo $displayImage; ?>.png">
			<?php echo AchievementHelper::renderEarnedBadge($earnedCount); ?>
			<div class="acImgXp">
			<?php echo $achievement['Achievement']['xp']; ?> XP
			</div>
		</div>
		<div class="acDesc">
			<?php
	echo h($achievement['Achievement']['description']);
if ($achievement['Achievement']['additionalDescription'] != null)
	echo '*';
?>
		</div>
		<div class="acDate">
			<?php
			if (!empty($currentUserStatus))
				echo '<time datetime="' . Util::toIso8601($currentUserStatus['AchievementStatus']['created']) . '" data-format="datetime">' . $currentUserStatus['AchievementStatus']['created'] . '</time>';
?>
		</div>
	</div>
	</a>
	<?php if ($progress !== null && $progressGoal > 0): ?>
		<?php $progressPercent = (int) round($progress / $progressGoal * 100); ?>
		<div class="progress" style="width:220px;margin:0 auto;">
			<div class="progress__fill progress__fill--tonal" style="--percent:<?php echo $progressPercent; ?>;width:<?php echo $progressPercent; ?>%"></div>
			<div class="progress__label"><?php echo $progress; ?>/<?php echo $progressGoal; ?></div>
		</div>
	<?php endif; ?>
	<div class="acRarity">
		<span class="rarity rarity--<?php echo strtolower(str_replace(' ', '-', $rarity)); ?>" title="<?php echo $completionPercent; ?>% of all users"><?php echo h($rarity); ?></span>
	</div>
	<font color="gray">
	<?php
	if ($achievement['Achievement']['additionalDescription'] != null)
		echo '*' . h($achievement['Achievement']['additionalDescription']);
?></font>
	<?php if ($collections): ?>
	<div class="acCollections">
		<p class="acCollections__title">Collections that can earn this</p>
		<?php
		$markedCollections = 0;
		foreach ($collections as $collection)
			if ($collection['earned'])
				$markedCollections++;
		?>
		<?php if ($earnedCount > $markedCollections): ?>
		<p class="acCollections__hint"><?= (int) ($earnedCount - $markedCollections) ?> of the collections it was earned on no longer count.</p>
		<?php endif; ?>
		<div class="acCollections__list">
			<?php foreach ($collections as $collection): ?>
				<a href="<?= Set::url((int) $collection['id']) ?>" class="acCollections__link<?= $collection['earned'] ? ' acCollections__link--earned' : '' ?>"><?= h($collection['title']) ?><?php if (!$collection['earned'] && $collection['accuracy'] > 0): ?><span class="acCollections__percent"><?= h($collection['accuracy']) ?>%</span><?php endif; ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	<br>
	<br>
	<font color="gray">
	<?php
	if ($completerCount == 0)
		echo 'Nobody completed this achievement.';
	else
	{
		?>
	<div>Recently completed by</div>
	<div>
	<?php
			for ($i = 0; $i < count($completers); $i++)
			{
				if ($completers[$i]['AchievementStatus']['user'] != null)
					echo User::renderLink($completers[$i]['AchievementStatus']['user']);
				if ($i < count($completers) - 1) echo ', ';

			}
		$moreCount = $completerCount - count($completers);
		?>
	</div>
	<div>
	<?php
			echo $moreCount > 0 ? 'and ' . $moreCount . ' others.' : '';
		?>
	</div>
	<?php
	}
?>
	</font>
	</div>
		
	</div>