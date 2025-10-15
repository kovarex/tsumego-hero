	<div align="center" class="highscore">
	<table border="0" width="100%">
		<tr>
			<td width="23%" valign="top">
				<font size="3px" style="font-weight:400;">Signed in users today: <?php echo $uNum; ?></font>
			</td>
			<td width="53%" valign="top">
				<div align="center">
				<br>
				<a class="new-button new-buttonx" href="/users/highscore">level</a>
				<a class="new-button new-buttonx" href="/users/rating">rating</a>
				<a class="new-button new-buttonx" href="/users/achievements">achievements</a>
				<a class="new-button new-buttonx" href="/users/added_tags">tags</a>
				<a class="new-button buttonx-current" href="/users/leaderboard">daily</a>
				<br><br>
				</div>
			</td>
			<td width="23%" valign="top">
				<div align="right">	
				<font size="3px" style="font-weight:400;font-style:italic;">Users can be user of the day once per week.</font>
				</div>
			</td>
		</tr>
	</table>
	<table border="0" width="100%">
		<tr>
			<td width="33%" valign="top">
			</td>
			<td width="33%" valign="top">
				<div align="center">	
				<p class="title">
					Daily Highscore
				<br><br> 
				</p>
				</div>
			</td>
			<td width="33%" valign="top">
			</td>
		</tr>
	</table>
	<?php
	$d1 = date(' d, Y');
	$d1day = date('d. ');
	$d1year = date('Y');
	if($d1day[0]==0) $d1day = substr($d1day, -3);
	$d2 = date('Y-m-d H:i:s');
	$month = date("F", strtotime(date('Y-m-d')));
	$d1 = $d1day.$month.' '.$d1year;
	echo $d1;
	?>
	<br><br>
	<table class="dailyHighscoreTable">
	<?php
		for($i=0; $i<=count($a); $i++){
			if($a[$i]['reuse3']>0){
				$bgColor = '#fff';
				if($i==0) $bgColor = '#ffec85';
				if($i==1) $bgColor = '#939393';
				if($i==2) $bgColor = '#c28d47';
				if($i==3) $bgColor = '#85e35d';
				if($i==4) $bgColor = '#85e35d';
				if($i==5) $bgColor = '#85e35d';
				if($i==6) $bgColor = '#85e35d';
				if($i==7) $bgColor = '#85e35d';
				if($i==8) $bgColor = '#85e35d';
				if($i==9) $bgColor = '#85e35d';
				if($i==10) $bgColor = '#85e35d';
				if($i==11) $bgColor = '#85e35d';
				if($i==12) $bgColor = '#85e35d';
				if($i==13) $bgColor = '#85e35d';
				if($i==14) $bgColor = '#85e35d';
				if($i==15) $bgColor = '#85e35d';
				if($i==16) $bgColor = '#85e35d';
				if($i==17) $bgColor = '#85e35d';
				if($i==18) $bgColor = '#85e35d';
				if($i==19) $bgColor = '#85e35d';
				if($i==20) $bgColor = '#9cf974';
				if($i==21) $bgColor = '#9cf974';
				if($i==22) $bgColor = '#9cf974';
				if($i==23) $bgColor = '#9cf974';
				if($i==24) $bgColor = '#9cf974';
				if($i==25) $bgColor = '#9cf974';
				if($i==26) $bgColor = '#9cf974';
				if($i==27) $bgColor = '#9cf974';
				if($i==28) $bgColor = '#9cf974';
				if($i==29) $bgColor = '#9cf974';
				if($i>=30) $bgColor = '#b6f998';
				if($i>=40) $bgColor = '#d3f9c2';
				if($i>=50) $bgColor = '#e8f9e0';
				
				if(substr($a[$i]['name'],0,3)=='g__' && $a[$i]['external_id']!=null){
					$a[$i]['name'] = '<img class="google-profile-image" src="/img/google/'.$a[$i]['picture'].'">'.substr($a[$i]['name'],3);
				}

				echo '
					<tr style="background-color:'.$bgColor.';">
						<td align="right" style="padding:10px;">
							<b>'.($i+1).'</b>
						</td>
						<td style="padding:10px;" width="200px">
							<b>'.$a[$i]['name'].'</b>
						</td>
						<td align="right" style="padding:10px;font-weight:400;">
							'.$a[$i]['reuse2'].' solved
						</td>
						<td align="right" style="padding:10px;">
							<b>'.$a[$i]['reuse3'].' XP</b>
						</td>
					</tr>
				';
			}
		}
	?>
	</table>
	<br><br>
	</div>
	<div align="center">
	<div class="accessList" style="font-weight:400;">
	Admins: 
	<?php
		for($i=0; $i<count($admins); $i++){
			echo $admins[$i];
			if($i<count($admins)-1) echo ', ';
		}
	?>
	<br><br>
	</div>
	</div>
		<script>
		</script>
		
		
		
		
