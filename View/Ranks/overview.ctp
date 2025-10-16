	<?php	if(!isset($_SESSION['loggedInUser']['User']['id'])) echo '<script type="text/javascript">window.location.href = "/";</script>';	?>
	
	<div align="center">
		<h2>Time Mode Select</h2>
	</div>
	<br>
	<div align="center">
		<a class="new-button-inactive" href="#">Select</a>
		<?php
		if(count($ro)==0) echo '<a class="new-button-inactive" href="#">Results</a>';
		else echo '<a class="new-button" href="/ranks/result">Results</a>';
		?>
	</div>
	<br><br>
	<div align="center">
	
		<img id="timeMode3" src="/img/timeMode32.png?v=1.2" width="200" onclick="timeMode3();" onmouseover="hoverTimeMode3()" onmouseout="noHoverTimeMode3()">
		<img id="timeMode2" src="/img/timeMode22.png" width="200" onclick="timeMode2();" onmouseover="hoverTimeMode2()" onmouseout="noHoverTimeMode2()">
		<img id="timeMode1" src="/img/timeMode12.png" width="200" onclick="timeMode1();" onmouseover="hoverTimeMode1()" onmouseout="noHoverTimeMode1()">
		
	</div>
	<br>
	<div id="settingsToggle">
		<a id="settingsText" onclick="settings();" onmouseover="settingsIn()" onmouseout="settingsOut()"><img id="cog" src="/img/cog.png" width="16px"><b>&nbsp;Settings</b></a>
	</div>
	
	<div id="timeModeSettings">
	 <?php
		echo '<br>';
		echo $this->Form->create('Settings');
		for($h=0;$h<count($settings['id']);$h++){
			echo '<input name="data[Settings][i'.$settings['id'][$h].']" type="checkbox" value="'.$settings['id'][$h].'" id="i'.$settings['id'][$h].'" '.$settings['checked'][$h].' onchange="countChecks();">';
			echo '<label for="i'.$settings['id'][$h].'">'.$settings['title'][$h].'</label><br>';
		}
		echo '<br>';
		echo $this->Form->end('Submit');
	?>
	</div>
	<br>
	<?php 
	for($h=0;$h<count($modes);$h++){
	$text = '';
	if($h==0){ 
		$text = 'time-mode1';
		$modeLink = 1;
	}elseif($h==1){ 
		$text = 'time-mode2';
		$modeLink = 2;
	}else{
		$text = 'time-mode3';
		$modeLink = 3;
	}
	echo '<div id="'.$text.'">';
		for($i=0;$i<count($modes[$h]);$i++){
			if($locks[$h][$i]=='x'){
				$p = array();
				$p[1] = '';
				$p[0] = '';
				$imageContainerText = 'imageContainerText2';
				$imageContainerSpace = '';
				echo '<div class="imageContainer1">
				<a style="text-decoration:none;" href="/tsumegos/play/5127?rank='.$modes[$h][$i].'&modelink='.$modeLink.'">
					<img id="i-'.$h.'-'.$modes[$h][$i].'" src="/img/rankButton'.$modes[$h][$i].'.png" onmouseover="hover_'.$h.'_'.$modes[$h][$i].'()" onmouseout="noHover_'.$h.'_'.$modes[$h][$i].'()">
					 <div class="'.$imageContainerText.'">'.$p[1].' '.$p[0].''.$imageContainerSpace.'<img class="timeModeIcons" src="/img/timeModeStored.png">'.$rxxCount[$i].'</div>
				</a>
				</div>';
			}else{
				echo '<div class="imageContainer1">
					<a>
					<img src="/img/rankButton'.$modes[$h][$i].'inactive.png" >
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
	
	$("#timeModeSettings").hide();
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
	
	function settings(){
		if(s==0){
			$("#timeModeSettings").fadeIn(250);
			s = 1;
		}else{
			$("#timeModeSettings").fadeOut(250);
			s = 0;
		}
	}
	function settingsIn(){
		$("#settingsText").css({'color':'#2651d9'});
	}
	function settingsOut(){
		$("#settingsText").css({'color':'#373b43'});
	}
	function countChecks(){
		checkCounter = 0;
		<?php for($h=0;$h<count($settings['id']);$h++){
			echo 'if($("#i'.$settings['id'][$h].'").is(":checked")) checkCounter++;';
		} ?>
		if(checkCounter<=42){
			<?php for($h=0;$h<count($settings['id']);$h++){
				echo 'if($("#i'.$settings['id'][$h].'").is(":checked")){
						$("#i'.$settings['id'][$h].'").attr("disabled", true);
					}else{
						$("#i'.$settings['id'][$h].'").attr("disabled", false);
					}';
			} ?>
			$('input[type="submit"]').attr('disabled','disabled');
		}else{
			<?php for($h=0;$h<count($settings['id']);$h++){
				echo '$("#i'.$settings['id'][$h].'").removeAttr("disabled");';
			} ?>
			$('input[type="submit"]').removeAttr('disabled');
		}
	}
	function timeMode1(){
		document.getElementById("timeMode1").src = "/img/timeMode12.png";
		document.getElementById("timeMode2").src = "/img/timeMode2inactive2.png";
		document.getElementById("timeMode3").src = "/img/timeMode3inactive2.png?v=1.2";
		$("#time-mode1").fadeIn(250);
		$("#time-mode2").hide();
		$("#time-mode3").hide();
		mode = 1;
		document.cookie = "lastMode=1";
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
	for($h=0;$h<count($modes);$h++){
		for($i=0;$i<count($modes[$h]);$i++){ 
			echo 'function hover_'.$h.'_'.$modes[$h][$i].'(){
				document.getElementById("i-'.$h.'-'.$modes[$h][$i].'").src = "/img/rankButton'.$modes[$h][$i].'hover.png";
			}
			function noHover_'.$h.'_'.$modes[$h][$i].'(){
				document.getElementById("i-'.$h.'-'.$modes[$h][$i].'").src = "/img/rankButton'.$modes[$h][$i].'.png";
			}';
		} 
	}	
	?>
	$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
	$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
	$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
	$("#account-bar-user a").attr("class", "xp-text-fill-c3x");
	
	bartext = "";
	if(mode==1) bartext = "<?php echo $lowestMode[0]; ?>";
	else if(mode==2) bartext = "<?php echo $lowestMode[1]; ?>";
	else if(mode==3) bartext = "<?php echo $lowestMode[2]; ?>";
	
	$("#account-bar-xp").text(bartext);
	$("#xp-bar-fill").css("width","100%");
	</script>
	
	
	