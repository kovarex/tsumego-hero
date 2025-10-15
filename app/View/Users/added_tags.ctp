
	<div align="center" class="highscore">
	
	<table border="0" width="100%">
		<tr>
			<td width="23%" valign="top">
				<font size="3px" style="font-weight:400;"></font>
			</td>
			<td width="53%" valign="top">
				<div align="center">
				<br>
				<a class="new-button new-buttonx" href="/users/highscore">level</a>
				<a class="new-button new-buttonx" href="/users/rating">rating</a>
				<a class="new-button new-buttonx" href="/users/achievements">achievements</a>
				<a class="new-button buttonx-current" href="/users/added_tags">tags</a>
				<a class="new-button new-buttonx" href="/users/leaderboard">daily</a>
				<br><br>
				</div>
			</td>
			<td width="23%" valign="top">
				<div align="right">	
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
					Contribution points
				</p>
				</div>
			</td>
			<td width="33%" valign="top">
			</td>
		</tr>
	</table>
	<br><br>
	<table class="dailyHighscoreTable">
	<?php
		$showLinks = false;
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			if($_SESSION['loggedInUser']['User']['id']==72){
				$showLinks = true;
			}
		}
		for($i=0; $i<count($a); $i++){
			if($a[$i]['name'] != ''){
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
				echo '
					<tr style="background-color:'.$bgColor.';">
						<td align="right" style="padding:10px;">
							<b>'.($i+1).'</b>
						</td>
						<td style="padding:10px;" width="200px">';
						if(!$showLinks)
							echo '<b>'.$a[$i]['name'].'</b>';
						else
							echo '<a href="/tag_names/user/'.$a[$i]['id'].'" style="color:black"><b>'.$a[$i]['name'].'</b></a>';
						echo '</td>
						<td align="right" style="padding:10px;">
							<b>'.$a[$i]['score'].'</b>
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
	<br><br>
	</div>
	</div>
		<script>
		</script>
		
		
		
		
