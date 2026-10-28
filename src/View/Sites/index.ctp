<?php

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
			<div data-recent-achievements-root data-props="<?php echo h(json_encode(['initialAchievements' => $recentAchievements])); ?>"></div>
		</div>

		<p class="title4">Update 30.11.2025</p>
		<div class="new1">
			<p><b>Warning:</b></p>
			<p>
				If you have issues with problems always removing hearts on refresh, or similar, please try to clear the cookies this way:<br>
				<img src="/img/cookies-cleanup.png" alt="This is how you clean the cookies." class="img-bordered"><br>
				I made several changes which should make the new cookies behave properly, but I didn't find a way to remove the old ones, which are breaking stuff.
			</p>
			<p>There is a lot of work going on. More details can be found in the forum posts</p>
			<a href="/forums/viewtopic.php?t=83">Version 0.5 weekly overview</a><br>
			<a href="/forums/viewtopic.php?t=52">Version 0.4 weekly overview</a><br>
			<a href="/forums/viewtopic.php?t=43">Version 0.3 weekly overview</a><br>
			<a href="/forums/viewtopic.php?t=12">Version 0.2 weekly overview</a><br>
			<a href="/forums/viewtopic.php?t=11">Version 0.1 weekly overview</a>
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

		<div id="news-list-left" class="news-list" data-initial-visible="4">
			<?php echo $this->element('Home/updates', ['levelModeUrl' => $levelModeUrl]); ?>
			<div id="show-more-left" class="show-more text-center">
				<button type="button" class="show-more__button">Show more</button>
			</div>
		</div>
	</div>

	<div>
		<div class="new-tsumego-box new-tsumego-box--news">
			<?php if (!empty($publishedBySet)): ?>
				<span class="latest-additions-heading">Latest additions<?php if ($latestPublishDate !== null): ?> (<time datetime="<?php echo $latestPublishDate; ?>" data-format="month-day"><?php echo $latestPublishDate; ?></time>)<?php endif; ?>:</span><br>
				<?php foreach ($publishedBySet as $setID => $setButtons): ?>
					<a class="scheduleTsumego" href="<?php echo Set::url($setID); ?>"><b><?php echo h($setButtons[0]->setTitle); ?> - <?php echo count($setButtons); ?> problems</b></a><br>
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

		<div id="news-list-right" class="news-list" data-initial-visible="6">
			<?php echo $this->element('Home/collections'); ?>
			<div id="show-more-right" class="show-more text-center">
				<button type="button" class="show-more__button">Show more</button>
			</div>
		</div>
	</div>
</div>

<script>
	["left", "right"].forEach(function(side)
	{
		const articles = $("#news-list-" + side + " > .news-article");
		const showMore = $("#show-more-" + side);
		const initialVisible = Number($("#news-list-" + side).attr("data-initial-visible"));
		articles.slice(initialVisible).prop("hidden", true);
		showMore.toggle(articles.length > initialVisible);
		showMore.on("click", function()
		{
			const hiddenArticles = articles.filter("[hidden]");
			hiddenArticles.slice(0, 3).prop("hidden", false).hide().fadeIn(250);
			if (hiddenArticles.length <= 3)
				showMore.hide();
		});
	});
</script>
