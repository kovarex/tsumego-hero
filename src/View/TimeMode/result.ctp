	<?php
	if(Auth::isLoggedIn()){
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	?>
	<div align="center">
	<h2>Time Mode Results</h2>
	<br>
	<div align="center">
		<a class="new-button" href="/timeMode/overview">Select</a>
		<a class="new-button-inactive" href="#">Results</a>
	</div>
	<br><br>
	<table class="timeModeTable" border="0">
		<?php

		function showSession($session, $isCurrent) {
      echo '<tr>';
      echo '<td colspan="5">';
      $color = $session['status'] == 'passed' ? 'green' : '#e03c4b';
      echo '<h4 style="color:'.$color.';">Result: '.$session['status'].'('.$session['solvedCount'].'/'.TimeModeUtil::$PROBLEM_COUNT.')';
      if ($isCurrent) {
        echo '- '.$session['points'].' points';
      }
      echo '</h4>';
      echo '</td>';
      echo '</tr>';
      foreach ($session['attempts'] as $attempt) {
          echo '<tr>';
          echo '<td width="9%">#'.$attempt['order'].'</td>';
          echo '<td width="46%"><a href="/tsumegos/play/'.$attempt['tsumego_id'].'">'.$attempt['set'].' - '.$attempt['set_order'].'</a></td>';
    		  $color = $attempt['status'] == 'solved' ? 'green' : '#e03c4b';
          echo '<td width="7%" style="color:'.$color.';">'.$attempt['status'].'</td>';
          echo '<td width="8%" style="color:'.$color.';">'.$attempt['seconds'].'</td>';
          echo '<td>'.$attempt['points'].' points</td>';
          echo '</tr>';
        }
      echo '</td>';
      echo '</tr>';
    }

    function showRank($session) {
      if($session['best']['status'] == 'passed') {
        $boxHighlight = 'tScoreTitle1';
	    }
      else
        $boxHighlight = 'tScoreTitle2';
      echo '<tr>';
      echo '<td>';
      echo '<div class="tScoreTitle '.$boxHighlight.'" id="title'.$session['best']['id'].'">';
      echo '<table class="timeModeTable2" width="100%" border="0">';
      echo '<tr>';
      echo '<td width="9%">'.$session['best']['category'].'</td>';
      echo '<td width="46%">'.$session['best']['rank'].'</td>';
      echo '<td width="15%"><b>'.$session['best']['status'].'<b></td>';
      echo '<td width="13%">'.$session['best']['points'].' points</td>';
      echo '<td class="timeModeTable2td">'.$session['best']['created'].'</td>';
      echo '<td width="3%" class="timeModeTable2td"><img id="arrow'.$session['best']['id'].'" src="/img/greyArrow1.png"></td>';
      echo '</tr>';
      echo '</table>';
      echo '</div>';

      echo '<table width="100%" class="scoreTable" border="0">';
      $bestIsCurrent = isset($session['current']) && $session['current']['id'] == $session['best']['id'];
      showSession($session['best'], $bestIsCurrent);
      if (!$bestIsCurrent && isset($session['current'])) {
        showSession($session['current'], true);
      }
      echo '</table>';
    }


    foreach ($sessionsToShow as $categoryToShow) {
      foreach ($categoryToShow as $rankToShow) {
        showRank($rankToShow);
      }
		}
		?>
	</table>
	<br>
	</div>
	<?php if(@$finishedSession['TimeModeSession']['time_mode_session_status_id'] == TimeModeUtil::$SESSION_STATUS_SOLVED &&
      $newUnlock){
	$alertCategory = '';
	$alertRank = '';
	if($ro['TimeModeOverview']['mode']==0) $alertCategory = 'blitz';
	elseif($ro['TimeModeOverview']['mode']==1) $alertCategory = 'fast';
	elseif($ro['TimeModeOverview']['mode']==2) $alertCategory = 'slow';
	
	if($ro['TimeModeOverview']['rank']=='15k') $alertRank = '14k';
	elseif($ro['TimeModeOverview']['rank']=='14k') $alertRank = '13k';
	elseif($ro['TimeModeOverview']['rank']=='13k') $alertRank = '12k';
	elseif($ro['TimeModeOverview']['rank']=='12k') $alertRank = '11k';
	elseif($ro['TimeModeOverview']['rank']=='11k') $alertRank = '10k';
	elseif($ro['TimeModeOverview']['rank']=='10k') $alertRank = '9k';
	elseif($ro['TimeModeOverview']['rank']=='9k') $alertRank = '8k';
	elseif($ro['TimeModeOverview']['rank']=='8k') $alertRank = '7k';
	elseif($ro['TimeModeOverview']['rank']=='7k') $alertRank = '6k';
	elseif($ro['TimeModeOverview']['rank']=='6k') $alertRank = '5k';
	elseif($ro['TimeModeOverview']['rank']=='5k') $alertRank = '4k';
	elseif($ro['TimeModeOverview']['rank']=='4k') $alertRank = '3k';
	elseif($ro['TimeModeOverview']['rank']=='3k') $alertRank = '2k';
	elseif($ro['TimeModeOverview']['rank']=='2k') $alertRank = '1k';
	elseif($ro['TimeModeOverview']['rank']=='1k') $alertRank = '1d';
	elseif($ro['TimeModeOverview']['rank']=='1d') $alertRank = '2d';
	elseif($ro['TimeModeOverview']['rank']=='2d') $alertRank = '3d';
	elseif($ro['TimeModeOverview']['rank']=='3d') $alertRank = '4d';
	elseif($ro['TimeModeOverview']['rank']=='4d') $alertRank = '5d';
	?>
		<label>
		  <input type="checkbox" class="alertCheckbox1" id="alertCheckbox" autocomplete="off" />
		  <div class="alertBox alertInfo" id="alertInfo">
			<div class="alertBanner" align="center">
			Unlocked
			<span class="alertClose">x</span>
			</div>
			<span class="alertText">
			<?php
			echo '<a style="color:black;text-decoration:none;" href="/timeMode/overview"><img id="hpIcon1" src="/img/rankButton'.$alertRank.'.png">
			You unlocked the '.$alertRank.' '.$alertCategory.' rank.</a><br>'
			?>
			<br class="clear1"/></span>
		  </div>
		</label>
	<?php } ?>
	<script>
		<?php
        /*
			for($h=count($sessionsToShow)-1;$h>=0;$h--){
				for($i=0;$i<count($sessionsToShow[$h]);$i++){
					echo 'var triggered'.$h.'_'.$i.' = false;';
					echo '$("#content'.$h.'_'.$i.'").hide();';
					if($h==$openCard1&&$i==$openCard2 && $finish){
						echo '$("#content'.$h.'_'.$i.'").show();';
						echo 'triggered'.$h.'_'.$i.' = true;';
					}					
					echo '$("#title'.$h.'_'.$i.'").click(function(){
						if(!triggered'.$h.'_'.$i.'){
							$("#content'.$h.'_'.$i.'").fadeIn(250);
							document.getElementById("arrow'.$h.'_'.$i.'").src = "/img/greyArrow2.png";
						}else{
							$("#content'.$h.'_'.$i.'").fadeOut(250);
							document.getElementById("arrow'.$h.'_'.$i.'").src = "/img/greyArrow1.png";
						}
						triggered'.$h.'_'.$i.' = !triggered'.$h.'_'.$i.';
					});';
				}
			}*/
		?>
		$(document).ready(function(){
			$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
			$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
			$("#account-bar-user a").attr("class", "xp-text-fill-c3x");
			$("#modeSelector").hide();
			notMode3 = false;
			<?php
				$bt = '15k';
				if($finish) $bt = $ranks[0]['Rank']['rank'];
				else $bt = $lastModeV;
				if($c!=0) $bp = ($c/$stopParameterNum)*100;
				else $bp = 100;
			?>
			
			bartext = "<?php echo $bt; ?>";
			barPercent = "<?php echo $bp; ?>%";
			
			$("#account-bar-xp").text(bartext);
			$("#account-bar-xp").html(bartext);
			$("#xp-bar-fill").css("width", barPercent);
			
			<?php if(isset($ro['TimeModeOverview']['status']) && $ro['TimeModeOverview']['status']=='s' && $newUnlock){ ?>
			$(".alertBox").fadeIn(500);
			<?php } ?>
		});
		
		
		$("#alertCheckbox").change(function(){
			$("#alertInfo").fadeOut(500);
		});
		
		
		
	</script>