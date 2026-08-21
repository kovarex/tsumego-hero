<?php

/**
 * @var View $this
 * @var int $PAGE_SIZE
 * @var array $attempts
 * @var int $count
 * @var int $pageIndex
 * @var int $userID
 */

echo '<div style="padding: 0 16px">';
echo '<div class="subnav" style="margin-bottom: 12px">';
echo '<a href="/users/view/' . $userID . '" class="subnav__link">Profile</a>';
echo '<a href="/users/solveHistory/' . $userID . '" class="subnav__link subnav__link--active">Solve History</a>';
echo '<a href="/tags/user/' . $userID . '" class="subnav__link">Contributions</a>';
echo '<a href="/achievements/user/' . $userID . '" class="subnav__link">Achievements</a>';
echo '</div>';
echo PaginationHelper::render($pageIndex, intval(ceil($count / $PAGE_SIZE)), 'page');
echo '<table class="data-table" style="margin: 12px 0">';
echo '<thead><tr><th>Set</th><th>Tsumego <label style="cursor:pointer;font-size:11px;color:#888;font-weight:normal;text-transform:none" title="Toggle board previews"><input type="checkbox" id="preview-zoom-slider" style="vertical-align:middle;margin-right:2px">🔍</label></th><th>Solved</th><th>Misplays</th><th>Rating</th><th>XP gained</th><th>Date</th></tr></thead>';
echo '<tbody>';
foreach ($attempts as $attempt)
{
	echo '<tr>';
	if ($attempt['set_connection_id'])
	{
		echo '<td><a href="/sets/view/' . (int) $attempt['set_id'] . '">' . h($attempt['set_title']) . '</a></td>';
		echo '<td>';
		new TsumegoButton($attempt['tsumego_id'], $attempt['set_connection_id'], $attempt['num'], $attempt['status'] ?: 'N', 0, $attempt['sgf'])->render();
		echo '</td>';
	}
	else
	{
		echo '<td>' . h($attempt['set_title'] ?? 'Unknown') . '</td>';
		echo '<td>' . (int) $attempt['tsumego_id'] . '</td>';
	}
	echo '<td>' . ($attempt['solved'] ? '<span style="color:var(--color-green)">✓</span>' : '<span style="color:var(--color-red)">✗</span>') . '</td>';
	echo '<td>' . $attempt['misplays'] . '</td>';
	echo '<td>' . round($attempt['user_rating']) . '</td>';
	echo '<td>' . $attempt['xp_gain'] . '</td>';
	echo '<td><time datetime="' . Util::toIso8601($attempt['created']) . '" data-format="datetime">' . $attempt['created'] . '</time></td>';
	echo '</tr>';
}
echo '</tbody></table>';
echo PaginationHelper::render($pageIndex, intval(ceil($count / $PAGE_SIZE)), 'page');
echo '</div>';
