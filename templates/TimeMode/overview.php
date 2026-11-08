<?php

use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var bool $hasFinishedSesssion
 * @var string $lastTimeModeCategoryID
 * @var array $solvedMap
 * @var array<\App\Model\Entity\TimeModeCategory> $timeModeCategories
 * @var array $timeModeRanks
 * @var int|null $challengeInProgressID The challenge whose run the player is in the middle of
 */

//The best rank the player passed in each category, which the account bar shows
$bestSolvedRankByCategory = [];
foreach ($timeModeCategories as $timeModeCategory)
	if ($bestSolvedRankID = $solvedMap[$timeModeCategory->id]['best-solved-rank'] ?? null)
		$bestSolvedRankByCategory[$timeModeCategory->id] = $solvedMap[$timeModeCategory->id][$bestSolvedRankID];
?>
<div class="legacy-center">
	<h2>Time Mode Select</h2>
</div>
<br>
<div class="legacy-center">
	<a class="btn btn--inactive" href="#">Select</a>
	<a class="btn<?php echo $hasFinishedSesssion ? '' : ' btn--inactive'; ?>" href="<?php echo $hasFinishedSesssion ? '/timeMode/result' : '#'; ?>">Results</a>
	<a class="btn" href="/challenges">Challenge a friend</a>
</div>
<?php if ($challengeInProgressID): ?>
<p class="hint legacy-center" id="challenge-in-progress">
	You are in the middle of <a href="/challenges/<?= $challengeInProgressID ?>">Challenge #<?= $challengeInProgressID ?></a>:
	<a href="/timeMode/play">continue it</a>. Starting a time mode run ends it, and the problems you didn't play get no points.
</p>
<?php endif; ?>
<br><br>
<div class="legacy-center">
	<?php foreach ($timeModeCategories as $timeModeCategory): ?>
	<img id="timeMode<?php echo $timeModeCategory->id; ?>" src="/img/timeMode<?php echo $timeModeCategory->id; ?>2.png" width="200">
	<?php endforeach; ?>
</div>
<br><br>
<?php foreach ($timeModeCategories as $timeModeCategory): ?>
<div id="time-mode<?php echo $timeModeCategory->id; ?>">
	<?php
	$unlocked = true; // the first rank is always unlocked, then the one after each passed one
	foreach ($timeModeRanks as $timeModeRank):
		$rank = $timeModeRank['name'];
		$rankID = $timeModeRank['id'];
	?>
	<div class="imageContainer1" id="rank-selector-<?php echo $timeModeCategory->id; ?>-<?php echo $rankID; ?>">
		<?php if ($unlocked): ?>
		<a class="time-mode__rank-link" href="/timeMode/start?categoryID=<?php echo $timeModeCategory->id; ?>&rankID=<?php echo $rankID; ?>">
			<img src="/img/rankButton<?php echo $rank; ?>.png" data-hover-src="/img/rankButton<?php echo $rank; ?>hover.png">
			<div class="imageContainerText2"> <img class="timeModeIcons" src="/img/timeModeStored.png"><?php echo $timeModeRank['tsumego_count']; ?></div>
		</a>
		<?php else: ?>
		<img src="/img/rankButton<?php echo $rank; ?>inactive.png">
		<div class="imageContainerText2"> <img class="timeModeIcons" src="/img/timeModeStored.png"><?php echo $timeModeRank['tsumego_count']; ?></div>
		<?php endif; ?>
	</div>
	<?php
		$unlocked = isset($solvedMap[$timeModeCategory->id][$rankID]);
	endforeach;
	?>
</div>
<?php endforeach; ?>
<?php echo ViteManifest::legacyScript('time-mode'); ?>
<script>
	initTimeModeOverview(<?php echo json_encode([
		'categoryIDs' => array_map(fn($timeModeCategory) => $timeModeCategory->id, $timeModeCategories),
		'lastCategoryID' => (int) $lastTimeModeCategoryID,
		'bestSolvedRankByCategory' => (object) $bestSolvedRankByCategory,
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
</script>
