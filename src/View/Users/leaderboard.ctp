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
		foreach ($leaderboard as $order => $user) {
			$bgColor = '#fff';
			if ($order == 0) $bgColor = '#ffec85';
			if ($order == 1) $bgColor = '#939393';
			if ($order == 2) $bgColor = '#c28d47';
			if ($order == 3) $bgColor = '#85e35d';
			if ($order == 4) $bgColor = '#85e35d';
			if ($order == 5) $bgColor = '#85e35d';
			if ($order == 6) $bgColor = '#85e35d';
			if ($order == 7) $bgColor = '#85e35d';
			if ($order == 8) $bgColor = '#85e35d';
			if ($order == 9) $bgColor = '#85e35d';
			if ($order == 10) $bgColor = '#85e35d';
			if ($order == 11) $bgColor = '#85e35d';
			if ($order == 12) $bgColor = '#85e35d';
			if ($order == 13) $bgColor = '#85e35d';
			if ($order == 14) $bgColor = '#85e35d';
			if ($order == 15) $bgColor = '#85e35d';
			if ($order == 16) $bgColor = '#85e35d';
			if ($order == 17) $bgColor = '#85e35d';
			if ($order == 18) $bgColor = '#85e35d';
			if ($order == 19) $bgColor = '#85e35d';
			if ($order == 20) $bgColor = '#9cf974';
			if ($order == 21) $bgColor = '#9cf974';
			if ($order == 22) $bgColor = '#9cf974';
			if ($order == 23) $bgColor = '#9cf974';
			if ($order == 24) $bgColor = '#9cf974';
			if ($order == 25) $bgColor = '#9cf974';
			if ($order == 26) $bgColor = '#9cf974';
			if ($order == 27) $bgColor = '#9cf974';
			if ($order == 28) $bgColor = '#9cf974';
			if ($order == 29) $bgColor = '#9cf974';
			if ($order >= 30) $bgColor = '#b6f998';
			if ($order >= 40) $bgColor = '#d3f9c2';
			if ($order >= 50) $bgColor = '#e8f9e0';

			if (substr($user['name'],0,3) == 'g__' && $user['external_id'] != null) {
				$user['name'] = '<img class="google-profile-image" src="/img/google/'.$user['picture'].'">'.substr($user['name'], 3);
			}

			echo '
				<tr style="background-color:'.$bgColor.';">
					<td align="right" style="padding:10px;">
						<b>'.($order + 1).'</b>
					</td>
					<td style="padding:10px;" width="200px">
						<b>'.$user['name'].'</b>
					</td>
					<td align="right" style="padding:10px;font-weight:400;">
						'.$user['daily_solved'].' solved
					</td>
					<td align="right" style="padding:10px;">
						<b>'.$user['daily_xp'].' XP</b>
					</td>
				</tr>
			';
		}
	?>
	</table>
	<br><br>
	</div>
	<div align="center">
	<div class="accessList" style="font-weight:400;">
	Admins:
	<?php echo implode($admins); ?>
	<br><br>
	</div>
	</div>
