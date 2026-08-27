<?php
/**
 * @var View $this
 * @var array $achievement
 * @var int $completerCount
 * @var array $completers
 * @var float $completionPercent
 * @var array $currentUserStatus
 * @var int|null $progress
 * @var int|null $progressGoal
 * @var string $rarity
 * @var int $userCount
 */

?>
	
	<div align="center" >
	<?php
	if (!empty($currentUserStatus))
		$aColor = $achievement['Achievement']['color'];
	else $aColor = 'achievementColorGray';
?>
	<p class="title <?php echo $aColor; ?>2">
				<br>Achievement: <?php echo h($achievement['Achievement']['name']); ?>
				<br><br> 
				</p>
				<?php
				$rarityClass = strtolower(str_replace(' ', '-', $rarity));
?>
				<div style="margin: 8px 0;">
					<span class="rarity rarity--<?php echo $rarityClass; ?>" title="<?php echo $completionPercent; ?>% of all users"><?php echo h($rarity); ?></span>
				</div>
				<div class="achievemetProfileLink">
					<?php
						if (Auth::isLoggedIn())
							echo '<a href="/users/view/' . Auth::getUserID() . '">Profile</a>';
?>
				</div>
				<div class="achievemetIndexLink">
					<a href="/achievements">View Achievements</a>
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
			<?php
		$a46style = '';
if(!empty($currentUserStatus) && $achievement['Achievement']['id'] == 46)
{
	$a46style = ' style="top:-22px;"';
	?>
			<div class="acImgXp2">
				<?php echo $currentUserStatus['AchievementStatus']['value']; ?>
			</div>
			<?php } ?>
			<div class="acImgXp"<?php echo $a46style; ?>>
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
	<font color="gray">
	<?php
	if ($achievement['Achievement']['additionalDescription'] != null)
		echo '*' . h($achievement['Achievement']['additionalDescription']);
?></font>
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