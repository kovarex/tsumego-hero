<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'level']); ?>

<?php echo HighscoreHelper::renderTable(
	'Level Highscore',
	$users,
	[
		['label' => 'Level', 'render' => fn($user) => $user['level']],
		['label' => 'XP', 'render' => fn($user) => number_format(Level::getOverallXPGained($user))],
		['label' => 'Solved', 'render' => fn($user) => number_format($user['solved'])],
	],
	$totalUsers,
); ?>
</div>
