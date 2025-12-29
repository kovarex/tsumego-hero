<?php

App::uses('DataTableRenderer', 'Utility');

class TsumegoAttemptsRenderer extends DataTableRenderer
{
	public function __construct($urlParams, int $tsumegoID)
	{
		$this->count = Util::query("SELECT COUNT(*) as total FROM tsumego_attempt WHERE tsumego_id = ?", [$tsumegoID])[0]['total'];
		parent::__construct($urlParams, 'tsumego_attempts_page', 'Tsumego attempts');
		$this->data = Util::query("
SELECT
	tsumego_attempt.solved as solved,
	tsumego_attempt.misplays as misplays,
	tsumego_attempt.tsumego_rating as tsumego_rating,
	tsumego_attempt.created as created,
	user.name as user_name,
	user.id as user_id,
	user.rating as user_rating
FROM
	tsumego_attempt
	JOIN user ON user.id = tsumego_attempt.user_id
WHERE tsumego_attempt.tsumego_id = ?
ORDER BY tsumego_attempt.created DESC
LIMIT " . self::$PAGE_SIZE . "
OFFSET " . $this->offset, [$tsumegoID]);
	}

	public function renderItem(int $index, array $item): void
	{
		echo '<td>' . User::renderLink($item) . '</td>';
		echo '<td>' . $item['solved'] . '</td>';
		echo '<td>' . $item['misplays'] . '</td>';
		echo '<td>' . $item['tsumego_rating'] . '</td>';
		echo '<td>' . $item['created'] . '</td>';
	}

	protected function renderHeader(): void
	{
		echo '<thead>';
		echo '<td>User</td>';
		echo '<td>Solved</td>';
		echo '<td>Misplays</td>';
		echo '<td>Rating</td>';
		echo '<td>Datetime</td>';
		echo '</thead>';
	}
}
