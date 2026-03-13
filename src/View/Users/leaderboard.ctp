	<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'daily']); ?>

<?php if (Auth::isAdmin()): ?>
<p style="font-weight:400;">Signed in users today: <?php echo $uNum; ?></p>
<?php endif; ?>

<?php
// Format date for caption
$d1day = date('d. ');
if ($d1day[0] == '0')
	$d1day = substr($d1day, -3);
$dateStr = $d1day . date('F') . ' ' . date('Y');

echo HighscoreHelper::renderTable(
	'Daily Highscore<br><span style="display:inline-block;margin-top:4px;font-weight:normal;text-decoration:none">' . h($dateStr) . '</span>',
	$users,
	[
		['label' => 'Solved', 'render' => fn($row) => $row['daily_solved'] . ' solved'],
		['label' => 'XP', 'render' => fn($row) => '<b>' . $row['daily_xp'] . ' XP</b>'],
	],
	null,
	'dailyHighscoreTable',
	fn($row, $pos) => 'background-color:' . Util::smallScoreTableRowColor($pos - 1),
);
?>

<p style="font-weight:400;font-style:italic;">Users can be user of the day once per week.</p>
</div>
