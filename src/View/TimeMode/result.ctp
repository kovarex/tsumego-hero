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
		}

		function showRank($session, $dataForView) {
			if($session['best']['status'] == 'passed') {
				$boxHighlight = 'tScoreTitle1';
			}
			else
				$boxHighlight = 'tScoreTitle2';
			$containsCurrent = isset($session['current']);

			echo '<tr>';
			echo '<td>';
			echo '<div class="tScoreTitle '.$boxHighlight.'" id="title'.$session['best']['id'].'" onclick="toggleRelatedContent(this);">';
			echo '<table class="timeModeTable2" width="100%" border="0">';
			echo '<tr>';
			echo '<td width="9%">'.$session['best']['category'].'</td>';
			echo '<td width="46%">'.$session['best']['rank'].'</td>';
			echo '<td width="15%"><b>'.$session['best']['status'].'</b></td>';
			echo '<td width="13%">'.$session['best']['points'].' points</td>';
			echo '<td class="timeModeTable2td">'.$session['best']['created'].'</td>';
			echo '<td width="3%" class="timeModeTable2td"><img class="rankArrow" src="'.($containsCurrent ? $dataForView['rankArrowOpened'] : $dataForView['rankArrowClosed']).'"></td>';
			echo '</tr>';
			echo '</table>';
			echo '</div>';
			echo '<div class="timeModeTable3" width="100%" style="display: '.($containsCurrent ? '' : 'none').';">';
			echo '<table width="100%" class="scoreTable" border="0">';
			$bestIsCurrent = $containsCurrent && $session['current']['id'] == $session['best']['id'];
			showSession($session['best'], $bestIsCurrent);
			if (!$bestIsCurrent && isset($session['current'])) {
				showSession($session['current'], true);
			}
			echo '</table>';
			echo '</div>';
		}

		foreach ($sessionsToShow as $categoryToShow) {
			foreach ($categoryToShow as $rankToShow) {
				showRank($rankToShow, $dataForView);
			}
		}
	?>
	</table>
	<br>
	</div>
	<?php if($unlock) { ?>
		<label>
			<input type="checkbox" class="alertCheckbox1" id="alertCheckbox" autocomplete="off">
			<div class="alertBox alertInfo" id="alertInfo">
			<div class="alertBanner" align="center">Unlocked<span class="alertClose">x</span></div>
			<span class="alertText">
			<a style="color:black;text-decoration:none;" href="/timeMode/overview"><img id="hpIcon1" src="/img/rankButton'.<?php echo $unlock['rank']; ?>.png">
			  You unlocked the <?php echo $unlock['rank'];?> <?php echo $unlock['category']; ?> rank.
			</a><br>'
			<br class="clear1"/></span>
			</div>
		</label><?php
	} ?>
	<script>
		function toggleRelatedContent(div) {
			let parentOfDiv = div.parentElement;
			let content = parentOfDiv.querySelector('.timeModeTable3');
			let rankArrow = div.querySelector('.rankArrow');
			if (content.style.display === 'none' || getComputedStyle(content).display === 'none') {
				content.style.display = '';
				rankArrow.setAttribute('src', '<?php echo $dataForView['rankArrowOpened'] ?>');
			}
			else {
				content.style.display = 'none';
				rankArrow.setAttribute('src', '<?php echo $dataForView['rankArrowClosed'] ?>');
			}
		}
		$(document).ready(function() {
			$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
			$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
			$("#account-bar-user a").attr("class", "xp-text-fill-c3x");
			$("#modeSelector").hide();
			notMode3 = false;

			bartext = "<?php echo 'some rank to show'; ?>"; // TODO:
			barPercent = "<?php echo '100'; ?>%"; // TODO:

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
