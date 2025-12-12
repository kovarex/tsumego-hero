
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
		$place = 1;
		foreach ($users as $index => $user)
		{
			$order = $index + 1;
			$user = $user['User'];
			if($user['solved'] == 0) $user['solved'] = 'missing data';
			$bgColor = '#dddddd';
			$statsLink4 = '';
			if(Auth::isLoggedIn()){if(Auth::getUserID()==72){
				$statsLink4 = '/'.$user['id'];
			}}
			$uType = '';
			if($user['type']==1)
				$uType = '<img alt="Account Type" title="Account Type" src="/img/premium1.png" height="16px">';
			else if($user['type']==2)
				$uType = '<img alt="Account Type" title="Account Type" src="/img/premium2.png" height="16px">';
			if ($order == 1)
				$tableRowColor = 'color1';
			elseif ($order <= 4)
				$tableRowColor = 'color2';
			elseif ($order <= 50)
				$tableRowColor = 'color3';
			elseif ($order <= 100)
				$tableRowColor = 'color4';
			elseif($order <= 200)
				$tableRowColor = 'color5';
			elseif($order <= 300)
				$tableRowColor = 'color6';
			elseif($order <= 400)
				$tableRowColor = 'color7';
			elseif($order <= 500)
				$tableRowColor = 'color8';
			elseif($order <= 600)
				$tableRowColor = 'color9';
			elseif($order <= 700)
				$tableRowColor = 'color10';
			elseif($order <= 800)
				$tableRowColor = 'color11';
			elseif($order <= 900)
				$tableRowColor = 'color12x';
			else
				$tableRowColor = 'color13';

			echo '
				<tr class="'.$tableRowColor.'">
					<td align="center">
						#'.$place.'
					</td>

					<td  align="left">
						' . User::renderLink($user['id'], $user['name'], $user['external_id'], $user['picture'],  $user['rating']) . '
					</td>
					<td>
						'.$uType.'
					</td>

					<td align="center">
						Level '.$user['level'].'
					</td>

					<td align="center">
						'.Level::getOverallXPGained($user).' XP
					</td>
					<td align="left">
						'.$user['solved'].'
					</td>
				</tr>
			';
			$place++;
		}
	?>
	</table>
	<?php

	/*
	if(Auth::getUserID()==72){
		echo '<pre>';
		print_r($users2);
		echo '</pre>';
	}
	if(Auth::isLoggedIn()){if(Auth::getUserID()==72){
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



