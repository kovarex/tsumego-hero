<?php
/**
 * Shared navigation for all highscore pages.
 * Usage: $this->element('highscore_nav', ['activeTab' => 'rating'])
 *
 * Tabs: level, rating, time, achievements, tags, daily
 */
$tabs = [
	'level' => '/users/highscore',
	'rating' => '/users/rating',
	'time' => '/users/highscore3',
	'achievements' => '/users/achievements',
	'tags' => '/users/added_tags',
	'daily' => '/users/leaderboard',
];
?>
<table border="0" width="100%">
<tr>
	<td width="23%" valign="top"></td>
	<td width="53%" valign="top">
		<div align="center">
		<br>
		<?php foreach ($tabs as $name => $url): ?>
			<?php $class = ($name === $activeTab) ? 'buttonx-current' : 'new-buttonx'; ?>
			<a class="new-button <?php echo $class; ?>" href="<?php echo $url; ?>"><?php echo $name; ?></a>
		<?php endforeach; ?>
		<br><br>
		</div>
	</td>
	<td width="23%" valign="top"></td>
</tr>
</table>
