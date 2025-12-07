
<div align="center" class="highscore">

<table border="0" width="100%">
<tr>
	<td width="23%" valign="top"></td>
	<td width="53%" valign="top">
		<div align="center">
		<br>
		<a class="new-button new-buttonx" href="/users/highscore">level</a>
		<a class="new-button buttonx-current" href="/users/rating">rating</a>
		<a class="new-button new-buttonx" href="/users/achievements">achievements</a>
		<a class="new-button new-buttonx" href="/users/added_tags">tags</a>
		<a class="new-button new-buttonx" href="/users/leaderboard">daily</a>
		<br><br>
		</div>
	</td>
	<td width="23%" valign="top">
		<div align="right"></div>
	</td>
</table>


<table class="highscoreTable" border="0">
	<tr>
		<div align="center">
				<p class="title">
					Rating Highscore
				<br><br>
				</p>
		</div>
	</tr>
	<tr>
		<!--<th width="55px"></th>-->
		<th width="60px">Place</th>
		<th width="220px" colspan="2" align="left">&nbsp;Name</th>
		<th width="150px">Rank</th>
		<th width="150px">Rating</th>
		<th width="150px">Solved in rating mode</th>
	</tr>
	<?php
		foreach ($users as $index => $user)
		{
			$user = $user['User'];
			if (substr($user['name'], 0, 3) == 'g__' && $user['external_id'])
				$user['name'] = '<img class="google-profile-image" src="/img/google/' . $user['picture'] . '">' . substr($user['name'], 3);
			elseif (strlen($user['name']) > 20)
				$user['name'] = substr($user['name'], 0, 20);

			$uType = '';
			if ($user['premium'] == 1)
				$uType = '<img alt="Account Type" title="Account Type" src="/img/premium1.png" height="16px">';
			else if($user['premium'] == 2)
				$uType = '<img alt="Account Type" title="Account Type" src="/img/premium2.png" height="16px">';

			$rank = Rating::getRankFromRating($user['rating']);
			$styleRank = Util::clampOptional($rank, Rating::getRankFromReadableRank('9d'), Rating::getRankFromReadableRank('20k'));
			$tableRowColor = 'color' . Rating::getReadableRank($styleRank);

			echo '<tr class="'.$tableRowColor.'">';
			echo '<td align="center">#' . ($index + 1) . '</td>';
			echo '<td width="225px" align="left">'.$user['name'].'</td>';
			echo '<td width="90px">'.$uType.'</td>';
			echo '<td align="center">' . Rating::getReadableRank($rank) . '</td>';
			echo '<td align="center">'.round($user['rating']).'</td>';
			echo '<td align="center">'.$user['solved2'].'</td>';
			echo '</tr>';
		}
	?>
</table>
</div>
