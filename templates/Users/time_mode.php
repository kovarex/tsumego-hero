<?php

use App\View\Helper\HighscoreHelper;

/**
 * @var \App\View\AppView $this
 * @var int $categoryIndex
 * @var string $rankName
 * @var array<int, string> $rankNames
 * @var array<int, true> $playedRankIDs
 * @var array $users
 * @var int $totalUsers
 */
?>

<div class="legacy-center highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'time']); ?>

<div class="highscore__categories">
<?php foreach ([2 => 'Slow', 1 => 'Fast', 0 => 'Blitz'] as $index => $categoryName): ?>
	<?php if ($index === $categoryIndex): ?>
		<a class="btn btn--pill is-selected" aria-current="page"><?php echo $categoryName; ?></a>
	<?php else: ?>
		<a class="btn btn--pill" href="/users/time_mode?category=<?php echo $index; ?>&rank=<?php echo h($rankName); ?>"><?php echo $categoryName; ?></a>
	<?php endif; ?>
<?php endforeach; ?>
</div>

<div class="highscore__ranks">
<?php foreach ($rankNames as $rankID => $name): ?>
	<?php if ($name === $rankName): ?>
		<a class="btn btn--pill is-selected" aria-current="page"><?php echo $name; ?></a>
	<?php elseif (isset($playedRankIDs[$rankID])): ?>
		<a class="btn btn--pill" href="/users/time_mode?category=<?php echo $categoryIndex; ?>&rank=<?php echo $name; ?>"><?php echo $name; ?></a>
	<?php else: ?>
		<a class="btn btn--pill" aria-disabled="true" title="Nobody has played this rank yet"><?php echo $name; ?></a>
	<?php endif; ?>
<?php endforeach; ?>
</div>

<?php
echo HighscoreHelper::renderTable(
	'Time Mode Highscore',
	$users,
	[
		['label' => 'Points', 'render' => fn($user) => $user['points']],
	],
	$totalUsers,
);
?>
</div>
