<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'rating']); ?>

<?php echo HighscoreHelper::renderTable(
	'Rating Highscore',
	$users,
	[
		['label' => 'Rating', 'render' => fn($user) => number_format($user['rating'])],
	],
	$totalUsers,
); ?>
</div>
