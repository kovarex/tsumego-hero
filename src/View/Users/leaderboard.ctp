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
		foreach ($leaderboard as $index => $user)
			echo '
				<tr style="background-color:' . Util::smallScoreTableRowColor($index) . ';">
					<td align="right" style="padding:10px;">
						<b>'.($index + 1).'</b>
					</td>
					<td style="padding:10px;" width="200px">
						<b>' . User::renderLink($user['id'], $user['name'], $user['external_id'], $user['picture'], $user['rating']) . '</b>
					</td>
					<td align="right" style="padding:10px;font-weight:400;">
						'.$user['daily_solved'].' solved
					</td>
					<td align="right" style="padding:10px;">
						<b>'.$user['daily_xp'].' XP</b>
					</td>
				</tr>';
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
