<?php

/**
 * @var View $this
 * @var array $tagContributors
 * @var int $totalUsers
 */

?>

<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'tags']); ?>

<?php echo HighscoreHelper::renderTable(
	'Tags Added',
	$tagContributors,
	[
		['label' => 'Tags', 'render' => fn($row) => $row['tag_count']],
	],
	$totalUsers,
); ?>
</div>
