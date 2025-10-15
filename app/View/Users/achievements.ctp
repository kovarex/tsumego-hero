
<div align="center" class="highscore">

<table border="0" width="100%">
<tr>
	<td width="23%" valign="top">
	</td>
	<td width="53%" valign="top">
		<div align="center">
		<br>
		<a class="new-button new-buttonx" href="/users/highscore">level</a>
		<a class="new-button new-buttonx" href="/users/rating">rating</a>
		<a class="new-button buttonx-current" href="/users/achievements">achievements</a>
		<a class="new-button new-buttonx" href="/users/added_tags">tags</a>
		<a class="new-button new-buttonx" href="/users/leaderboard">daily</a>
		<br><br>
		</div>
	</td>
	<td width="23%" valign="top">
		<div align="right">	
		</div>
	</td>
</table>

<table class="highscoreTable" border="0">
	<tr>
		<div align="center">	
				<p class="title">
					Achievement Highscore
				</p>
				</div>
	</tr>
	<tr>
	<br>
		<th width="60px">Place</th>
		<th width="220px" align="left">&nbsp;Name</th>
		<th width="150px">Completed</th>
	</tr>
	<?php
		$place = 1;
		for($i=count($uaNum)-1; $i>=count($uaNum)-100; $i--){
			echo '
				<tr class="color9d">';
					echo '
					<td align="center">
						#'.$place.'
					</td>
					<td>
						'.$uName[$i].'
					</td>
					<td align="center">
						'.$uaNum[$i].'/'.$aNum.'
					</td>
				</tr>
			';
			$place++;
		}
	?>
	</table>
	</div>
	<br><br><br><br><br><br>

	<script>
	</script>
	<?php
	/* if($i==3) $bgColor = '#9e61b6';
			if($i==4) $bgColor = '#9e61b6';
			if($i==5) $bgColor = '#9e61b6';
			if($i==6) $bgColor = '#9e61b6';
			if($i==7) $bgColor = '#9e61b6';
			if($i==8) $bgColor = '#9e61b6';
			if($i==9) $bgColor = '#9e61b6';
			if($i==10) $bgColor = '#ba84cf';
			if($i==11) $bgColor = '#b57dca';
			if($i==12) $bgColor = '#c08ad5';
			if($i==13) $bgColor = '#cb98df';
			if($i==14) $bgColor = '#d1a5e4';
			if($i==15) $bgColor = '#d1aae3';
			if($i==16) $bgColor = '#d2ade3';
			if($i==17) $bgColor = '#d2b3e3';
			if($i==18) $bgColor = '#d2b5e2';
			if($i==19) $bgColor = '#d2bbe2';
			if($i==20) $bgColor = '#d2bee2';
			if($i==21) $bgColor = '#d2c3e1';
			if($i==22) $bgColor = '#d2c1e2';
			if($i==23) $bgColor = '#d2c6e1';
			if($i==24) $bgColor = '#d2c9e1';
			if($i==25) $bgColor = '#d3cce1';
			if($i==26) $bgColor = '#d3cfe1';
			if($i==27) $bgColor = '#d3d1e0';
			if($i==28) $bgColor = '#d3d4e0';
			if($i==29) $bgColor = '#d3d7e0';
				 if($i==3) $bgColor = '#8846a1';
			if($i==4) $bgColor = '#8d4da6';
			if($i==5) $bgColor = '#9354ab';
			if($i==6) $bgColor = '#985ab0';
			if($i==7) $bgColor = '#9e61b6';
			if($i==8) $bgColor = '#a468bb';
			if($i==9) $bgColor = '#a96fc0';
			if($i==10) $bgColor = '#af76c5';
			if($i==11) $bgColor = '#ba84cf';
			if($i==12) $bgColor = '#c691da';
			if($i==13) $bgColor = '#d19fe4';
			if($i==14) $bgColor = '#d1a5e4';
			if($i==15) $bgColor = '#d1aae3';
			if($i==16) $bgColor = '#d2ade3';
			if($i==17) $bgColor = '#d2b3e3';
			if($i==18) $bgColor = '#d2b5e2';
			if($i==19) $bgColor = '#d2bbe2';
			if($i==20) $bgColor = '#d2bee2';
			if($i==21) $bgColor = '#d2c3e1';
			if($i==22) $bgColor = '#d2c1e2';
			if($i==23) $bgColor = '#d2c6e1';
			if($i==24) $bgColor = '#d2c9e1';
			if($i==25) $bgColor = '#d3cce1';
			if($i==26) $bgColor = '#d3cfe1';
			if($i==27) $bgColor = '#d3d1e0';
			if($i==28) $bgColor = '#d3d4e0';
			if($i==29) $bgColor = '#d3d7e0';
			*/
	?>
	
	
	
