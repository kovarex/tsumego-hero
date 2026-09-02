<?php

/**
 * @var View $this
 * @var array $acA
 * @var array $acS
 * @var string $accuracy
 * @var float $avgTime
 * @var bool $isFav
 * @var string $lightDark
 * @var int $partition
 * @var int $pdCounter
 * @var int $problemSolvedPercent
 * @var bool $refreshView
 * @var int $saNum
 * @var bool $scoring
 * @var array $set
 * @var int $setRating
 * @var string $setTitle
 * @var bool $startingSetConnectionID
 * @var TsumegoButton $tsumegoButton
 * @var TsumegoButtons $tsumegoButtons
 * @var TsumegoFilters $tsumegoFilters
 */

$noImage = false;
if($isFav) $noImage = true;
if($set['Set']['id'] == 11969 || $set['Set']['id'] == 29156 || $set['Set']['id'] == 31813 || $set['Set']['id'] == 33007
|| $set['Set']['id'] == 71790 || $set['Set']['id'] == 74761 || $set['Set']['id'] == 81578 || $set['Set']['id'] == 88156)
	$noImage = true;

?>
</script>
	<div class="split">
		<div>
		<?php echo '<p class="title4 set-view-title">' . h($setTitle) . '</p>';?>
		<div class="new1">
		<table border="0" width="100%">
		<tr>
			<td class="valign-top">
				<?php
				$saNum = 0;

if($set['Set']['image'] == 'sa-pretty.jpg') $saNum = 9;
elseif($set['Set']['image'] == 'sa-hunting.jpg') $saNum = 8;
elseif($set['Set']['image'] == 'sa-ghost.jpg') $saNum = 7;
elseif($set['Set']['image'] == 'sa-carnage.jpg') $saNum = 6;
elseif($set['Set']['image'] == 'sa-invisible.png') $saNum = 5;
elseif($set['Set']['image'] == 'sa-giant.jpg') $saNum = 4;
elseif($set['Set']['image'] == 'sa-resistance.jpg') $saNum = 3;
else $saNum = 11;

echo HtmlSanitizer::sanitize((string) ($set['Set']['description'] ?? ''));
?>
			</td>
				<?php
