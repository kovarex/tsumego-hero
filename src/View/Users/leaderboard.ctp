<?php
/**
 * @var View $this
 * @var int $totalUsers
 * @var array $users
 */

?>
	<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'daily']); ?>

<?php

echo HighscoreHelper::renderTable(
	'Daily Highscore<br><span style="display:inline-block;margin-top:4px;font-weight:normal;text-decoration:none"><time datetime="' . date('c') . '" data-format="date">' . date('Y-m-d') . '</time></span>',
	$users,
	[
		['label' => 'Solved', 'render' => fn($row) => $row['daily_solved']],
		['label' => 'XP', 'render' => fn($row) => number_format($row['daily_xp'])],
	],
	$totalUsers,
);
?>

<p style="font-weight:400;font-style:italic;">Users can be user of the day once per week.</p>
</div>
