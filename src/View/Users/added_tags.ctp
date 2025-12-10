
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
					<p class="title">Tags added</p>
				</div>
			</td>
			<td width="33%" valign="top">
			</td>
		</tr>
	</table>
	<br><br>
	<table class="dailyHighscoreTable">
	<?php
	foreach ($tagContributors as $index => $contributor)
	{
		echo '<tr style="background-color:' . Util::smallScoreTableRowColor($index) . ';">
				<td align="right" style="padding:10px;">
					<b>' . ($index + 1) . '</b>
				</td>
				<td style="padding:10px;" width="200px">';
				echo '<b>' . $contributor['name'].'</b>';
				echo '</td>
				<td align="right" style="padding:10px;">
					<b>' . $contributor['tag_count'] . '</b>
				</td>
			</tr>';
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




