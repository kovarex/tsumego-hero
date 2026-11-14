<?php

use App\Model\Table\SetTable;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\HtmlSanitizer;
use App\Utility\Rating;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var float|null $bestAccuracy
 * @var float|null $bestTime
 * @var string $accuracy
 * @var float $avgTime
 * @var bool $canShare
 * @var int $partition
 * @var int $pdCounter
 * @var int $problemSolvedPercent
 * @var float $runSolvedPercent The share solved since the latest reset of the part: a problem the reset put back in Review counts once solved again
 * @var bool $scoring
 * @var \App\Model\Entity\Set $set
 * @var array|null $setAuthor
 * @var int $setRating
 * @var string $setTitle
 * @var int|null $startingSetConnectionID The problem the collection starts with, null when it has none
 * @var TsumegoButton $tsumegoButton
 * @var TsumegoButtons $tsumegoButtons
 * @var TsumegoFilters $tsumegoFilters
 */

?>
	<div class="split">
		<div>
		<?php
		$ownerNote = $setAuthor && (int) $setAuthor['id'] !== Auth::getUserID()
			? ' <span class="set-view__owner">by ' . UserTable::renderLink($setAuthor) . '</span>'
			: '';
		echo '<p class="title4 set-view-title">' . h($setTitle) . $ownerNote . '</p>';
		if (!empty($set->deleted))
			echo $this->element('trash_notice', [
				'what' => 'collection',
				'deleted' => $set->deleted->format('Y-m-d H:i:s'),
				'restoreAction' => '/trash/restore-set/' . (int) $set->id]);
		?>
		<div class="new1">
		<?php //On a phone the description is cut to its beginning and More opens it with the cover; the stats and the views of the scores open from their button (sets.css, SetsViewPage.js), so the problems are in sight at once ?>
		<div class="set-view__info">
		<div class="set-view__row set-view__intro">
			<div class="set-view__main">
				<div class="set-view__description"><?php
echo HtmlSanitizer::sanitize((string) ($set->description ?? ''));
?></div>
				<button type="button" class="set-view__expand" aria-expanded="false">More</button>
			</div>
				<?php
// an empty collection has no problem to start with, so its cover and Start lead nowhere
$startHref = $startingSetConnectionID === null ? '' : ' href="/' . $startingSetConnectionID . '"';
// a cover whose name has a dash at its third letter sits at the bottom of its cell
if ($tsumegoFilters->query == 'topics' && $set->image)
	echo '<div class="set-view__cover-cell' . ($set->image[2] != '-' ? '' : ' set-view__img-bottom') . '"><div class="legacy-center set-view__cover">
						<a' . $startHref . '>
						<img height="252" width="182" src="/img/' . h($set->image) . '"
						alt="Tsumego Collection: ' . h($setTitle) . '" title="Tsumego Collection: ' . h($setTitle) . '">
						</a></div></div>';
elseif ($tsumegoFilters->query == 'difficulty')
	echo '<div class="set-view__cover-cell"><div class="legacy-center set-view__cover set-view__cover--rank">
						<a' . $startHref . '>
						<span class="rank-icon rank-icon-large' . (str_contains((string) $set->id, '-') ? ' rank-icon-large--range' : '') . '">' . h((string) $set->id) . '</span>
						</a></div></div>';
else
	echo '<div class="set-view__cover-cell"></div>';
?>
		</div>
		<div class="set-view__row set-view__summary">
			<div class="set-view__counts">
						<div class="set-view__half legacy-center">
						<br>
						<?php
echo '<b>' . count($tsumegoButtons) . ' Problems</b><br>';
?>
						</div>
						<div class="set-view__half set-view__half--bottom legacy-center">
							<br>
							Difficulty: <?php
	echo '<b>' . Rating::getReadableRankFromRating($setRating) . '</b>';
if ($tsumegoFilters->query != 'topics')
	echo '<br><br>Solved: <b>' . $problemSolvedPercent . '%</b>';
?>
						</div>
			</div>
				<div class="legacy-center">
				<br><br>
					<?php
					if ($startingSetConnectionID !== null)
						echo '<a class="btn btn--primary set-view__start" href="/' . $startingSetConnectionID . '">Start</a>';
