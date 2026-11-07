<?php

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
		['label' => 'Rating', 'render' => fn($user) => number_format($user['rating'])],
	],
	$totalUsers,
); ?>
</div>
