
<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'tags']); ?>

<?php echo HighscoreHelper::renderTable(
	'Tags Added',
	$tagContributors,
	[
		['label' => 'Tags', 'render' => fn($c) => $c['tag_count']],
	],
	$totalUsers,
); ?>
</div>