?>
				</div>
		</div>
		<?php if ($tsumegoFilters->query == 'topics'): ?>
		<div class="set-view__stats-toggle-row"><button type="button" class="btn btn--ghost set-view__stats-toggle" aria-expanded="false">Stats &amp; display</button></div>
		<?php //The scores, each on one line with the best of a completed run beside it ?>
		<div class="set-view__row set-view__stats">
			<div>
			<br>
			<div class="legacy-center">
				<?php if(Auth::isLoggedIn())
				{ ?>
					<?php
					echo '<div class="setViewCompleted"><b>Completed: ' . $problemSolvedPercent . '%</b></div>';
					echo '<div class="setViewAccuracy"><b>Accuracy: ' . $accuracy . '%</b>' . ($bestAccuracy !== null ? ' &middot; Best: ' . $bestAccuracy . '%' : '') . '</div>';
					echo '<div class="setViewTime"><b>Avg. time: ' . $avgTime . 's</b>' . ($bestTime !== null && $bestTime != 60 ? ' &middot; Best: ' . $bestTime . 's' : '') . '</div>';
				} ?>
			</div>
			<?php //The reset of the progress under the scores, its form opening right under its link ?>
			<div class="legacy-center set-view__reset">
			<?php
			if (Auth::isLoggedIn())
			{
				if ($pdCounter > 0)
				{
					$plural = 's';
					if ($pdCounter == 1)
					{
						$pdCounterValue = 50;
						$plural = '';
					}
					elseif ($pdCounter == 2)
						$pdCounterValue = 80;
					elseif ($pdCounter == 3)
						$pdCounterValue = 90;
					else
						$pdCounterValue = 99;

					echo '<span class="text-muted">XP reduced by ' . $pdCounterValue . '%. (' . $pdCounter . ' reset' . $plural . ' in the last month.)</span>';
				}
				if ($tsumegoFilters->collectionSize != ProgressDeletionTable::$PARTITION_SIZE)
					echo 'Reset is only possible when collection size is set to ' . ProgressDeletionTable::$PARTITION_SIZE;
				elseif ($tsumegoFilters->hasProblemFilters())
					echo 'Reset is only possible without rank and tag filters';
				elseif ($runSolvedPercent < 50)
					echo '<br><span class="text-muted">You need to complete 50% to reset.</span>';
				else
				{
					echo '<div id="msg1x"><a id="showx">Reset<img id="greyArrow1" src="/img/greyArrow1.png"></a></div>';
					echo '<div id="msg2x" class="set-view__reset-form">';
					echo 'Type "reset" to start this part over: the problems you solved go back to Review, the others are cleared. Solving a Review problem again makes it Mastered.<br><br>';
					echo '<form action="/sets/resetProgress/' . $set->id . '/' . ($partition + 1) . '" method="post">';
					echo '<input type="text" name="reset-check" id="reset-textfield" placeholder="reset">';
					echo '<input type="submit" class="btn btn--danger" value="Reset" id="reset-submit">';
					echo '</form>';
					echo '</div>';
				}
			}
			?>
			</div>
			</div>
			</div>
			<div class="set-view__row set-view__stats">
			<div>
			<?php if($scoring)
			{ ?>
			<div class="legacy-center">
			<br>
			<br>
			<a id="numbersButton" class="btn btn--pill" data-score-view="numbers">Numbers</a>
			<a id="ratioButton" class="btn btn--pill" data-score-view="ratio">Accuracy</a>
			<a id="timeButton" class="btn btn--pill" data-score-view="time">Time</a>
			<br>
			<br>
			<div id="numbersInfo">
				The problem numbers are displayed.
			</div>
			<div id="ratioInfo">
				The solved and failed (s/f) attempts are displayed.<br><span class="hint">Outdated and missing entries (-) are counted as fail.</span>
			</div>
			<div id="timeInfo">
				The time (in seconds) for solving is displayed.<br><span class="hint">Outdated and missing entries (-) are counted as 60 seconds.</span>
			</div>
			</div>
			<br>
			<?php } ?>
			</div>
			</div>
			<?php if (isset($canEdit) && $canEdit): ?>
					<div class="set-view__edit-row">
						<a class="btn" href="/sets/edit/<?php echo (int) $set->id; ?>">Edit Set</a>
						<?php if (!empty($canShare)): ?>
							<form method="post" action="/sets/share/<?php echo (int) $set->id; ?>" class="set-view__share">
								<label class="set-view__share-toggle">
									<input type="hidden" name="shared" value="0">
									<input type="checkbox" name="shared" value="1"<?php echo $set->public == 1 ? ' checked' : ''; ?>>
									Anyone with the link can open this collection
								</label>
								<noscript><button type="submit" class="btn btn--primary">Save sharing</button></noscript>
								<?php if ($set->public == 1): ?>
									<span class="set-view__share-link-row">
										<input class="form-field__control set-view__share-link" type="text" id="SetShareLink" readonly value="<?php echo h(SetTable::url((int) $set->id, 1, true)); ?>">
										<button type="button" class="btn set-view__share-copy" id="SetShareCopy" aria-label="Copy the link to this collection">Copy</button>
									</span>
									<span class="set-view__share-status" id="SetShareStatus" role="status" aria-live="polite"></span>
								<?php endif; ?>
							</form>
						<?php endif; ?>
					</div>
			<?php endif; ?>
		<?php endif; ?>

		</div>
		</div>
		<div class="set-view__gap"><br><br><br><br><br><br><br></div>
	</div>
	<div class="set-view-problems">
		<p class="title4">Problems</p>
		<?php // the Filters link is offered whenever any filter type has active selections ?>
		<div class="showFilters<?php echo empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags) ? ' showFilters--hidden' : ''; ?>">
			<a id="showFilters" class="selectable-text">Filters<img id="greyArrowFilter" src="/img/greyArrow1.png"></a>
		</div>
		<label class="preview-toggle" title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">🔍 Preview</label>
		<div id="msgFilters">
			<div class="active-tiles-container tiles-view">
