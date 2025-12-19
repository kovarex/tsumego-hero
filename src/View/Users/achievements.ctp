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
		<caption><p class="title">Achievement Highscore</p></caption>
		<tr>
			<th style="width:60px;">Place</th>
			<th style="width:220px;text-align:left;">Name</th>
			<th style="width:150px">Completed</th>
		</tr>
		<?php
			foreach ($users as $index => $user)
			{
				echo '<tr class="color9d">';
				echo '<td style="text-align:center">#' . ($index + 1) . '</td>';
				echo '<td>' . User::renderLink($user) . '</td>';
				echo '<td style="text-align:center;">' . $user['achievement_score'].'/' . Achievement::COUNT . '</td>';
				echo '</tr>';
			}
		?>
	</table>
</div>
