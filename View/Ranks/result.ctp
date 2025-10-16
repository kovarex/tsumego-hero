	<?php
	if(isset($_SESSION['loggedInUser'])){	
	}else{
		echo '<script type="text/javascript">window.location.href = "/";</script>';
	}
	?>
	<div align="center">
	<h2>Time Mode Results</h2>
	<br>
	<div align="center">
		<a class="new-button" href="/ranks/overview">Select</a>
		<a class="new-button-inactive" href="#">Results</a>
	</div>
	<br><br>
	<table class="timeModeTable" border="0">
		<?php
		if(count($ranks)==0) $finish = false;
		else $finish = true;
		$c = $stopParameterNum-$c;
		$modesOrder = 0;
		$sum = 0;
		for($i=0;$i<count($points);$i++){
			$sum+=$points[$i];
		}
		
		while($modesOrder<=1){
			for($h=count($modes)-1;$h>=0;$h--){
				if($modesOrder==0 && $h==$openCard1 || $modesOrder==1 && $h!=$openCard1){
					for($i=count($modes[$h])-1;$i>=0;$i--){
						if(isset($modes[$h][$i]['RankOverview'])){
							
							if($modes[$h][$i]['RankOverview']['status'] == 'passed') $boxHighlight = 'tScoreTitle1';
							else $boxHighlight = 'tScoreTitle2';
							echo '<tr>';
							echo '<td>';
							echo '<div class="tScoreTitle '.$boxHighlight.'" id="title'.$h.'_'.$i.'">';
							echo '<table class="timeModeTable2" width="100%" border="0">';
							echo '<tr>';
							echo '<td width="9%">'.$modes[$h][$i]['RankOverview']['mode'].'</td>';
							echo '<td width="46%">'.$modes[$h][$i]['RankOverview']['rank'].'</td>';
							echo '<td width="15%"><b>'.$modes[$h][$i]['RankOverview']['status'].'<b></td>';
							echo '<td width="13%">'.$modes[$h][$i]['RankOverview']['points'].' points</td>';
							echo '<td class="timeModeTable2td">'.$modes[$h][$i]['RankOverview']['created'].'</td>';
							if(!empty($allR[$h][$i])){
								echo '<td width="3%" class="timeModeTable2td"><img id="arrow'.$h.'_'.$i.'" src="/img/greyArrow1.png"></td>';
							}
							echo '</tr>';
							echo '</table>';
							echo '</div>';
							
							if(!empty($allR[$h][$i])){
								echo '<div class="timeModeTable3" width="100%" id="content'.$h.'_'.$i.'" style="display: block;">';
								echo '<table width="100%" class="scoreTable" border="0">';	
								for($j=0;$j<count($allR[$h][$i]);$j++){
									if($h==$openCard1&&$i==$openCard2 && $j==0 && $finish && $sessionFound){
										echo '<tr>';
										echo '<td colspan="5">';
										if($solved>=$stopParameterPass){
											$pf='passed';
											$cpf = 'green';
										}else{
											$pf='failed';
											$cpf = '#e03c4b';
										}
										echo '<h4 style="color:'.$cpf.';">Result: '.$pf.'('.$c.'/'.$stopParameterNum.') - '.$sum.' points</h4>';
										echo '</td>';
										echo '</tr>';
									}
									if(!$sessionFound && $h==$openCard1&&$i==$openCard2 && $j==0){
										echo '<tr>';
										echo '<td colspan="5">';
										echo '<h4>Best Result:</h4>';
										echo '</td>';
										echo '</tr>';
									}
									
									
									echo '<tr>';
									echo '<td width="9%">#'.$allR[$h][$i][$j]['Rank']['num'].'</td>';
									echo '<td width="46%"><a href="/tsumegos/play/'.$allR[$h][$i][$j]['Rank']['tsumego_id'].'">'.$allR[$h][$i][$j]['Rank']['tsumego'].'</a></td>';
									echo '<td width="7%">'.$allR[$h][$i][$j]['Rank']['result'].'</td>';
									echo '<td width="8%">'.$allR[$h][$i][$j]['Rank']['seconds'].'</td>';
									echo '<td>'.$allR[$h][$i][$j]['Rank']['points'].' points</td>';
									echo '</tr>';
								}
								echo '</table>';
								if(!$sessionFound && $h==$openCard1&&$i==$openCard2){
									echo '<br>';	
									echo '<table width="100%" class="scoreTable" border="0">';

									for($k=0;$k<count($ranks);$k++){
										if($k==0){
											echo '<tr>';
											echo '<td colspan="5">';
											if($solved>=$stopParameterPass){
												$pf='passed';
												$cpf = 'green';
											}else{
												$pf='failed';
												$cpf = '#e03c4b';
											}
											echo '<h4 style="color:'.$cpf.';">Result: '.$pf.'('.$c.'/'.$stopParameterNum.') - '.$sum.' points</h4>';
											echo '</td>';
											echo '</tr>';
										}
										if($ranks[$k]['Rank']['result']=='solved'){
											$ranks[$k]['Rank']['result'] = '<b style="color:green;">'.$ranks[$k]['Rank']['result'].'</b>';
											$ranks[$k]['Rank']['seconds'] = '<font style="color:green;">'.$ranks[$k]['Rank']['seconds'].'</font>';
										}else{
											$ranks[$k]['Rank']['result'] = '<b style="color:#e03c4b;">'.$ranks[$k]['Rank']['result'].'</b>';
											$ranks[$k]['Rank']['seconds'] = '<font style="color:#e03c4b;">'.$ranks[$k]['Rank']['seconds'].'</font>';
										}
										echo '<tr>';
										echo '<td width="9%">#'.($k+1).'</td>';
										echo '<td width="46%"><a href="/tsumegos/play/'.$ranks[$k]['Rank']['tsumego_id'].'">'.$ranks[$k]['Rank']['set1'].' '.$ranks[$k]['Rank']['set2'].' - '.$ranks[$k]['Rank']['tsumegoNum'].'</a></td>';
										echo '<td width="7%">'.$ranks[$k]['Rank']['result'].'</td>';
										echo '<td width="8%">'.$ranks[$k]['Rank']['seconds'].'</td>';
										echo '<td>'.$ranks[$k]['Rank']['points'].' points</td>';
										echo '</tr>';
									}
									echo '</table>';
								}
								echo '</div>';
							}
							echo '</td>';
							echo '</tr>';
						}
					}
				}
			}
			$modesOrder++;
		}
		?>
	</table>
	<br>
	</div>
	<?php if(isset($ro['RankOverview']['status']) && $ro['RankOverview']['status']=='s' && $newUnlock){
	$alertCategory = '';
	$alertRank = '';
	if($ro['RankOverview']['mode']==0) $alertCategory = 'blitz';
	elseif($ro['RankOverview']['mode']==1) $alertCategory = 'fast';
	elseif($ro['RankOverview']['mode']==2) $alertCategory = 'slow';
	
	if($ro['RankOverview']['rank']=='15k') $alertRank = '14k';
	elseif($ro['RankOverview']['rank']=='14k') $alertRank = '13k';
	elseif($ro['RankOverview']['rank']=='13k') $alertRank = '12k';
	elseif($ro['RankOverview']['rank']=='12k') $alertRank = '11k';
	elseif($ro['RankOverview']['rank']=='11k') $alertRank = '10k';
	elseif($ro['RankOverview']['rank']=='10k') $alertRank = '9k';
	elseif($ro['RankOverview']['rank']=='9k') $alertRank = '8k';
	elseif($ro['RankOverview']['rank']=='8k') $alertRank = '7k';
	elseif($ro['RankOverview']['rank']=='7k') $alertRank = '6k';
	elseif($ro['RankOverview']['rank']=='6k') $alertRank = '5k';
	elseif($ro['RankOverview']['rank']=='5k') $alertRank = '4k';
	elseif($ro['RankOverview']['rank']=='4k') $alertRank = '3k';
	elseif($ro['RankOverview']['rank']=='3k') $alertRank = '2k';
	elseif($ro['RankOverview']['rank']=='2k') $alertRank = '1k';
	elseif($ro['RankOverview']['rank']=='1k') $alertRank = '1d';
	elseif($ro['RankOverview']['rank']=='1d') $alertRank = '2d';
	elseif($ro['RankOverview']['rank']=='2d') $alertRank = '3d';
	elseif($ro['RankOverview']['rank']=='3d') $alertRank = '4d';
	elseif($ro['RankOverview']['rank']=='4d') $alertRank = '5d';
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
			echo '<a style="color:black;text-decoration:none;" href="/ranks/overview"><img id="hpIcon1" src="/img/rankButton'.$alertRank.'.png">
			You unlocked the '.$alertRank.' '.$alertCategory.' rank.</a><br>'
			?>
			<br class="clear1"/></span>
		  </div>
		</label>
	<?php } ?>
	<script>
		<?php
			for($h=count($modes)-1;$h>=0;$h--){
				for($i=0;$i<count($modes[$h]);$i++){
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
			}
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
			
			<?php if(isset($ro['RankOverview']['status']) && $ro['RankOverview']['status']=='s' && $newUnlock){ ?>
			$(".alertBox").fadeIn(500);
			<?php } ?>
		});
		
		
		$("#alertCheckbox").change(function(){
			$("#alertInfo").fadeOut(500);
		});
		
		
		
	</script>