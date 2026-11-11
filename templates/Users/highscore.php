<?php

use App\Utility\Util;
use App\View\Helper\HighscoreHelper;
use App\Utility\Level;

/**
 * @var \App\View\AppView $this
 * @var int $totalUsers
 * @var array $users
 */

?>
<div class="legacy-center highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'level']); ?>

<?php echo HighscoreHelper::renderTable(
	'Level Highscore',
	$users,
	[
		['label' => 'Level', 'render' => fn($user) => $user['level']],
		['label' => 'XP', 'render' => fn($user) => Util::formatNumber(Level::getOverallXPGained($user))],
		['label' => 'Solved', 'render' => fn($user) => Util::formatNumber($user['solved'])],
	],
	$totalUsers,
); ?>
</div>
