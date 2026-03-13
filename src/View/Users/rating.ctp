<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'rating']); ?>

<?php echo HighscoreHelper::renderTable(
	'Rating Highscore',
	$users,
	[
		['label' => 'Rating', 'render' => fn($user) => round($user['rating'])],
	],
	function ($user) {
		$rank = Rating::getRankFromRating($user['rating']);
		$styleRank = Util::clampOptional($rank, Rating::getRankFromReadableRank('20k'), Rating::getRankFromReadableRank('9d'));
		return 'color' . Rating::getReadableRank($styleRank);
	},
); ?>
</div>
