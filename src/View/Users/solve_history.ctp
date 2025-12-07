<?php
echo PaginationHelper::render($pageIndex, intval(ceil($count / $PAGE_SIZE)), 'page');
echo '<table>';
echo '<thead><tr><td>Set</td><td>Tsumego</td><td>Solved</td><td>Misplays</td><td>Rating</td></td><td>XP gained</td><td>Date</td></tr>';
	foreach ($attempts as $attempt)
	{
		echo '<tr>';
		echo '<td>' . $attempt['set_title'] . '</td>';
		echo '<td>';
		new TsumegoButton($attempt['tsumego_id'], $attempt['set_connection_id'], $attempt['num'], $attempt['status'], false, false)->render();
		echo '</td>';
		echo '<td>' . $attempt['solved'] . '</td>';
		echo '<td>' . $attempt['misplays'] . '</td>';
		echo '<td>' . round($attempt['user_rating']) . '</td>';
		echo '<td>' . $attempt['xp_gain'] . '</td>';
		echo '<td>' . $attempt['created'] . '</td>';
		echo '</tr>';
	}
echo '</table>';
