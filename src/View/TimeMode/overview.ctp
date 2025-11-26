	<?php	if(!Auth::isLoggedIn()) echo '<script type="text/javascript">window.location.href = "/";</script>';	?>

	<div align="center">
		<h2>Time Mode Select</h2>
	</div>
	<br>
	<div align="center">
		<a class="new-button-inactive" href="#">Select</a>
		<?php
		if(count($ro)==0) echo '<a class="new-button-inactive" href="#">Results</a>';
		else echo '<a class="new-button" href="/timeMode/result">Results</a>';
		?>
	</div>
	<br><br>
	<div align="center">
	<?php
	foreach ($timeModeCategories as $timeModeCategory)
	{
		$categoryID = $timeModeCategory['TimeModeCategory']['id'];
		echo '<img id="timeMode'.$categoryID.'" src="/img/timeMode'.$categoryID.'2.png" width="200" onclick="setTimeModeCategory('.$categoryID.');" onmouseover="hoverTimeMode'.$categoryID.'();" onmouseout="noHoverTimeMode'.$categoryID.'();"/>';
	}
	?>
	</div>
	<br>

	<br>

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
			if ($unlocked) {
				echo '<a style="text-decoration:none;" href="/timeMode/start?categoryID='.$categoryID.'&rankID='.$rankID.'">';
			}
			echo '<img src="/img/rankButton'.$rank.($unlocked ? '' : 'inactive').'.png" '.($unlocked ? 'onmouseover="this.src = \'/img/rankButton'.$rank.'hover.png\';" onmouseout="this.src = \'/img/rankButton'.$rank.'.png\';"' : '').'>';
			echo '<div class="imageContainerText2"> <img class="timeModeIcons" src="/img/timeModeStored.png">'.$timeModeRank['tsumego_count'].'</div>';
			if ($unlocked) {
				echo '</a>';
			}
			echo '</div>';
			$unlocked = @$solvedMap[$categoryID][$rankID]; // the one after solved is unlocked
		}
	echo '</div>';
	}?>
	<script>

	var timeModeCategoryID = getCookie('lastTimeModeCategoryID') || <?php echo $timeModeCategories[0]['TimeModeCategory']['id']; ?>;

	function setTimeModeCategory(categoryIDToSet)
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
		setCookie('lastTimeModeCategoryID', categoryIDToSet);
	}

	setTimeModeCategory(timeModeCategoryID);

	function hoverTimeMode1(){
		document.getElementById("timeMode1").src = "/img/timeMode1hover2.png";
	}
	function noHoverTimeMode1(){
		if(timeModeCategoryID==1) document.getElementById("timeMode1").src = "/img/timeMode12.png";
		else document.getElementById("timeMode1").src = "/img/timeMode1inactive2.png";
	}
	function hoverTimeMode2(){
		document.getElementById("timeMode2").src = "/img/timeMode2hover2.png";
	}
	function noHoverTimeMode2(){
		if(timeModeCategoryID==2) document.getElementById("timeMode2").src = "/img/timeMode22.png";
		else document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
	}
	function hoverTimeMode3(){
		document.getElementById("timeMode3").src = "/img/timeMode3hover2.png?v=1.2";
	}
	function noHoverTimeMode3() {
		if (timeModeCategoryID == 3) document.getElementById("timeMode3").src = "/img/timeMode32.png?v=1.2";
		else document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
	}
	$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
	$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
	$("#account-bar-user a").attr("class", "xp-text-fill-c3x");


  function getRankForMode(timeModeCategoryID) {
  <?php
    foreach ($timeModeCategories as $timeModeCategory) {
      $timeModeCategoryID = $timeModeCategory['TimeModeCategory']['id'];
      if ($bestSolvedRankID = @$solvedMap[$timeModeCategoryID]['best-solved-rank']) {
        $bestSolvedRank = $solvedMap[$timeModeCategoryID][$bestSolvedRankID];
	  }
	  else {
        $bestSolvedRank = 'no rank';
      }
      echo "if (timeModeCategoryID == ".$timeModeCategoryID.") return '".$bestSolvedRank."';";
    }
  ?>
  }
  function updateRankBar() { $("#account-bar-xp").text(getRankForMode(timeModeCategoryID)); }
	$("#xp-bar-fill").css("width","100%");
	</script>
