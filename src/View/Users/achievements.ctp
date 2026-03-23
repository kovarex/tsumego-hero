<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'achievements']); ?>

<?php echo HighscoreHelper::renderTable(
	'Achievement Highscore',
	$users,
	[
		['label' => 'Completed', 'render' => fn($user) => $user['achievement_score'] . '/' . Achievement::COUNT],
	],
	fn() => 'color9d',
); ?>
</div>
