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

/**
 * @var \App\View\AppView $this
 * @var float|null $bestAccuracy
 * @var float|null $bestTime
 * @var string $accuracy
 * @var float $avgTime
 * @var bool $isFav
 * @var string $lightDark
 * @var bool $canShare
 * @var int $partition
 * @var int $pdCounter
 * @var int $problemSolvedPercent
 * @var bool $refreshView
 * @var int $saNum
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

$noImage = false;
if($isFav) $noImage = true;
if($set->id == 11969 || $set->id == 29156 || $set->id == 31813 || $set->id == 33007
|| $set->id == 71790 || $set->id == 74761 || $set->id == 81578 || $set->id == 88156)
	$noImage = true;

?>
</script>
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
		<table border="0" width="100%">
		<tr>
			<td class="valign-top">
				<?php
				$saNum = 0;

if($set->image == 'sa-pretty.jpg') $saNum = 9;
elseif($set->image == 'sa-hunting.jpg') $saNum = 8;
elseif($set->image == 'sa-ghost.jpg') $saNum = 7;
elseif($set->image == 'sa-carnage.jpg') $saNum = 6;
elseif($set->image == 'sa-invisible.png') $saNum = 5;
elseif($set->image == 'sa-giant.jpg') $saNum = 4;
elseif($set->image == 'sa-resistance.jpg') $saNum = 3;
else $saNum = 11;

echo HtmlSanitizer::sanitize((string) ($set->description ?? ''));
?>
			</td>
				<?php
// an empty collection has no problem to start with, so its cover and Start lead nowhere
$startHref = $startingSetConnectionID === null ? '' : ' href="/' . $startingSetConnectionID . '"';
if (!$noImage && $tsumegoFilters->query == 'topics' && $set->image)
{
	if ($set->image[2] != '-')
		echo '<td width="195px" class="valign-top"><div align="center" class="set-view__cover">
							<a' . $startHref . '>
							<img height="252" width="182" src="/img/' . h($set->image) . '"
							alt="Tsumego Collection: ' . h($setTitle) . '" title="Tsumego Collection: ' . h($setTitle) . '">
							</a></div></td>';
	else
		echo '<td width="195px" class="set-view__img-bottom"><div align="center" class="set-view__cover">
							<a' . $startHref . '>
							<img height="252" width="182" src="/img/' . h($set->image) . '"
							alt="Tsumego Collection: ' . h($setTitle) . '" title="Tsumego Collection: ' . h($setTitle) . '" width="210">
							</a></div></td>';
}
elseif (!$noImage && $tsumegoFilters->query == 'difficulty')
	echo '<td width="195px" class="valign-top"><div class="set-view__cover set-view__cover--rank" align="center">
						<a' . $startHref . '>
						<span class="rank-icon rank-icon-large' . (str_contains((string) $set->id, '-') ? ' rank-icon-large--range' : '') . '">' . h((string) $set->id) . '</span>
						</a></div></td>';
else
	echo '<td width="195px" class="valign-top"><div align="center"></div></td>';
?>
		</tr>
		<tr>
			<td class="valign-top">
				<table width="100%">
					<tr>
						<td class="valign-top" width="50%">
						<div align="center">
						<br>
						<?php
echo '<b>' . count($tsumegoButtons) . ' Problems<br>';
?>
						</div>
						</td>
						<td class="valign-bottom" width="50%">
						<div align="center">
							<br>
							Difficulty: <?php
	echo '<b>' . Rating::getReadableRankFromRating($setRating) . '</b>';
if ($tsumegoFilters->query != 'topics')
	echo '<br><br>Solved: <b>' . $problemSolvedPercent . '%</b>';
?>
						</div>
						</td>
					</tr>
				</table>
			</td>
			<td class="valign-top">
				<div align="center">
				<br><br>
					<?php
					if ($startingSetConnectionID !== null)
						echo '<a class="btn set-view__start" href="/' . $startingSetConnectionID . '">Start</a>';
?>
				</div>
			</td>
		</tr>
		<tr>
		<?php if($tsumegoFilters->query == 'topics')
		{ ?>
			<td>
			<br>
			<div align="center">
				<?php if(Auth::isLoggedIn())
				{ ?>
					<?php
					echo '<table><tr><td><div class="setViewCompleted"><b>Completed: ' . $problemSolvedPercent . '%</b></div></td><td></td></tr></table>
					<table><tr><td><div class="setViewAccuracy"><b>Accuracy: ' . $accuracy . '%</b></div></td><td>';
					if($bestAccuracy !== null) echo '<font class="setViewAccuracy">Best completion: ' . $bestAccuracy . '%</font>';
					echo '</td></tr></table>
					<table><tr><td><div class="setViewTime"><b>Avg. time: ' . $avgTime . 's</b></div></td><td>';
					if($bestTime !== null && $bestTime != 60) echo '<font class="setViewTime">Best completion: ' . $bestTime . 's</font>';
					echo '</td></tr></table>';
				} ?>
			</div>

			</td>

			<td>
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

					echo '<font color="gray">XP reduced by ' . $pdCounterValue . '%. (' . $pdCounter . ' reset' . $plural . ' in the last month.)</font>';
				}
				if ($tsumegoFilters->collectionSize != ProgressDeletionTable::$PARTITION_SIZE)
					echo 'Reset is only possible when collection size is set to ' . ProgressDeletionTable::$PARTITION_SIZE;
				elseif ($tsumegoFilters->hasProblemFilters())
					echo 'Reset is only possible without rank and tag filters';
				elseif ($problemSolvedPercent < 50)
					echo '<br><font color="gray">You need to complete 50% to reset.</font>';
				else
					echo '<div id="msg1x"><a id="showx">Reset<img id="greyArrow1" src="/img/greyArrow1.png"></a></div><br>';
			}
			?>
			</td>
			</tr>
			<tr>
			<td>
			<?php if($scoring)
			{ ?>
			<div align="center">
			<br>
			<br>
			<a id="numbersButton" class="btn" onclick="d1();">Numbers</a>
			<a id="ratioButton" class="btn" onclick="d2();">Accuracy</a>
			<a id="timeButton" class="btn" onclick="d3();">Time</a>
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
			</td>
			<td>
			<?php
			if ($problemSolvedPercent >= 50 && $tsumegoFilters->collectionSize == ProgressDeletionTable::$PARTITION_SIZE && !$tsumegoFilters->hasProblemFilters())
			{
				echo '<div id="msg2x">';
				echo 'Type "reset" to remove all your progress on this collection.<br><br>';
				echo '<form action="/sets/resetProgress/' . $set->id . '/' . ($partition + 1) . '" method="post">';
				echo '<input type="text" name="reset-check" id="reset-textfield" placeholder="reset">';
				echo '<input type="submit" value="submit" id="reset-submit">';
				echo '</form>';
				echo '</div>';
			} ?>
			</td>
			</tr>
			<?php if (isset($canEdit) && $canEdit): ?>
				<tr>
					<td colspan="2" class="set-view__edit-row">
						<a class="btn btn--primary" href="/sets/edit/<?php echo (int) $set->id; ?>">Edit Set</a>
						<?php if (!empty($canShare)): ?>
							<form method="post" action="/sets/share/<?php echo (int) $set->id; ?>" class="set-view__share">
								<label class="set-view__share-toggle">
									<input type="hidden" name="shared" value="0">
									<input type="checkbox" name="shared" value="1"<?php echo $set->public == 1 ? ' checked' : ''; ?> onchange="this.form.submit()">
									Anyone with the link can open this collection
								</label>
								<noscript><button type="submit" class="btn">Save sharing</button></noscript>
								<?php if ($set->public == 1): ?>
									<span class="set-view__share-link-row">
										<input class="form-field__control set-view__share-link" type="text" id="SetShareLink" readonly value="<?php echo h(SetTable::url((int) $set->id, 1, true)); ?>" onclick="this.select()">
										<button type="button" class="btn set-view__share-copy" id="SetShareCopy" aria-label="Copy the link to this collection">Copy</button>
									</span>
									<span class="set-view__share-status" id="SetShareStatus" role="status" aria-live="polite"></span>
								<?php endif; ?>
							</form>
							<?php if ($set->public == 1): ?>
								<script>
									(function () {
										const button = document.getElementById('SetShareCopy');
										const field = document.getElementById('SetShareLink');
										const status = document.getElementById('SetShareStatus');
										if (!button || !field)
											return;
										button.addEventListener('click', () => {
											const copied = navigator.clipboard && window.isSecureContext
												? navigator.clipboard.writeText(field.value)
												: Promise.reject(new Error('no clipboard'));
											copied.then(() => {
												button.textContent = 'Copied';
												status.textContent = 'The link is in your clipboard.';
												setTimeout(() => {
													button.textContent = 'Copy';
													status.textContent = '';
												}, 2000);
											}).catch(() => {
												// The browser refused, so leave the address selected for a manual copy.
												field.select();
												status.textContent = 'Press Ctrl+C to copy the address.';
											});
										});
									}());
								</script>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			</tr>
		<?php } ?>

		</table>
		</div>
		<?php if(!$isFav) echo '<br><br><br><br><br>'; ?>
		<br><br>
	</div>
	<div>
		<p class="title4">Problems</p>
		<div class="showFilters">
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
		echo '<div class="dropdown__tile dropdown__tile--green dropdown__tile--context" id="active-set-element' . $i . '" onclick="removeActiveTopic(' . $i . ')">' . h($setName) . '</div>';
	}
if ($tsumegoFilters->query != 'difficulty')
	foreach ($rankLabels as $i => $rankLabel)
	{
		$hasActiveTiles = true;
		$isRange = str_contains($rankLabel, '-');
		echo '<div class="dropdown__tile ' . ($isRange ? 'dropdown__tile--purple' : 'dropdown__tile--difficulty')
			. ' dropdown__tile--context"'
			. ($isRange ? '' : ' style="--rank-color:var(--rank-' . h($rankLabel) . ')"')
			. ' id="active-rank-element' . $i . '" onclick="removeActiveDifficulty(' . $i . ')">' . h($rankLabel) . '</div>';
	}
if ($tsumegoFilters->query != 'tags')
	foreach ($tsumegoFilters->tags as $i => $tag)
	{
		$hasActiveTiles = true;
		$color = (int) ($tsumegoFilters->tagColors[$tag] ?? 24);
		echo '<div class="dropdown__tile dropdown__tile--tag dropdown__tile--context" style="--tag-color:var(--tag-palette-' . $color . ')" id="active-tag-element' . $i . '" onclick="removeActiveTag(' . $i . ')">' . h($tag) . '</div>';
	}
if ($hasActiveTiles)
	echo '<a class="dropdown__tile dropdown__tile--muted" id="unselect-active-tiles" href="">clear</a><div class="clearfix"></div>';
?>
			</div>
		</div>
		<div class="set-view-main">
		<?php
	if($set->id != 58 && $set->id != 62 && $set->id != 91 && $set->id != 72 && $set->id != 73 && $set->id != 74
	&& $set->id != 75 && $set->id != 76 && $set->id != 77 && $set->id != 78 && $set->id != 79 && $set->id != 80
	&& $set->id != 51 && $set->id != 56 && $set->id != 57 && $set->id != 119
	&& $set->id != 126 && $set->id != 129 && $set->id != 134 && $set->id != 135)
		$beta2 = false;
	else $beta2 = true;
if(Auth::getUserID() == 72)
	$beta2 = false;

if(!$beta2)
	foreach ($tsumegoButtons as $tsumegoButton)
		$tsumegoButton->render();

$totalPages = $tsumegoButtons->getPageCount($tsumegoFilters->collectionSize);
if ($totalPages > 1):
	$currentPage = $partition + 1;
	?>
	<div class="pagination">
<?php for ($page = 1; $page <= $totalPages; $page++): ?>
<?php if ($page === $currentPage): ?>
		<strong><?php echo $page; ?></strong>
<?php else: ?>
			<a href="<?php echo SetTable::url($set->id, $page); ?>"><?php echo $page; ?></a>
<?php endif; ?>
<?php endfor; ?>
	</div>
<?php endif; ?>
	</div>
	</div>
	</div>
	<div class="clearfix"></div>

	<script>
	const activeSets = <?php echo json_encode($tsumegoFilters->query != 'topics' ? array_values($tsumegoFilters->sets) : [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
	const activeRanks = <?php echo json_encode($rankLabels, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
	const activeTags = <?php echo json_encode($tsumegoFilters->query != 'tags' ? array_values($tsumegoFilters->tags) : [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;

	$(".active-tiles-container").on("click", "#unselect-active-tiles", function(e){
		e.preventDefault();
		setCookie("filtered_sets", "clear");
		setCookie("filtered_ranks", "clear");
		setCookie("filtered_tags", "clear");
		window.location.reload();
	});

	function removeActiveTopic(index){
		activeSets.splice(index, 1);
		setCookie("filtered_sets", activeSets.length == 0 ? "clear" : activeSets.join("@"));
		window.location.reload();
	}

	function removeActiveDifficulty(index){
		activeRanks.splice(index, 1);
		setCookie("filtered_ranks", activeRanks.length == 0 ? "clear" : activeRanks.join("@"));
		window.location.reload();
	}

	function removeActiveTag(index){
		activeTags.splice(index, 1);
		setCookie("filtered_tags", activeTags.length == 0 ? "clear" : activeTags.join("@"));
		window.location.reload();
	}

		var msg2selected = false;
		var msgFilterSelected = false;

		$("#msg2x").hide();
		$("#ratioInfo").hide();
		$("#timeInfo").hide();
		$("#numbersButton").addClass("btn--inactive");
		$("#ratioButton").removeClass("btn--inactive");
		$("#timeButton").removeClass("btn--inactive");

		$("#showx").click(function(){
			if(!msg2selected){
				$("#msg2x").fadeIn(250);
				document.getElementById("greyArrow1").src = "/img/greyArrow2.png";
			}else{
				$("#msg2x").fadeOut(250);
				document.getElementById("greyArrow1").src = "/img/greyArrow1.png";
			}
			msg2selected = !msg2selected;
		});

		$("#showFilters").click(function(){
			if(!msgFilterSelected){
				$("#msgFilters").fadeIn(250);
				document.getElementById("greyArrowFilter").src = "/img/greyArrow2.png";
			}else{
				$("#msgFilters").fadeOut(250);
				document.getElementById("greyArrowFilter").src = "/img/greyArrow1.png";
			}
			msgFilterSelected = !msgFilterSelected;
		});

		function d1(){
			$("#numbersButton").addClass("btn--inactive");
			$("#ratioButton").removeClass("btn--inactive");
			$("#timeButton").removeClass("btn--inactive");
			$("#numbersInfo").fadeIn(250);
			$("#ratioInfo").hide();
			$("#timeInfo").hide();
			$(".problem-nav__number").fadeIn(200);
			$(".problem-nav__ratio").hide();
			$(".problem-nav__time").hide();
			$(".setViewCompleted").css("border", "1px solid var(--set-accent-green)");
			$(".setViewAccuracy").css("border", "none");
			$(".setViewTime").css("border", "none");
		}
		function d2(){
			$("#numbersButton").removeClass("btn--inactive");
			$("#ratioButton").addClass("btn--inactive");
			$("#timeButton").removeClass("btn--inactive");
			$("#numbersInfo").hide();
			$("#ratioInfo").fadeIn(250);
			$("#timeInfo").hide();
			$(".problem-nav__number").hide();
			$(".problem-nav__ratio").fadeIn(200);
			$(".problem-nav__time").hide();
			$(".setViewCompleted").css("border", "none");
			$(".setViewAccuracy").css("border", "1px solid var(--set-accent-purple)");
			$(".setViewTime").css("border", "none");
		}
		function d3(){
			$("#numbersButton").removeClass("btn--inactive");
			$("#ratioButton").removeClass("btn--inactive");
			$("#timeButton").addClass("btn--inactive");
			$("#numbersInfo").hide();
			$("#ratioInfo").hide();
			$("#timeInfo").fadeIn(250);
			$(".problem-nav__number").hide();
			$(".problem-nav__ratio").hide();
			$(".problem-nav__time").fadeIn(200);
			$(".setViewCompleted").css("border", "none");
			$(".setViewAccuracy").css("border", "none");
			$(".setViewTime").css("border", "1px solid var(--set-accent-orange)");
		}

		<?php
if($refreshView)
	echo 'window.location.href = "' . SetTable::url($set->id) . '";';
?>
	</script>
	<style>
	#msgFilters{
		display:none;
		margin:0 4px 8px
	}
	.showFilters{
		display:inline-block;
	}
	#showFilters, .showFilters{
		<?php
// Show Filters button whenever any filter type has active selections
$displayNone = empty($tsumegoFilters->sets) && empty($tsumegoFilters->ranks) && empty($tsumegoFilters->tags);
if ($displayNone)
	echo 'display:none;';
?>
		margin:8px 4px;
	}

	</style>
