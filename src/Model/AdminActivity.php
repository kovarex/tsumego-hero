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
			case AdminActivityType::ACCEPT_TAG: return 'Accepted tag ' . $adminActivity['new_value'];
			case AdminActivityType::REJECT_TAG: return 'Rejected tag ' . $adminActivity['old_value'];
			case AdminActivityType::TSUMEGO_MERGE:
			{
				$decoded = json_decode($adminActivity['old_value'], true);
				if (json_last_error() !== JSON_ERROR_NONE ||
					!is_array($decoded))
					return 'Merged tsumego ' . $adminActivity['old_value'];
				$onMouseOver = 'if (this.querySelector(\'svg\')) return;';
				$onMouseOver .= TsumegoButton::createBoardFromSgf($decoded['sgf'], 'this', 'createPreviewBoard');
				$result = 'Merged ';
				$result .= '<a style="position: relative;" onmouseover="' . $onMouseOver . '">tsumego<span class="tooltip-box"></span></a>';
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
					return $adminActivity['readable_type'] . ': [Empty]  → ' . $adminActivity['new_value'];
				}
				return $adminActivity['readable_type'];
		}
	}
}