if (!$noImage && $tsumegoFilters->query == 'topics' && $set['Set']['image'])
{
	if ($set['Set']['image'][2] != '-')
		echo '<td width="195px" class="valign-top"><div align="center" class="set-image-zoom">
							<a href="/' . $startingSetConnectionID . '">
							<img height="252" width="182" src="/img/' . h($set['Set']['image']) . '"
							alt="Tsumego Collection: ' . h($setTitle) . '" title="Tsumego Collection: ' . h($setTitle) . '">
							</a></div></td>';
	else
		echo '<td width="195px" class="set-view__img-bottom"><div align="center" class="set-image-zoom">
							<a href="/' . $startingSetConnectionID . '">
							<img height="252" width="182" src="/img/' . h($set['Set']['image']) . '"
							alt="Tsumego Collection: ' . h($setTitle) . '" title="Tsumego Collection: ' . h($setTitle) . '" width="210">
							</a></div></td>';
}
elseif (!$noImage && $tsumegoFilters->query == 'difficulty')
	echo '<td width="195px" class="valign-top"><div class="set-image-zoom set-image-zoom--rank" align="center">
						<a href="/' . $startingSetConnectionID . '">
						<span class="rank-icon rank-icon-large">' . h($set['Set']['id']) . '</span>
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
			if(isset($set['Set']['solved']))
			{
				$solvedColor = '#a7a7a7';
				if($set['Set']['solved'] > 0)
					$solvedColor = '#247bb5';
				if($set['Set']['solved'] == 100)
					$solvedColor = '#3ecf78';
			}
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
					if ($set['Set']['solved'] > 100)
						$set['Set']['solved'] = 100;
					echo '<table><tr><td><div class="setViewCompleted"><b>Completed: ' . $problemSolvedPercent . '%</b></div></td><td></td></tr></table>
					<table><tr><td><div class="setViewAccuracy"><b>Accuracy: ' . $accuracy . '%</b></div></td><td>';
					if($acA != null) echo '<font class="setViewAccuracy">Best completion: ' . $acA['AchievementCondition']['value'] . '%</font>';
					echo '</td></tr></table>
					<table><tr><td><div class="setViewTime"><b>Avg. time: ' . $avgTime . 's</b></div></td><td>';
					if($acS != null && $acS['AchievementCondition']['value'] != 60) echo '<font class="setViewTime">Best completion: ' . $acS['AchievementCondition']['value'] . 's</font>';
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

					echo '<font color="gray">XP reduced by ' . $pdCounterValue . '%. (' . $pdCounter . ' reset' . $plural . ' this month.)</font>';
				}
				if ($tsumegoFilters->collectionSize != 200)
					echo 'Reset is only possible when collection size is set to 200';
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
			if ($problemSolvedPercent >= 50)
			{
				echo '<div id="msg2x">';
				echo 'Type "reset" to remove all your progress on this collection.<br><br>';
				echo '<form action="/sets/resetProgress/' . $set['Set']['id'] . '/' . ($partition + 1) . '" method="post">';
				echo '<input type="text" name="reset-check" id="reset-textfield" placeholder="reset">';
				echo '<input type="submit" value="submit" id="reset-submit">';
				echo '</form>';
				echo '</div>';
			} ?>
			</td>
			</tr>
			<?php
			if (isset($canEdit) && $canEdit)
				echo '<tr><td colspan="2" class="set-view__edit-row">
					<a class="btn btn--primary" href="/sets/edit/' . $set['Set']['id'] . '">Edit Set</a>
					</td></tr>';
			?>
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
if ($tsumegoFilters->query != 'topics')
{
	foreach ($tsumegoFilters->sets as $i => $setName)
	{
		$hasActiveTiles = true;
		echo '<div class="dropdown__tile dropdown__tile--green dropdown__tile--context" id="active-set-element' . $i . '" onclick="removeActiveTopic(' . $i . ')">' . h($setName) . '</div>';
	}
}
if ($tsumegoFilters->query != 'difficulty')
{
	foreach ($tsumegoFilters->ranks as $i => $rank)
	{
		$hasActiveTiles = true;
		echo '<div class="dropdown__tile dropdown__tile--difficulty dropdown__tile--context" style="--rank-color:var(--rank-' . h($rank) . ')" id="active-rank-element' . $i . '" onclick="removeActiveDifficulty(' . $i . ')">' . h($rank) . '</div>';
	}
}
if ($tsumegoFilters->query != 'tags')
{
	foreach ($tsumegoFilters->tags as $i => $tag)
	{
		$hasActiveTiles = true;
		$color = (int) ($tsumegoFilters->tagColors[$tag] ?? 24);
		echo '<div class="dropdown__tile dropdown__tile--tag dropdown__tile--context" style="--tag-color:var(--tag-palette-' . $color . ')" id="active-tag-element' . $i . '" onclick="removeActiveTag(' . $i . ')">' . h($tag) . '</div>';
	}
}
if ($hasActiveTiles)
	echo '<a class="dropdown__tile dropdown__tile--muted" id="unselect-active-tiles" href="">clear</a><div class="clearfix"></div>';
?>
			</div>
		</div>
		<div class="set-view-main">
		<?php
	if($set['Set']['id'] != 58 && $set['Set']['id'] != 62 && $set['Set']['id'] != 91 && $set['Set']['id'] != 72 && $set['Set']['id'] != 73 && $set['Set']['id'] != 74
	&& $set['Set']['id'] != 75 && $set['Set']['id'] != 76 && $set['Set']['id'] != 77 && $set['Set']['id'] != 78 && $set['Set']['id'] != 79 && $set['Set']['id'] != 80
	&& $set['Set']['id'] != 51 && $set['Set']['id'] != 56 && $set['Set']['id'] != 57 && $set['Set']['id'] != 119
	&& $set['Set']['id'] != 119 && $set['Set']['id'] != 126 && $set['Set']['id'] != 129 && $set['Set']['id'] != 134 && $set['Set']['id'] != 135)
		$beta2 = false;
	else $beta2 = true;
if(Auth::getUserID() == 72)
	$beta2 = false;

if(!$beta2)
	foreach ($tsumegoButtons as $tsumegoButton)
		$tsumegoButton->render();

$totalPages = (int) ceil($tsumegoButtons->highestTsumegoOrder / $tsumegoFilters->collectionSize);
if ($totalPages > 1):
	$currentPage = $partition + 1;
	?>
	<div class="pagination">
<?php for ($p = 1; $p <= $totalPages; $p++): ?>
<?php if ($p === $currentPage): ?>
		<strong><?php echo $p; ?></strong>
<?php else: ?>
		<a href="/sets/view/<?php echo $set['Set']['id'] . ($p > 1 ? '/' . $p : ''); ?>"><?php echo $p; ?></a>
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
	const activeRanks = <?php echo json_encode($tsumegoFilters->query != 'difficulty' ? array_values($tsumegoFilters->ranks) : [], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;
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
	echo 'window.location.href = "/sets/view/' . $set['Set']['id'] . '";';
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
