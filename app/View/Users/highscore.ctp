
<div align="center" class="highscore">

<table border="0" width="100%">
<tr>
	<td width="23%" valign="top">
	</td>
	<td width="53%" valign="top">
		<div align="center">
		<br>
		<a class="new-button buttonx-current" href="/users/highscore">level</a>
		<a class="new-button new-buttonx" href="/users/rating">rating</a>
		<a class="new-button new-buttonx" href="/users/achievements">achievements</a>
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
				Level Highscore
			<br><br> 
			</p>
		</div>
	</tr>
	<tr>
		<!--<th width="55px"></th>-->
		<th width="60px">Rank</th>
		<th width="220px" colspan="2" align="left">&nbsp;Name</th>
		<th width="150px">Level</th>
		<th width="150px">XP</th>
		<th width="90px" align="left">Solved</th>
	</tr>
	<?php
		$statsLink1 = '';
		$statsLink2 = '';
		$statsLink3 = '';
		if(isset($_SESSION['loggedInUser'])){if($_SESSION['loggedInUser']['User']['id']==72){
			$statsLink1 = '<a style="color:black;text-decoration:none;" target="_blank" href="/users/view';
			$statsLink2 = '">';
			$statsLink3 = '</a>';
		}}
		$place = 1;
		for($i=count($users)-1;$i>=0;$i--){
			if($users[$i]['solved'] == 0) $users[$i]['solved'] = 'missing data';
			$bgColor = '#dddddd';
			$statsLink4 = '';
			if(isset($_SESSION['loggedInUser'])){if($_SESSION['loggedInUser']['User']['id']==72){
				$statsLink4 = '/'.$users[$i]['id'];
			}}
			$uType = '';
			if($users[$i]['type']==1) $uType = '<img alt="Account Type" title="Account Type" src="/img/premium1.png" height="16px">';
			else if($users[$i]['type']==2) $uType = '<img alt="Account Type" title="Account Type" src="/img/premium2.png" height="16px">';
			if($i>989) $tableRowColor = 'color1';
			else if($i>969) $tableRowColor = 'color2';
			else if($i>939) $tableRowColor = 'color3';
			else if($i>899) $tableRowColor = 'color4';
			else if($i>799) $tableRowColor = 'color5';
			else if($i>699) $tableRowColor = 'color6';
			else if($i>599) $tableRowColor = 'color7';
			else if($i>499) $tableRowColor = 'color8';
			else if($i>399) $tableRowColor = 'color9';
			else if($i>299) $tableRowColor = 'color10';
			else if($i>199) $tableRowColor = 'color11';
			else if($i>99) $tableRowColor = 'color12x';
			else $tableRowColor = 'color13';

			echo '
				<tr class="'.$tableRowColor.'">
					<td align="center">
						#'.$place.'
					</td>
					
					<td  align="left">
						'.$statsLink1.$statsLink4.$statsLink2.$users[$i]['name'].' '.$statsLink3.'
					</td>
					<td>
						'.$uType.'
					</td>
					
					<td align="center">
						Level '.$users[$i]['level'].'
					</td>
					
					<td align="center">
						'.$users[$i]['xpSum'].' XP
					</td>
					<td align="left">
						'.$users[$i]['solved'].'
					</td>
				</tr>
			';
			$place++;
		}
	?>
	</table>
	<?php
	
	/*
	if($_SESSION['loggedInUser']['User']['id']==72){
		echo '<pre>';
		print_r($users2);
		echo '</pre>';
	}
	if(isset($_SESSION['loggedInUser'])){if($_SESSION['loggedInUser']['User']['id']==72){
		echo '<pre>';
		print_r($users);
		echo '</pre>';
	}}
	*/
	?>

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
	
	
	
