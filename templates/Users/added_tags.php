<?php

use App\Utility\Util;
use App\View\Helper\HighscoreHelper;

/**
 * @var \App\View\AppView $this
 * @var array $tagContributors
 * @var int $totalUsers
 */

?>

<div class="legacy-center highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'tags']); ?>

<?php echo HighscoreHelper::renderTable(
	'Tags Added',
	$tagContributors,
	[
		['label' => 'Tags', 'render' => fn($row) => Util::formatNumber($row['tag_count'])],
	],
	$totalUsers,
); ?>
</div>
