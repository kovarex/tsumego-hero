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
	
		<img id="timeMode3" src="/img/timeMode32.png?v=1.2" width="200" onclick="timeMode3();" onmouseover="hoverTimeMode3()" onmouseout="noHoverTimeMode3()">
		<img id="timeMode2" src="/img/timeMode22.png" width="200" onclick="timeMode2();" onmouseover="hoverTimeMode2()" onmouseout="noHoverTimeMode2()">
		<img id="timeMode1" src="/img/timeMode12.png" width="200" onclick="timeMode1();" onmouseover="hoverTimeMode1()" onmouseout="noHoverTimeMode1()">
		
	</div>
	<br>

	<br>
	<?php
  foreach ($timeModeCategories as $timeModeCategory) {
	echo '<div id="time-mode'.$timeModeCategory['TimeModeCategory']['id'].'">';
    foreach ($timeModeRanks as $timeModeRank) {
		  $rank = $timeModeRank['TimeModeRank']['name'];
  		$rankID = $timeModeRank['TimeModeRank']['id'];
      $categoryID = $timeModeCategory['TimeModeCategory']['id'];
			if(@$solvedMap[$categoryID][$rankID]) {
				$imageContainerText = 'imageContainerText2';
				$imageContainerSpace = '';
				echo '<div class="imageContainer1">
				<a style="text-decoration:none;" href="/timeMode/start?categoryID='.$categoryID.'&rankID='.$rankID.'">
					<img src="/img/rankButton'.$rank.'.png" onmouseover="hover_'.$rankID.'(this)" onmouseout="noHover_'.$rankID.'(this)">
					 <div class="'.$imageContainerText.'">'.' '.$imageContainerSpace.'<img class="timeModeIcons" src="/img/timeModeStored.png">'.$rxxCount[$i].'</div>
				</a>
				</div>';
			}else{
				echo '<div class="imageContainer1">
					<a>
					<img src="/img/rankButton'.$rank.'inactive.png" >
					 <div class="imageContainerText2"><img class="timeModeIcons" src="/img/timeModeStored.png">'.$rxxCount[$i].'</div>
					</a>
				</div>';
			}
		}
	?>
	
	</div>
	<?php
	}
	?>
	
	<script>
	var mode = 1;
	var s = 0;
	
	
	<?php 
		if($lastMode==1) echo 'mode = 1;';
		elseif($lastMode==2) echo 'mode = 2;';
		elseif($lastMode==3) echo 'mode = 3;';
	?>
	
	if(mode!=1) $("#time-mode1").hide();
	if(mode!=2) $("#time-mode2").hide();
	if(mode!=3) $("#time-mode3").hide();
	
	if(mode!=1) document.getElementById("timeMode1").src = "/img/timeMode1inactive2.png";
	if(mode!=2) document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
	if(mode!=3) document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
	
	document.getElementById("timeMode1").style = "cursor: pointer;";
	document.getElementById("timeMode2").style = "cursor: pointer;";
	document.getElementById("timeMode3").style = "cursor: pointer;";
	
	$(document).ready(function(){
		notMode3 = false;
		$("#modeSelector").hide();
	});
	
	function timeMode1(){
		document.getElementById("timeMode1").src = "/img/timeMode12.png";
		document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
		document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
		$("#time-mode1").fadeIn(250);
		$("#time-mode2").hide();
		$("#time-mode3").hide();
		mode = 1;
		document.cookie = "lastMode=1";
    updateRankBar();
	}
	function timeMode2(){
		document.getElementById("timeMode1").src = "/img/timeMode1inactive2.png";
		document.getElementById("timeMode2").src = "/img/timeMode22.png";
		document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
		$("#time-mode1").hide();
		$("#time-mode2").fadeIn(250);
		$("#time-mode3").hide();
		mode = 2;
		document.cookie = "lastMode=2";
    updateRankBar();
	}
	function timeMode3(){
		document.getElementById("timeMode1").src = "/img/timeMode1inactive2.png";
		document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
		document.getElementById("timeMode3").src = "/img/timeMode32.png?v=1.2";
		$("#time-mode1").hide();
		$("#time-mode2").hide();
		$("#time-mode3").fadeIn(250);
		mode = 3;
		document.cookie = "lastMode=3";
    updateRankBar();
	}
	function hoverTimeMode1(){
		document.getElementById("timeMode1").src = "/img/timeMode1hover2.png";
	}
	function noHoverTimeMode1(){
		if(mode==1) document.getElementById("timeMode1").src = "/img/timeMode12.png";
		else document.getElementById("timeMode1").src = "/img/timeMode1inactive2.png";
	}
	function hoverTimeMode2(){
		document.getElementById("timeMode2").src = "/img/timeMode2hover2.png";
	}
	function noHoverTimeMode2(){
		if(mode==2) document.getElementById("timeMode2").src = "/img/timeMode22.png";
		else document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
	}
	function hoverTimeMode3(){
		document.getElementById("timeMode3").src = "/img/timeMode3hover2.png?v=1.2";
	}
	function noHoverTimeMode3(){
		if(mode==3) document.getElementById("timeMode3").src = "/img/timeMode32.png?v=1.2";
		else document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
	}
	<?php
    foreach ($timeModeRanks as $timeModeRank) {
      $rankID = $timeModeRank['TimeModeRank']['id'];
      $rankName = $timeModeRank['TimeModeRank']['name'];
			echo 'function hover_'.$rankID.'(element) { element.src = "/img/rankButton'.$rankName.'hover.png"; }';
			echo 'function noHover_'.$rankID.'(element) { element.src = "/img/rankButton'.$rankName.'.png"; }';
		} 
	?>
	$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
	$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
	$("#account-bar-user a").attr("class", "xp-text-fill-c3x");


  function getRankForMode(mode) {
  <?php
    foreach ($timeModeCategories as $timeModeCategory) {
      $timeModeCategoryID = $timeModeCategory['TimeModeCategory']['id'];
      if ($bestUnlockedRankID = @$solvedMap[$timeModeCategoryID]['best-unlocked-rank'])
        $bestUnlockedRank = $solvedMap[$timeModeCategoryID][$bestUnlockedRankID];
      if (!$bestUnlockedRank) {
        $bestUnlockedRank = $timeModeRanks[0]['TimeModeRank']['name'];
      }
      echo "if (mode == ".$timeModeCategoryID.") return '".$bestUnlockedRank."';";
    }
  ?>
  }
  function updateRankBar() { $("#account-bar-xp").text(getRankForMode(mode)); }
	$("#xp-bar-fill").css("width","100%");
	</script>
	
	
	