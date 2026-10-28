<?php

namespace App\Model\Table;

use AdminActivityType;
use App\Utility\BoardComparator;
use App\Utility\SgfBoard;
use App\Utility\SgfParser;
use App\Utility\TsumegoButton;

//What an admin did, for the activity log of the admin area (AdminActivityRenderer); written
//through AdminActivityLogger. type is an AdminActivityType id.
class AdminActivityTable extends AppTable
{
	//The change of a row of the activity log as HTML, the row with readable_type, the name of its type.
	/** @param array<string, mixed> $adminActivity */
	public static function renderChange(array $adminActivity): string
	{
		switch ($adminActivity['type'])
		{
			case AdminActivityType::ACCEPT_TAG: return 'Accepted tag ' . h($adminActivity['new_value']);
			case AdminActivityType::REJECT_TAG: return 'Rejected tag ' . h($adminActivity['old_value']);
			case AdminActivityType::ADD_TAG: return 'Added tag ' . h($adminActivity['new_value']);
			case AdminActivityType::DELETE_TAG:
				{
					$deleted = json_decode((string) $adminActivity['old_value'], true);
					$problemCount = count($deleted['connections'] ?? []);
					return 'Deleted tag ' . h($deleted['tag']['name'] ?? '') . ' (' . $problemCount . ' ' . ($problemCount === 1 ? 'problem' : 'problems') . ')';
				}
			case AdminActivityType::DELETE_USER: return 'Deleted user ' . h($adminActivity['old_value']);
			case AdminActivityType::RESTORE_USER: return 'Restored user ' . h($adminActivity['new_value']);
			case AdminActivityType::ROLE_CHANGE:
				{
					$old = json_decode((string) $adminActivity['old_value'], true);
					return 'Changed the role of <a href="/users/view/' . (int) $old['user_id'] . '">' . h($old['display_name']) . '</a>'
						. ' from ' . h($old['role']) . ' to ' . h($adminActivity['new_value']);
				}
			case AdminActivityType::SET_DELETE: return 'Moved collection ' . h($adminActivity['old_value']) . ' to the trash';
			case AdminActivityType::SET_RESTORE: return 'Restored collection ' . h($adminActivity['new_value']) . ' from the trash';
			case AdminActivityType::ACCEPT_PROPOSAL: return 'Accepted proposal by ' . h($adminActivity['new_value']);
			case AdminActivityType::REJECT_PROPOSAL: return 'Rejected proposal by ' . h($adminActivity['new_value']);
			case AdminActivityType::REOPEN_PROPOSAL: return 'Reopened rejected proposal by ' . h($adminActivity['new_value']);
			case AdminActivityType::TSUMEGO_MERGE:
				{
					$decoded = json_decode($adminActivity['old_value'], true);
					if (json_last_error() !== JSON_ERROR_NONE
						|| !is_array($decoded)
						|| !isset($decoded['master_sgf']))
							return 'Merged tsumego ' . $adminActivity['old_value'];

					$masterCorrectMoves = SgfBoard::decodePositionString($decoded['master_correct_moves']);
					$masterSgf = SgfParser::process($decoded['master_sgf'], $masterCorrectMoves);
					$slaveCorrectMoves = SgfBoard::decodePositionString($decoded['slave_correct_moves']);
					$slaveSgf = SgfParser::process($decoded['slave_sgf'], $slaveCorrectMoves);
					$comparisonResult = BoardComparator::compare(
						$masterSgf->stones,
						$decoded['master_first_move_color'],
						$masterCorrectMoves,
						$slaveSgf->stones,
						$decoded['slave_first_move_color'],
						$slaveCorrectMoves);

					$masterPreview = TsumegoButton::sgfToPreviewData($decoded['master_sgf']);
					$slavePreview = TsumegoButton::sgfToPreviewData($decoded['slave_sgf']);
					$onMouseOver = 'if (this.querySelector(\'svg\')) return;';
					if ($masterPreview)
						$onMouseOver .= 'createPreviewBoard(this,\'' . $masterPreview['black'] . '\',\'' . $masterPreview['white'] . '\',' . $masterPreview['xMax'] . ',' . $masterPreview['yMax'] . ',' . $masterPreview['boardSize'] . ');';
					if ($slavePreview)
					{
						$diff = $comparisonResult->diff ?? '';
						$onMouseOver .= 'createPreviewBoard(this,\'' . $slavePreview['black'] . '\',\'' . $slavePreview['white'] . '\',' . $slavePreview['xMax'] . ',' . $slavePreview['yMax'] . ',' . $slavePreview['boardSize'] . ',\'' . $diff . '\');';
					}
					$result = 'Merged ';
					$result .= '<a style="position: relative;" onmouseover="' . htmlspecialchars($onMouseOver, ENT_QUOTES, 'UTF-8') . '">tsumego<span class="tooltip-box"></span></a>';
					return $result;
				}
			default:
				if (!empty($adminActivity['old_value']) && !empty($adminActivity['new_value']))
					return $adminActivity['readable_type'] . ': ' . h($adminActivity['old_value']) . ' → ' . h($adminActivity['new_value']);
				if (!empty($adminActivity['new_value']))
				{
					if ($adminActivity['new_value'] === '1')
						return $adminActivity['readable_type'] . ' → enabled';
					/** @phpstan-ignore-next-line */
					if ($adminActivity['new_value'] === '0')
						return $adminActivity['readable_type'] . ' → disabled';
					return $adminActivity['readable_type'] . ': [Empty]  → ' . h($adminActivity['new_value']);
				}
				return $adminActivity['readable_type'];
		}
	}
}
