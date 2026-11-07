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
<table class="highscore-nav">
<tr>
	<td class="highscore-nav__side valign-top"></td>
	<td class="highscore-nav__middle valign-top">
		<div class="legacy-center">
		<br>
		<?php foreach ($tabs as $name => $url): ?>
			<?php $class = ($name === $activeTab) ? 'btn--inactive' : ''; ?>
			<a class="btn <?php echo $class; ?>" href="<?php echo $url; ?>"><?php echo $name; ?></a>
		<?php endforeach; ?>
		<br><br>
		</div>
	</td>
	<td class="highscore-nav__side valign-top"></td>
</tr>
</table>
