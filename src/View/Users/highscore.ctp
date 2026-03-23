
<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'level']); ?>

<?php echo HighscoreHelper::renderTable(
	'Level Highscore',
	$users,
	[
		['label' => 'Level', 'render' => fn($user) => 'Level ' . $user['level']],
		['label' => 'XP', 'render' => fn($user) => Level::getOverallXPGained($user) . ' XP'],
		['label' => 'Solved', 'render' => fn($user) => $user['solved']],
	],
	function ($user, $pos) {
		if ($pos == 1) return 'color1';
		if ($pos <= 3) return 'color2';
		if ($pos <= 10) return 'color3';
		if ($pos <= 20) return 'color4';
		if ($pos <= 30) return 'color5';
		if ($pos <= 40) return 'color6';
		if ($pos <= 50) return 'color7';
		if ($pos <= 60) return 'color8';
		if ($pos <= 70) return 'color9';
		if ($pos <= 80) return 'color10';
		if ($pos <= 90) return 'color11';
		if ($pos <= 100) return 'color12x';
		return 'color13';
	},
); ?>
</div>
