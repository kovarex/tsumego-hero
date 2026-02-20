
<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'tags']); ?>

<?php echo HighscoreHelper::renderTable(
	'Tags Added',
	$tagContributors,
	[
		['label' => 'Tags', 'render' => fn($c) => '<b>' . $c['tag_count'] . '</b>'],
	],
	null,
	'dailyHighscoreTable',
	fn($c, $pos) => 'background-color:' . Util::smallScoreTableRowColor($pos - 1),
); ?>
</div>
