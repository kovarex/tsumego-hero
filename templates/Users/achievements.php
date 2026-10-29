<?php
use App\View\Helper\AchievementHelper;
use App\View\Helper\HighscoreHelper;
use App\Utility\AchievementId;

/**
 * @var \App\View\AppView $this
 * @var int $totalUsers
 * @var array $users
 */

?>
<div align="center" class="highscore">
<?php echo $this->element('highscore_nav', ['activeTab' => 'achievements']); ?>

<?php echo HighscoreHelper::renderTable(
	'Achievement Highscore',
	$users,
	[
		[
			'label' => 'Completed',
			'render' => fn($user) => $user['achievement_score'] . '/' . AchievementId::count()
				. AchievementHelper::renderEarnedTimes((int) $user['achievement_earned_times']),
		],
	],
	$totalUsers,
); ?>
</div>
