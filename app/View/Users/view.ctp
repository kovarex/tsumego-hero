<?php ?>
	<div class="homeCenter2">
		<?php
			if($hideEmail){
				$user = null;
				$solved = 0;
				$xpSum = 0;
				$p = 0;
				$rank = 0;
			}
		?>
	<div class="user-header-container">
		<div class="user-header1">
			<p class="title6">Profile</p>
			
		</div>
		<div class="user-header2">
			<a href="/tag_names/user/<?php echo $_SESSION['loggedInUser']['User']['id']; ?>" class="new-button-time">contributions</a>
		</div>
	</div>
	<div class="userMain1">
		<div class="userTop2">
		<table class="userTopTable1" border="0">
		<tr>
		<td>
			<?php if($_SESSION['loggedInUser']['User']['premium'] == 2){ ?>
				<div style="float:left;width:50%;"><?php echo $user['User']['name'] ?></div>
				<div style="float:left;width:50%;"><img alt="Account Type" title="Account Type" src="/img/premium2.png" height="16px"></div>
			<?php }else if($_SESSION['loggedInUser']['User']['premium'] == 1){ ?>
				<div style="float:left;width:50%;"><?php echo $user['User']['name'] ?></div>
				<div style="float:left;width:50%;"><img alt="Account Type" title="Account Type" src="/img/premium1.png" height="16px"></div>
			<?php }else{ ?>
				<div><?php echo $user['User']['name'] ?></div>
			<?php }?>
		<?php
		if(!$hideEmail){
			echo '<div id="msg1">'.$user['User']['email'].' <a id="show" style="color:#74d14c;">change</a></div>';
			echo '<div id="msg2">';
			echo $this->Form->create('User');
			echo '<table border="0">';
			echo '<tr>';
			echo '<td style="vertical-align:top;>';
			echo $this->Form->input('email', array('label' => '', 'type' => 'text', 'placeholder' => 'E-Mail'));
			echo '</td><td style="vertical-align: top;" >';
			echo '<div class="submit"><input style="margin:0px;" value="Submit" type="submit"></div>';
			echo '</td>';
			echo '</tr>';
			echo '</table>';
			echo '</div>';
		}
		if($lightDark=='dark'){
			$lightDarkChartColor = '#fff';
			$lightDarkChartColor2 = '#3e3e3e';
		}else{
			$lightDarkChartColor = '#000';
			$lightDarkChartColor2 = '#ddd';
		}		
			
		if($solved > $tsumegoNum) $solved=$tsumegoNum;
		$heroPowers = 0;
		if($user['User']['level']>=20) $heroPowers++;
		if($user['User']['level']>=30) $heroPowers++;
		if($user['User']['level']>=40) $heroPowers++;
		if($user['User']['premium']>0 || $user['User']['level']>=100) $heroPowers++;
		?>
		</td>
		</tr>
		</table>
		</div>
		<div class="userTop2">
		<table class="userTopTable1" border="0" width="100%">
		<tr>
		<td>
		<div align="center">
			Your rank:<br>
			<a style="cursor:pointer;" onclick="userShow1(2);">
				<img id="profileRankImage" src="/img/<?php echo $eloRank; ?>Rank.png" width="76px">
			</a>
		</div>
		</td>
		</tr>
		</table>
		</div>
		<div class="userTop2">
		<table class="userTopTable1" border="0">
		<tr>
		<td>
			Progress bar preference:<br><br>
		<?php
			$levelBarDisplayChecked1 = '';
			$levelBarDisplayChecked2 = '';
			if($levelBar==1)
				$levelBarDisplayChecked1 = 'checked="checked"';
			else
				$levelBarDisplayChecked2 = 'checked="checked"';
		?>
		<input type="radio" id="levelBarDisplay1" name="levelBarDisplay" value="1" onclick="levelBarChange(1);" <?php echo $levelBarDisplayChecked1; ?>> <b id="levelBarDisplay1text">Show level</b><br>
		<input type="radio" id="levelBarDisplay2" name="levelBarDisplay" value="2" onclick="levelBarChange(2);" <?php echo $levelBarDisplayChecked2; ?>> <b id="levelBarDisplay2text">Show rating</b><br>
		</td>
		</tr>
		</table>
		</div>
		<div class="userTop2">
		<table class="userTopTable1" border="0">
		<tr>
		<td>
			<?php
			if($deletedProblems==1){
				echo '<font style="font-weight:800;color:#74d14c" >You have completed '.$p.'%. </font>'; 
				if($p>=75){
					echo '<br><br><a class="new-button" href="#" onclick="delUts(); return false;">Reset ('.$dNum.')</a><br><br>';
				}else{
					echo '<br><br><a class="new-button-inactive" href="#" >Reset ('.$dNum.')</a><br><br>';
				}
			}elseif($deletedProblems==2){
				echo '<font style="font-weight:800;color:#74d14c" >The progress of '.$dNum.' problems has been deleted.</font>'; 
			}
			echo 'If you have completed at least 75%, you can reset progress older than 1 year.<br>';
			?>
		</td>
		</tr>
		</table>
		</div>
	</div>
	
	<div class="userMain2">	
		<div class="userTop1">
		<table class="userTopTable1" border="0" width="100%">
		<tr>
			<td>Level:</td>
			<td><?php echo $user['User']['level']; ?></td>
		</tr>
		<tr>
			<td>Level up:</td>
			<td><?php echo $user['User']['xp'].'/'.$user['User']['nextlvl']; ?></td>
		</tr>
		<tr>
			<td>XP earned:</td>
			<td><?php echo $xpSum.' XP'; ?></td>
		</tr>
		<tr>
			<td>Health:</td>
			<td><?php echo $user['User']['health'].' HP'; ?></td>
		</tr>
		<tr>
			<td>Hero powers:</td>
			<td><?php echo $heroPowers; ?></td>
		</tr>
		</table>
		</div>
		
		<div class="userTop1">
		<table class="userTopTable1" border="0" width="100%">
		<tr>
			<td>Rank:</td>
			<td><?php echo $eloRank; ?></td>
		</tr>
		<tr>
			<td>Rating:</td>
			<td><?php echo $user['User']['elo_rating_mode']; ?></td>
		</tr>
		<tr>
			<td>Highest rank:</td>
			<td><?php echo $highestEloRank; ?></td>
		</tr>
		<tr>
			<td>Highest rating:</td>
			<td><?php echo $highestElo; ?></td>
		</tr>
		</table>
		</div>
		
		<div class="userTop1">
		<table class="userTopTable1" border="0" width="100%">
		<tr>
			<td>Time mode rank:</td>
			<td><?php echo $highestRo; ?></td>
		</tr>
		<tr>
			<td>Time mode runs:</td>
			<td><?php echo $timeModeRuns; ?></td>
		</tr>
		</table>
		</div>
		
		<div class="userTop1">
		<table class="userTopTable1" border="0" width="100%">
		<tr>
			<td>Overall solved:</td>
			<td><?php echo $solvedUts2.' of '.$tsumegoNum; ?></td>
		</tr>
		<tr>
			<td>Overall %:</td>
			<td><?php echo $p.'%'; ?></td>
		</tr>
		<tr>
			<td>Achievements:</td>
			<td><?php echo $aNum.'/'.count($aCount); ?></td>
		</tr>
		</table>
		</div>
	</div>
	</div>
	<?php
		$size = count($graph);
		if($size<10) $height = '400';
		else if($size<30) $height = '600';
		else if($size<50) $height = '900';
		else $height = '1200';
	?>
	<div class="userBottom1">
		<table class="profileTable" width="100%" border="0">
		<tr>
		<td width="50%">
			<div align="center">
				<a id="userShowLevel1Button" class="new-button-time" onclick="userShow1(1);">Level</a>
				<a id="userShowRating1Button" class="new-button-time" onclick="userShow1(2);">Rating</a>
				<a id="userShowTime1Button" class="new-button-time" onclick="userShow1(3);">Time</a>
				<a id="userShowAchievement1Button" class="new-button-time" onclick="userShow1(4);">Achievements</a>
			</div>
		</td>
		<td width="50%">
			<div align="center">
				<a id="userShowLevel2Button" class="new-button-time" onclick="userShow2(1);">Level</a>
				<a id="userShowRating2Button" class="new-button-time" onclick="userShow2(2);">Rating</a>
				<a id="userShowTime2Button" class="new-button-time" onclick="userShow2(3);">Time</a>
				<a id="userShowAchievement2Button" class="new-button-time" onclick="userShow2(4);">Achievements</a>
			</div>
		</td>
		</tr>
		</table>
		<br>
		<table class="profileTable" width="100%" border="0">
			<tr>
				<td width="50%">
					<div id="userShowLevel1">
						<div id="chartContainer">
							<div id="chart1"></div>
						</div>
					</div>
					<div id="userShowRating1">
						<div id="chartContainer">
							<div id="chart2"></div>
						</div>
						<div align="center">
							<a href="/tsumego_rating_attempts/user/<?php echo $_SESSION['loggedInUser']['User']['id']; ?>">Show rating mode history</a>
						</div>
					</div>
					<div id="userShowTime1">
						<div id="chartContainer">
							<div id="chart3"></div>
						</div>
					</div>
					<div id="userShowAchievements1">
						<table width="95%" border="0">
						<tr>
							<td class="h1profile">
								<h1 class="h1">Achievements</h1>
							</td>
							<td style="text-align:right;">
								<b class="profileTable2"><a href="/achievements">View Achievements</a></b>
							</td>
						</tr>
						</table>
						<?php
						for($i=0; $i<count($as); $i++){
						if(strlen($as[$i]['AchievementStatus']['a_title'])>30) $adjust = 'style="font-weight:normal;font-size:17px;"';
						else $adjust = '';
						?>
						<a href="/achievements/view/<?php echo $as[$i]['AchievementStatus']['a_id']; ?>">
						<div align="center" class="achievementSmall <?php echo $as[$i]['AchievementStatus']['a_color']; ?>">
							<div class="acTitle2">
								<b <?php echo $adjust; ?>><?php echo $as[$i]['AchievementStatus']['a_title']; ?></b>
							</div>
							<div class="acImg">
								<img src="/img/<?php echo $as[$i]['AchievementStatus']['a_image']; ?>.png" title="<?php echo $as[$i]['AchievementStatus']['a_description']; ?>">
								<div class="acImgXp">
								<?php echo $as[$i]['AchievementStatus']['a_xp']; ?> XP
								</div>
							</div>
							<div class="acDate2">
								<?php 
								$date = date_create($as[$i]['AchievementStatus']['created']);
								echo date_format($date,"d.m.Y H:i");
								?>
							</div>
						</div>
						</a>
						<?php } ?>
					</div>
				</td>
				<td width="50%">
					<div id="userShowLevel2">
						<div id="chartContainer">
							<div id="chart11"></div>
						</div>
					</div>
					<div id="userShowRating2">
						<div id="chartContainer">
							<div id="chart22"></div>
						</div>
						<div align="center">
							<a href="/tsumego_rating_attempts/user/<?php echo $_SESSION['loggedInUser']['User']['id']; ?>">Show rating mode history</a>
						</div>
					</div>
					<div id="userShowTime2">
						<div id="chartContainer">
							<div id="chart33"></div>
						</div>
					</div>
					<div id="userShowAchievements2">
						<table width="95%" border="0">
						<tr>
							<td class="h1profile">
								<h1 class="h1">Achievements</h1>
							</td>
							<td style="text-align:right;">
								<b class="profileTable2"><a href="/achievements">View Achievements</a></b>
							</td>
						</tr>
						</table>
						<?php
						for($i=0; $i<count($as); $i++){
						if(strlen($as[$i]['AchievementStatus']['a_title'])>30) $adjust = 'style="font-weight:normal;font-size:17px;"';
						else $adjust = '';
						?>
						<a href="/achievements/view/<?php echo $as[$i]['AchievementStatus']['a_id']; ?>">
						<div align="center" class="achievementSmall <?php echo $as[$i]['AchievementStatus']['a_color']; ?>">
							<div class="acTitle2">
								<b <?php echo $adjust; ?>><?php echo $as[$i]['AchievementStatus']['a_title']; ?></b>
							</div>
							<div class="acImg">
								<img src="/img/<?php echo $as[$i]['AchievementStatus']['a_image']; ?>.png" title="<?php echo $as[$i]['AchievementStatus']['a_description']; ?>">
								<div class="acImgXp">
								<?php echo $as[$i]['AchievementStatus']['a_xp']; ?> XP
								</div>
							</div>
							<div class="acDate2">
								<?php 
								$date = date_create($as[$i]['AchievementStatus']['created']);
								echo date_format($date,"d.m.Y H:i");
								?>
							</div>
						</div>
						</a>
						<?php } ?>
					</div>
				</td>
			</tr>
		</table>
		<div width="100%" align="right">
			<?php
				if($user['User']['dbstorage'] != 1111){
					echo '<div><a style="color:gray;" href="/users/delete_account">Request account deletion</a></div><br>';
				}else{
					echo '<p style="color:#d63a49;">You have requested account deletion.&nbsp;';
					echo '<a class="new-button-default" href="/users/view/'.$user['User']['id'].'?undo='.($user['User']['id']*1111).'">Undo</a></p>';
				}
				if(isset($_SESSION['loggedInUser']['User']['id'])){
					if($_SESSION['loggedInUser']['User']['isAdmin']!=0){
						echo '<div><a style="color:gray;" href="/users/demote_admin">Remove admin status</a></div><br>';
					}
				}
			?>
		</div>
	</div>
	
	<script>
	userShow1(<?php echo $lastProfileLeft; ?>);
	userShow2(<?php echo $lastProfileRight; ?>);
	
	$("#msg2").hide();
	$("#show").click(function(){
		$("#msg2").show();
	});
	function userShow1(num){
		document.cookie = "lastProfileLeft="+num+";path=/;SameSite=none;Secure=false";
		document.cookie = "lastProfileLeft="+num+";path=/sets;SameSite=none;Secure=false";
		document.cookie = "lastProfileLeft="+num+";path=/sets/view;SameSite=none;Secure=false";
		document.cookie = "lastProfileLeft="+num+";path=/tsumegos/play;SameSite=none;Secure=false";
		document.cookie = "lastProfileLeft="+num+";path=/users;SameSite=none;Secure=false";
		document.cookie = "lastProfileLeft="+num+";path=/users/view;SameSite=none;Secure=false";
		if(num==1){
			$("#userShowLevel1Button").addClass("new-button-time");
			$("#userShowLevel1Button").removeClass("new-button-time-inactive");
			$("#userShowRating1Button").addClass("new-button-time-inactive");
			$("#userShowRating1Button").removeClass("new-button-time");
			$("#userShowTime1Button").addClass("new-button-time-inactive");
			$("#userShowTime1Button").removeClass("new-button-time");
			$("#userShowAchievement1Button").addClass("new-button-time-inactive");
			$("#userShowAchievement1Button").removeClass("new-button-time");
			$("#userShowLevel1").fadeIn(250);
			$("#userShowRating1").hide();
			$("#userShowTime1").hide();
			$("#userShowAchievements1").hide();
		}else if(num==2){
			$("#userShowLevel1Button").addClass("new-button-time-inactive");
			$("#userShowLevel1Button").removeClass("new-button-time");
			$("#userShowRating1Button").addClass("new-button-time");
			$("#userShowRating1Button").removeClass("new-button-time-inactive");
			$("#userShowTime1Button").addClass("new-button-time-inactive");
			$("#userShowTime1Button").removeClass("new-button-time");
			$("#userShowAchievement1Button").addClass("new-button-time-inactive");
			$("#userShowAchievement1Button").removeClass("new-button-time");
			$("#userShowLevel1").hide();
			$("#userShowRating1").fadeIn(250);
			$("#userShowTime1").hide();
			$("#userShowAchievements1").hide();
		}else if(num==3){
			$("#userShowLevel1Button").addClass("new-button-time-inactive");
			$("#userShowLevel1Button").removeClass("new-button-time");
			$("#userShowRating1Button").addClass("new-button-time-inactive");
			$("#userShowRating1Button").removeClass("new-button-time");
			$("#userShowTime1Button").addClass("new-button-time");
			$("#userShowTime1Button").removeClass("new-button-time-inactive");
			$("#userShowAchievement1Button").addClass("new-button-time-inactive");
			$("#userShowAchievement1Button").removeClass("new-button-time");
			$("#userShowLevel1").hide();
			$("#userShowRating1").hide();
			$("#userShowTime1").fadeIn(250);
			$("#userShowAchievements1").hide();
		}else{
			$("#userShowLevel1Button").addClass("new-button-time-inactive");
			$("#userShowLevel1Button").removeClass("new-button-time");
			$("#userShowRating1Button").addClass("new-button-time-inactive");
			$("#userShowRating1Button").removeClass("new-button-time");
			$("#userShowTime1Button").addClass("new-button-time-inactive");
			$("#userShowTime1Button").removeClass("new-button-time");
			$("#userShowAchievement1Button").addClass("new-button-time");
			$("#userShowAchievement1Button").removeClass("new-button-time-inactive");
			$("#userShowLevel1").hide();
			$("#userShowRating1").hide();
			$("#userShowTime1").hide();
			$("#userShowAchievements1").fadeIn(250);
		}
	}
	
	function userShow2(num){
		document.cookie = "lastProfileRight="+num+";path=/;SameSite=none;Secure=false";
		document.cookie = "lastProfileRight="+num+";path=/sets;SameSite=none;Secure=false";
		document.cookie = "lastProfileRight="+num+";path=/sets/view;SameSite=none;Secure=false";
		document.cookie = "lastProfileRight="+num+";path=/tsumegos/play;SameSite=none;Secure=false";
		document.cookie = "lastProfileRight="+num+";path=/users;SameSite=none;Secure=false";
		document.cookie = "lastProfileRight="+num+";path=/users/view;SameSite=none;Secure=false";
		if(num==1){
			$("#userShowLevel2Button").addClass("new-button-time");
			$("#userShowLevel2Button").removeClass("new-button-time-inactive");
			$("#userShowRating2Button").addClass("new-button-time-inactive");
			$("#userShowRating2Button").removeClass("new-button-time");
			$("#userShowTime2Button").addClass("new-button-time-inactive");
			$("#userShowTime2Button").removeClass("new-button-time");
			$("#userShowAchievement2Button").addClass("new-button-time-inactive");
			$("#userShowAchievement2Button").removeClass("new-button-time");
			$("#userShowLevel2").fadeIn(250);
			$("#userShowRating2").hide();
			$("#userShowTime2").hide();
			$("#userShowAchievements2").hide();
		}else if(num==2){
			$("#userShowLevel2Button").addClass("new-button-time-inactive");
			$("#userShowLevel2Button").removeClass("new-button-time");
			$("#userShowRating2Button").addClass("new-button-time");
			$("#userShowRating2Button").removeClass("new-button-time-inactive");
			$("#userShowTime2Button").addClass("new-button-time-inactive");
			$("#userShowTime2Button").removeClass("new-button-time");
			$("#userShowAchievement2Button").addClass("new-button-time-inactive");
			$("#userShowAchievement2Button").removeClass("new-button-time");
			$("#userShowLevel2").hide();
			$("#userShowRating2").fadeIn(250);
			$("#userShowTime2").hide();
			$("#userShowAchievements2").hide();
		}else if(num==3){
			$("#userShowLevel2Button").addClass("new-button-time-inactive");
			$("#userShowLevel2Button").removeClass("new-button-time");
			$("#userShowRating2Button").addClass("new-button-time-inactive");
			$("#userShowRating2Button").removeClass("new-button-time");
			$("#userShowTime2Button").addClass("new-button-time");
			$("#userShowTime2Button").removeClass("new-button-time-inactive");
			$("#userShowAchievement2Button").addClass("new-button-time-inactive");
			$("#userShowAchievement2Button").removeClass("new-button-time");
			$("#userShowLevel2").hide();
			$("#userShowRating2").hide();
			$("#userShowTime2").fadeIn(250);
			$("#userShowAchievements2").hide();
		}else{
			$("#userShowLevel2Button").addClass("new-button-time-inactive");
			$("#userShowLevel2Button").removeClass("new-button-time");
			$("#userShowRating2Button").addClass("new-button-time-inactive");
			$("#userShowRating2Button").removeClass("new-button-time");
			$("#userShowTime2Button").addClass("new-button-time-inactive");
			$("#userShowTime2Button").removeClass("new-button-time");
			$("#userShowAchievement2Button").addClass("new-button-time");
			$("#userShowAchievement2Button").removeClass("new-button-time-inactive");
			$("#userShowLevel2").hide();
			$("#userShowRating2").hide();
			$("#userShowTime2").hide();
			$("#userShowAchievements2").fadeIn(250);
		}
	}
	
	function delUts(){
		var dNum = "<?php echo $dNum; ?>";
		var suid = "<?php echo $_SESSION['loggedInUser']['User']['id']; ?>";
		var confirmed = confirm("Are you sure that you want to delete your progress on "+dNum+" problems?");
		if(confirmed) window.location.href = "/users/view/"+suid+"?delete-uts=true";
	}
	</script>
	<script>
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js"><\/script>'
        )
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/eligrey-classlist-js-polyfill@1.2.20171210/classList.min.js"><\/script>'
        )
      window.Promise ||
        document.write(
          '<script src="https://cdn.jsdelivr.net/npm/findindex_polyfill_mdn"><\/script>'
        )
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
	 <script>
      // Replace Math.random() with a pseudo-random number generator to get reproducible results in e2e tests
      // Based on https://gist.github.com/blixt/f17b47c62508be59987b
      var _seed = 42;
      Math.random = function() {
        _seed = _seed * 16807 % 2147483647;
        return (_seed - 1) / 2147483646;
      };
    </script>
	<script>
		let graph1Categories = [];
		let graph1Solves = [];
		let graph1Fails = [];
		<?php
		foreach ($graph as $key => $value){
			$gDate = new DateTime($key);
			$gDate = $gDate->format('d.m.y');
			echo 'graph1Categories.push("'.$gDate.'");';
			echo 'graph1Solves.push("'.$value['s'].'");';
			echo 'graph1Fails.push("'.$value['f'].'");';
		}
		?>	
        var options = {
          series: [
		{
          name: 'Solves',
          data: graph1Solves,
		  color: '#74d14c'
        }, {
          name: 'Fails',
          data: graph1Fails,
		  color: '#d63a49'
        }],
        chart: {
          type: 'bar',
          height: <?php echo $countGraph; ?>,
          stacked: true,
		  foreColor: "<?php echo $lightDarkChartColor; ?>"
        },
        plotOptions: {
          bar: {
            horizontal: true,
            dataLabels: {
              total: {
                enabled: true,
                offsetX: 0,
                style: {
                  fontSize: '13px',
                  fontWeight: 900,
				  color: "<?php echo $lightDarkChartColor; ?>"
                }
              }
            }
          },
        },
        stroke: {
          width: 1,
          colors: ['#fff']
        },
        title: {
          text: 'Problems in level mode'
        },
        xaxis: {
          categories: graph1Categories,
          labels: {
            formatter: function (val) {
              return val
            }
          }
        },
        yaxis: {
          title: {
            text: undefined
          },
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val 
            }
          }
        },
        fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart1"), options);
        chart.render();
    </script>
	<script>
		let graph2dates = [];
		let graph2Ranks = [];
		<?php
		$currentGdate = 0;
		for($i=count($ta2['date'])-1;$i>=0;$i--){
			$gDate = new DateTime($ta2['date'][$i]);
			$gDate = $gDate->format('d.m.y');
			if($gDate!=$currentGdate){
				$currentGdate = $gDate;
				echo 'graph2dates.push("'.$currentGdate.'");';
			}else
				echo 'graph2dates.push("");';
			echo 'graph2Ranks.push("'.$ta2['elo'][$i].'");';
		}
		?>
        var options = {
          series: [{
            name: "Rating",
            data: graph2Ranks,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'Overall rating',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["<?php echo $lightDarkChartColor2; ?>", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: graph2dates
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart2"), options);
        chart.render();
    </script>
	<script>
		let graph3Categories = [];
		let graph3Solves = [];
		let graph3Fails = [];
		<?php
		foreach ($timeGraph as $key => $value){
			echo 'graph3Categories.push("'.$key.'");';
			if(!isset($value['s']))
				$value['s'] = 0;
			echo 'graph3Solves.push("'.$value['s'].'");';
			if(!isset($value['f']))
				$value['f'] = 0;
			echo 'graph3Fails.push("'.$value['f'].'");';
		}
		?>	
        var options = {
          series: [
		{
          name: 'Passes',
          data: graph3Solves,
		  color: '#c8723d'
        }, {
          name: 'Fails',
          data: graph3Fails,
		  color: '#888888'
        }],
        chart: {
          type: 'bar',
          height: <?php echo $countTimeGraph; ?>,
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          stacked: true,
        },
        plotOptions: {
          bar: {
            horizontal: true,
            dataLabels: {
              total: {
                enabled: true,
                offsetX: 0,
                style: {
                  fontSize: '13px',
                  fontWeight: 900,
				  color: "<?php echo $lightDarkChartColor; ?>"
                }
              }
            }
          },
        },
        stroke: {
          width: 1,
          colors: ['#fff']
        },
        title: {
          text: 'Time mode runs'
        },
        xaxis: {
          categories: graph3Categories,
          labels: {
            formatter: function (val) {
              return parseInt(val)
            }
          }
        },
        yaxis: {
          title: {
            text: undefined
          },
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val 
            }
          }
        },
        fill: {
          opacity: 1,
          colors: ['#c8723d', '#888888']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart3"), options);
        chart.render();
    </script>
	<script>
        let graph11Categories = [];
		let graph11Solves = [];
		let graph11Fails = [];
		<?php
		foreach ($graph as $key => $value){
			$gDate = new DateTime($key);
			$gDate = $gDate->format('d.m.y');
			echo 'graph11Categories.push("'.$gDate.'");';
			echo 'graph11Solves.push("'.$value['s'].'");';
			echo 'graph11Fails.push("'.$value['f'].'");';
		}
		?>	
        var options = {
          series: [
		{
          name: 'Solves',
          data: graph11Solves,
		  color: '#74d14c'
        }, {
          name: 'Fails',
          data: graph11Fails,
		  color: '#d63a49'
        }],
        chart: {
          type: 'bar',
          height: <?php echo $countGraph; ?>,
          stacked: true,
		  foreColor: "<?php echo $lightDarkChartColor; ?>"
        },
        plotOptions: {
          bar: {
            horizontal: true,
            dataLabels: {
              total: {
                enabled: true,
                offsetX: 0,
                style: {
                  fontSize: '13px',
                  fontWeight: 900,
				  color: "<?php echo $lightDarkChartColor; ?>"
                }
              }
            }
          },
        },
        stroke: {
          width: 1,
          colors: ['#fff']
        },
        title: {
          text: 'Problems in level mode'
        },
        xaxis: {
          categories: graph11Categories,
          labels: {
            formatter: function (val) {
              return val
            }
          }
        },
        yaxis: {
          title: {
            text: undefined
          },
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val 
            }
          }
        },
        fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart11"), options);
        chart.render();
    </script>
	<script>
		let graph22dates = [];
		let graph22Ranks = [];
		<?php
		$currentGdate = 0;
		for($i=count($ta2['date'])-1;$i>=0;$i--){
			$gDate = new DateTime($ta2['date'][$i]);
			$gDate = $gDate->format('d.m.y');
			if($gDate!=$currentGdate){
				$currentGdate = $gDate;
				echo 'graph22dates.push("'.$currentGdate.'");';
			}else
				echo 'graph22dates.push("");';
			echo 'graph22Ranks.push("'.$ta2['elo'][$i].'");';
		}
		?>
        var options = {
          series: [{
            name: "Rating",
            data: graph22Ranks,
			color: '#74d14c'
        }],
        chart: {
          height: 520,
          type: 'line',
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          zoom: {
            enabled: false
          }
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          curve: 'straight',
		  colors: ['#74d14c']
        },
        title: {
          text: 'Overall rating',
          align: 'left'
        },
        grid: {
          row: {
            colors: ["<?php echo $lightDarkChartColor2; ?>", 'transparent'],
            opacity: 0.5
          },
        },
        xaxis: {
          categories: graph22dates
        },
		fill: {
          opacity: 1,
          colors: ['#74d14c', '#d63a49']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart22"), options);
        chart.render();
    </script>
	<script>
        let graph33Categories = [];
		let graph33Solves = [];
		let graph33Fails = [];
		<?php
		foreach ($timeGraph as $key => $value){
			echo 'graph33Categories.push("'.$key.'");';
			if(!isset($value['s']))
				$value['s'] = 0;
			echo 'graph33Solves.push("'.$value['s'].'");';
			if(!isset($value['f']))
				$value['f'] = 0;
			echo 'graph33Fails.push("'.$value['f'].'");';
		}
		?>	
        var options = {
          series: [
		{
          name: 'Passes',
          data: graph3Solves,
		  color: '#c8723d'
        }, {
          name: 'Fails',
          data: graph3Fails,
		  color: '#888888'
        }],
        chart: {
          type: 'bar',
          height: <?php echo $countTimeGraph; ?>,
		  foreColor: "<?php echo $lightDarkChartColor; ?>",
          stacked: true,
        },
        plotOptions: {
          bar: {
            horizontal: true,
            dataLabels: {
              total: {
                enabled: true,
                offsetX: 0,
                style: {
                  fontSize: '13px',
                  fontWeight: 900,
				  color: "<?php echo $lightDarkChartColor; ?>"
                }
              }
            }
          },
        },
        stroke: {
          width: 1,
          colors: ['#fff']
        },
        title: {
          text: 'Time mode runs'
        },
        xaxis: {
          categories: graph33Categories,
          labels: {
            formatter: function (val) {
              return parseInt(val)
            }
          }
        },
        yaxis: {
          title: {
            text: undefined
          },
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return val 
            }
          }
        },
        fill: {
          opacity: 1,
          colors: ['#c8723d', '#888888']
        }
        };
        var chart = new ApexCharts(document.querySelector("#chart33"), options);
        chart.render();
    </script>
    <script>
    </script>
	<style>
	.new-button-time-inactive{
		cursor:pointer;
	}
	.userMain1{
		width:100%;
		height:138px;
		margin: 0 4px;
	}
	.userMain2{
		width:100%;
		height:254px;
		margin: 0 4px;
	}
	.userTop1{
		float:left;
		border: 3px solid #caa8d8;
		margin:2px;
		width:310px;
		height:205px;
	}
	.userTop2{
		float:left;
		border: 3px solid #74d14c;
		margin:2px;
		width:310px;
		height:148px;
	}
	.userTopTable1 td{
		vertical-align:top;padding:7px;
		text-align:left;
		width:50%;
	}
	.user-header-container{
		width:100%;
		height:50px;
	}
	.user-header1{
		width:50%;
		float:left;
	}
	.user-header2{
		width:50%;
		float:left;
		margin-top: 14px;
	}
	</style>
	
	

	
	
	
	
	
	
	
	
	

