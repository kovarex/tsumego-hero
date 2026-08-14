<?php

/**
 * @var View $this
 * @var int $PAGE_SIZE
 * @var array $attempts
 * @var int $count
 * @var int $pageIndex
 */

echo PaginationHelper::render($pageIndex, intval(ceil($count / $PAGE_SIZE)), 'page');
echo '<table>';
echo '<thead><tr><td>Set</td><td>Tsumego</td><td>Solved</td><td>Misplays</td><td>Rating</td></td><td>XP gained</td><td>Date</td></tr>';
	foreach ($attempts as $attempt)
	{
		echo '<tr>';
		echo '<td>' . h($attempt['set_title']) . '</td>';
		echo '<td>';
		new TsumegoButton($attempt['tsumego_id'], $attempt['set_connection_id'], $attempt['num'], $attempt['status'] ?: 'N', 0, $attempt['sgf'])->render();
		echo '</td>';
		echo '<td>' . $attempt['solved'] . '</td>';
		echo '<td>' . $attempt['misplays'] . '</td>';
		echo '<td>' . round($attempt['user_rating']) . '</td>';
		echo '<td>' . $attempt['xp_gain'] . '</td>';
		echo '<td><time datetime="' . Util::toIso8601($attempt['created']) . '" data-format="datetime">' . $attempt['created'] . '</time></td>';
		echo '</tr>';
	}
echo '</table>';
