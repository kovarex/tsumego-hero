<?php

class AdminActivity extends AppModel
{
	public $useTable = 'admin_activity';
	public $actsAs = ['Containable'];

	public $belongsTo = [
		'AdminActivityType' => [
			'className' => 'AdminActivityType',
			'foreignKey' => 'type',
			'fields' => ['name']
		]
	];

	public static function renderChange($adminActivity)
	{
		switch ($adminActivity['type'])
		{
			case AdminActivityType::ACCEPT_TAG: return 'Accepted tag ' . h($adminActivity['new_value']);
			case AdminActivityType::REJECT_TAG: return 'Rejected tag ' . h($adminActivity['old_value']);
			case AdminActivityType::ADD_TAG: return 'Added tag ' . h($adminActivity['new_value']);
			case AdminActivityType::DELETE_USER: return 'Deleted user ' . h($adminActivity['old_value']);
			case AdminActivityType::ACCEPT_PROPOSAL: return 'Accepted proposal by ' . h($adminActivity['new_value']);
			case AdminActivityType::REJECT_PROPOSAL: return 'Rejected proposal by ' . h($adminActivity['new_value']);
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
