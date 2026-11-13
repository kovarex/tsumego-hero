<?php
/**
 * Shared navigation for all highscore pages.
 * Usage: $this->element('highscore_nav', ['activeTab' => 'rating'])
 *
 * Tabs: level, rating, time, achievements, tags, daily
 * 
 * @var \App\View\AppView $this
 * @var string $activeTab
 */

$tabs = [
	'level' => '/users/highscore',
	'rating' => '/users/rating',
	'time' => '/users/time_mode',
	'achievements' => '/users/achievements',
	'tags' => '/users/added_tags',
	'daily' => '/users/leaderboard',
];
?>
<nav class="highscore-nav" aria-label="Highscores">
	<?php foreach ($tabs as $name => $url): ?>
		<?php if ($name === $activeTab): ?>
			<a class="btn btn--pill is-selected" href="<?php echo $url; ?>" aria-current="page"><?php echo ucfirst($name); ?></a>
		<?php else: ?>
			<a class="btn btn--pill" href="<?php echo $url; ?>"><?php echo ucfirst($name); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
