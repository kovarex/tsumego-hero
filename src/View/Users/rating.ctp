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
	<caption><p class="title">Rating Highscore<br><br></p></caption>
	<tr>
		<th style="width:60px">Place</th>
		<th style="with:440px;text-align: left;" colspan="2">Name</th>
		<th style="width:150px;">Rank</th>
		<th style="width=90px;">Rating</th>
	</tr>
	<?php
		foreach ($users as $index => $user)
		{
			$user = $user['User'];
			$rank = Rating::getRankFromRating($user['rating']);
			$styleRank = Util::clampOptional($rank, Rating::getRankFromReadableRank('20k'), Rating::getRankFromReadableRank('9d'));
			$tableRowColor = 'color' . Rating::getReadableRank($styleRank);
			echo '<tr class="' . $tableRowColor . '">';
			echo '<td style="text-align:center;">#' . ($index + 1) . '</td>';
			echo '<td style="width:350px;align:left;">' . User::renderLinkWithOptionalRank($user) . '</td>';
			echo '<td style="width:90px;">' . User::renderPremium($user) . '</td>';
			echo '<td style="text-align:center;">' . Rating::getReadableRank($rank) . '</td>';
			echo '<td style="text-align:center;">' . round($user['rating']) . '</td>';
			echo '</tr>';
		}
	?>
</table>
</div>
