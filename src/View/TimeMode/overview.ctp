<div align="center">
	<h2>Time Mode Select</h2>
</div>
<br>
<div align="center">
	<a class="new-button-inactive" href="#">Select</a>
	<?php echo '<a class="new-button'.($hasFinishedSesssion ? '' : '-inactive').'" href="'.($hasFinishedSesssion ? '/timeMode/result' : '#').'">Results</a>'; ?>
</div>
<br><br>
<div align="center">
	<?php
	foreach ($timeModeCategories as $timeModeCategory)
	{
		$categoryID = $timeModeCategory['TimeModeCategory']['id'];
		echo '<img id="timeMode'.$categoryID.'"'.
			' src="/img/timeMode'.$categoryID.'2.png" '.
			' width="200"'.
			' onclick="setTimeModeCategory('.$categoryID.');"'.
			' onmouseover="this.src=\'/img/timeMode'.$categoryID.'hover2.png\';"'.
			' onmouseout="this.src=\'/img/timeMode'.$categoryID.'\' + (timeModeCategoryID == '.$categoryID.' ? \'\' : \'inactive\') + \'2.png\';"/>';
	}
	?>
	</div>
<br><br>

<?php
foreach ($timeModeCategories as $timeModeCategory)
{
	$categoryID = $timeModeCategory['TimeModeCategory']['id'];
	echo '<div id="time-mode'.$categoryID.'">';
	$unlocked = true; // first is always unlocked
	foreach ($timeModeRanks as $timeModeRank)
	{
		$rank = $timeModeRank['name'];
		$rankID = $timeModeRank['id'];

		echo '<div class="imageContainer1" id="rank-selector-'.$categoryID.'-'.$rankID.'">';
		if ($unlocked)
			echo '<a style="text-decoration:none;" href="/timeMode/start?categoryID='.$categoryID.'&rankID='.$rankID.'">';
		echo '<img src="/img/rankButton'.$rank.($unlocked ? '' : 'inactive').'.png" '.($unlocked ? 'onmouseover="this.src = \'/img/rankButton'.$rank.'hover.png\';" onmouseout="this.src = \'/img/rankButton'.$rank.'.png\';"' : '').'>';
		echo '<div class="imageContainerText2"> <img class="timeModeIcons" src="/img/timeModeStored.png">'.$timeModeRank['tsumego_count'].'</div>';
		if ($unlocked)
			echo '</a>';
		echo '</div>';
		$unlocked = @$solvedMap[$categoryID][$rankID]; // the one after solved is unlocked
	}
echo '</div>';
}?>
<script>

	var timeModeCategoryID = getCookie('lastTimeModeCategoryID') || <?php echo $lastTimeModeCategoryID; ?>;
	function setTimeModeCategory(categoryIDToSet, shouldSetCookie = true)
	{
		for (const categoryID of [<?php $result = []; foreach ($timeModeCategories as $timeModeCategory) $result[] = $timeModeCategory['TimeModeCategory']['id']; echo implode(',', $result); ?>])
		{
			var element = document.getElementById("timeMode" + categoryID);
			element.style = "cursor: pointer;";
			var section = document.getElementById("time-mode" + categoryID);
			if (categoryID == categoryIDToSet)
			{
				element.src = '/img/timeMode' + categoryID + '2.png';
				section.style.display = "block";
			}
			else
			{
				element.src = '/img/timeMode' + categoryID + 'inactive2.png';
				section.style.display = "none";
			}
		}
		updateRankBar();
		timeModeCategoryID = categoryIDToSet;
		if (shouldSetCookie)
			setCookie('lastTimeModeCategoryID', categoryIDToSet);
	}
	setTimeModeCategory(timeModeCategoryID, false);
	$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
	$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
	$("#account-bar-user a").attr("class", "xp-text-fill-c3x");

	function getRankForMode(timeModeCategoryID)
	{
	<?php
	foreach ($timeModeCategories as $timeModeCategory)
	{
		$timeModeCategoryID = $timeModeCategory['TimeModeCategory']['id'];
		if ($bestSolvedRankID = @$solvedMap[$timeModeCategoryID]['best-solved-rank'])
			$bestSolvedRank = $solvedMap[$timeModeCategoryID][$bestSolvedRankID];
		else
			$bestSolvedRank = 'no rank';
		echo "if (timeModeCategoryID == ".$timeModeCategoryID.") return '".$bestSolvedRank."';";
	}
	?>
	}
	function updateRankBar() { $("#account-bar-xp").text(getRankForMode(timeModeCategoryID)); }
	$("#xp-bar-fill").css("width","100%");
</script>
