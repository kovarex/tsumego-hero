<?php

use App\Utility\Util;
use App\View\Helper\HighscoreHelper;

/**
 * @var \App\View\AppView $this
 * @var int $totalUsers
 * @var array $users
 */

?>
<div class="legacy-center highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'rating']); ?>

<?php echo HighscoreHelper::renderTable(
	'Rating Highscore',
	$users,
	[
		['label' => 'Rating', 'render' => fn($user) => Util::formatNumber($user['rating'])],
	],
	$totalUsers,
); ?>
</div>