<?php
$hasActiveTiles = false;
$rankLabels = $tsumegoFilters->query != 'difficulty' ? $tsumegoFilters->rankFilterLabels() : [];
if ($tsumegoFilters->query != 'topics')
	foreach ($tsumegoFilters->sets as $i => $setName)
	{
		$hasActiveTiles = true;
		echo '<div class="dropdown__tile dropdown__tile--green dropdown__tile--context" id="active-set-element' . $i . '" data-filter="set" data-index="' . $i . '">' . h($setName) . '</div>';
	}
if ($tsumegoFilters->query != 'difficulty')
	foreach ($rankLabels as $i => $rankLabel)
	{
		$hasActiveTiles = true;
		$isRange = str_contains($rankLabel, '-');
		echo '<div class="dropdown__tile ' . ($isRange ? 'dropdown__tile--purple' : 'dropdown__tile--difficulty')
			. ' dropdown__tile--context"'
			. ($isRange ? '' : ' style="--rank-color:var(--rank-' . h($rankLabel) . ')"')
			. ' id="active-rank-element' . $i . '" data-filter="rank" data-index="' . $i . '">' . h($rankLabel) . '</div>';
	}
if ($tsumegoFilters->query != 'tags')
	foreach ($tsumegoFilters->tags as $i => $tag)
	{
		$hasActiveTiles = true;
		$color = (int) ($tsumegoFilters->tagColors[$tag] ?? 24);
		echo '<div class="dropdown__tile dropdown__tile--tag dropdown__tile--context" style="--tag-color:var(--tag-palette-' . $color . ')" id="active-tag-element' . $i . '" data-filter="tag" data-index="' . $i . '">' . h($tag) . '</div>';
	}
if ($hasActiveTiles)
	echo '<a class="dropdown__tile dropdown__tile--muted" id="unselect-active-tiles" href="">clear</a><div class="clearfix"></div>';
?>
			</div>
		</div>
		<div class="set-view-main">
		<?php
foreach ($tsumegoButtons as $tsumegoButton)
	$tsumegoButton->render();

$totalPages = $tsumegoButtons->getPageCount($tsumegoFilters->collectionSize);
if ($totalPages > 1):
	$currentPage = $partition + 1;
	?>
	<div class="pagination">
<?php for ($page = 1; $page <= $totalPages; $page++): ?>
<?php if ($page === $currentPage): ?>
		<span class="pagination__link pagination__link--active" aria-current="page"><?php echo $page; ?></span>
<?php else: ?>
		<a class="pagination__link" href="<?php echo SetTable::url($set->id, $page); ?>"><?php echo $page; ?></a>
<?php endif; ?>
<?php endfor; ?>
	</div>
<?php endif; ?>
	</div>
	</div>
	</div>
	<div class="clearfix"></div>

	<?php
	//All webroot/js/SetsViewPage.js needs to know about this request: the active filters of the other kinds
	$setsViewPage = [
		'activeSets' => $tsumegoFilters->query != 'topics' ? array_values($tsumegoFilters->sets) : [],
		'activeRanks' => $rankLabels,
		'activeTags' => $tsumegoFilters->query != 'tags' ? array_values($tsumegoFilters->tags) : [],
	];
	?>
	<script>
		const setsViewPage = <?php echo json_encode($setsViewPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
	</script>
	<?php echo ViteManifest::legacyScript('sets-view'); ?>
