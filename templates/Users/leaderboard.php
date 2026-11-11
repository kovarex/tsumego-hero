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
<?php echo $this->element('highscore_nav', ['activeTab' => 'daily']); ?>

<?php

echo HighscoreHelper::renderTable(
	'Daily Highscore<br><span class="highscore__date"><time datetime="' . date('Y-m-d') . '" data-format="date">' . date('Y-m-d') . '</time></span>',
	$users,
	[
		['label' => 'Solved', 'render' => fn($row) => Util::formatNumber($row['daily_solved'])],
		['label' => 'XP', 'render' => fn($row) => Util::formatNumber($row['daily_xp'])],
	],
	$totalUsers,
);
?>

<p class="highscore__note">Users can be user of the day once per week.</p>
</div>
