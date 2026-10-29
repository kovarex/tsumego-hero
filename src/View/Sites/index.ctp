<?php

use App\Model\Table\SetTable;
use App\Utility\Auth;
use App\Utility\ChartRenderer;
use App\Utility\Constants;
use App\Utility\MistakeTraining;
use App\Utility\TsumegoButtons;

/**
 * @var View $this
 * @var array<int, array{date: string, problems: int|null, users: int|null}> $chartData
 * @var string|null $latestPublishDate
 * @var string $quote
 * @var array $recentAchievements
 * @var TsumegoButtons $tsumegoButtonsOfPublishedTsumegos
 * @var array|null $userOfTheDay
 * @var int|null $mistakeTrainingDue Problems due in the mistake training, null when it is not offered
 */

$loggedIn = Auth::isLoggedIn();
$levelModeUrl = '/tsumegos/play/' . (int) ($_COOKIE['lastVisit'] ?? Constants::$DEFAULT_TSUMEGO_ID);
$modes = [
	['name' => 'level', 'url' => $levelModeUrl, 'label' => 'Level Mode', 'enabled' => true],
	['name' => 'rating', 'url' => '/ratingMode', 'label' => 'Rating Mode', 'enabled' => $loggedIn],
	['name' => 'time', 'url' => '/timeMode/overview', 'label' => 'Time Mode', 'enabled' => $loggedIn],
	['name' => 'achievements', 'url' => '/achievements', 'label' => 'Achievements', 'enabled' => $loggedIn],
];

$publishedBySet = [];
foreach ($tsumegoButtonsOfPublishedTsumegos as $tsumegoButton)
	$publishedBySet[$tsumegoButton->setID][] = $tsumegoButton;
?>
<div class="split">
	<div>
		<div class="new-tsumego-box new-tsumego-box--modes mode-select-box">
			<?php foreach ($modes as $mode): ?>
				<?php $image = '<img src="/img/mode-button-' . $mode['name'] . '.png?v=1.0" alt="' . $mode['label'] . '" title="' . $mode['label'] . '" width="134" height="123">'; ?>
				<?php if ($mode['enabled']): ?>
					<a class="mode-button mode-button-<?php echo $mode['name']; ?>" href="<?php echo $mode['url']; ?>"><?php echo $image; ?></a>
				<?php else: ?>
					<span class="mode-button mode-button-<?php echo $mode['name']; ?> mode-button-disabled"><?php echo $image; ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php if (isset($mistakeTrainingDue)): ?>
			<a class="mistake-training-card" href="<?php echo MistakeTraining::$OVERVIEW_LINK; ?>">
				<span class="mistake-training-card__title">Mistake Training</span>
				<?php if ($mistakeTrainingDue > 0): ?>
					<?php $mistakeTrainingDueLabel = $mistakeTrainingDue . ' problem' . ($mistakeTrainingDue === 1 ? '' : 's') . ' to review today'; ?>
					<span class="mistake-training-card__count" role="img" title="<?php echo $mistakeTrainingDueLabel; ?>" aria-label="<?php echo $mistakeTrainingDueLabel; ?>"><?php echo $mistakeTrainingDue; ?></span>
				<?php else: ?>
					<span class="mistake-training-card__text">Nothing to review today. Add past mistakes to training.</span>
				<?php endif; ?>
				<span class="mistake-training-card__go" aria-hidden="true">&rsaquo;</span>
			</a>
		<?php endif; ?>

		<div class="achievements-panel">
			<img src="/img/new_startpage/<?php echo h($quote); ?>e.png?v=4.3" class="achievement-quote-pick" alt="Most Recent Achievements" title="Most Recent Achievements">
			<div data-recent-achievements-root data-props="<?php echo h(json_encode(['achievements' => $recentAchievements])); ?>"></div>
		</div>

		<p class="title4">Site Growth</p>
		<div class="new1">
			<?php echo ChartRenderer::line(
				title: null,
				series: [
					['label' => 'Problems', 'color' => '#d19fe4', 'axis' => 'left', 'fill' => true, 'points' => array_map(fn($month) => [$month['date'], $month['problems']], $chartData)],
					['label' => 'Users', 'color' => '#5b9bd5', 'axis' => 'right', 'fill' => false, 'points' => array_map(fn($month) => [$month['date'], $month['users']], $chartData)]],
				period: 'month',
				height: 360,
				startAtZero: true,
				class: 'chart-box'); ?>
			<br>
		</div>
	</div>

	<div>
		<div class="new-tsumego-box new-tsumego-box--news">
			<?php if (!empty($publishedBySet)): ?>
				<span class="latest-additions-heading">Latest additions<?php if ($latestPublishDate !== null): ?> (<time datetime="<?php echo $latestPublishDate; ?>" data-format="month-day"><?php echo $latestPublishDate; ?></time>)<?php endif; ?>:</span><br>
				<?php foreach ($publishedBySet as $setID => $setButtons): ?>
					<a class="scheduleTsumego" href="<?php echo SetTable::url($setID); ?>"><b><?php echo h($setButtons[0]->setTitle); ?> - <?php echo count($setButtons); ?> problems</b></a><br>
					<div class="problem-nav__buttons">
						<?php foreach ($setButtons as $tsumegoButton)
							$tsumegoButton->render(); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="quote-pick-wrapper">
			<img src="/img/new_startpage/<?php echo h($quote); ?>.png?v=4.3" class="quote-pick-1" alt="Quote of the Day" title="Quote of the Day">
			<img src="/img/new_startpage/<?php echo h($quote); ?>u.png?v=4.3" class="quote-pick-1" alt="User of the Day" title="User of the Day">
			<?php if ($userOfTheDay !== null): ?>
				<div class="user-pick-all user-pick-<?php echo h($quote); ?> text-center">
					<?php echo User::renderLink($userOfTheDay); ?>
				</div>
			<?php endif; ?>
		</div>

		<section>
			<p class="title4">What's new | <time datetime="2026-10-28" data-format="date">2026-10-28</time></p>
			<div class="new1">
				The site changed a lot in the last months. The biggest additions:
				<ul class="whats-new-list">
					<li><a href="/mistake-training">Mistake Training</a>: a problem you solved with a mistake comes back for review at growing intervals until you master it.</li>
					<li><a href="/challenges">Challenges</a>: pick a range of ranks, the time for a problem and how many problems, invite your friends, and everyone plays the same problems.</li>
					<li><a href="/messages">Messages</a>: write to other players; the menu shows how many messages you haven't read yet.</li>
					<li>Proposals and tags: when an admin accepts or rejects your proposal or tag, you get a message saying what happened and why.</li>
					<li><a href="/users/settings">Settings</a>: email, password, theme, sound, date format and playing preferences have their own tab. Your avatar can be a Go board drawn from your account, your Gravatar or Google picture, or a picture you upload.</li>
					<li><a href="/classes">Classes</a>: teachers invite their pupils, give them assignments and see who solved what.</li>
					<li>Speed: problems with long variations open several times faster, most of all on phones.</li>
				</ul>
				Every change, big or small, is listed on the <a href="/changelog">What's new</a> page.
			</div>
		</section>
	</div>
</div>

