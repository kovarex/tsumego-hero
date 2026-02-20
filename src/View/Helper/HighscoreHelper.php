<?php

App::uses('AppHelper', 'View/Helper');

/**
 * HighscoreHelper - Renders highscore tables with standardized layout.
 *
 * Every highscore table has the same structure:
 *   Place | Name+Rank (with premium badge) | ...value columns...
 *
 * Gap rows (⋮) and self-view highlighting are handled automatically.
 */
class HighscoreHelper extends AppHelper
{
	/**
	 * Render a complete highscore table.
	 *
	 * Standard columns (Place, Name+Rank+Premium) are always included.
	 * Only the value columns and color logic need to be specified per page.
	 *
	 * @param string $title Table title HTML
	 * @param array $rows Data rows (each must have 'position' key)
	 * @param array $valueColumns Value column definitions: [['label' => string, 'render' => callable($row)], ...]
	 * @param callable|null $colorClass Color function: fn($row, $pos) => CSS class string (applied to <tr>)
	 * @param string $tableClass CSS class for table (default: 'highscoreTable')
	 * @param callable|null $rowStyle Inline style function: fn($row, $pos) => CSS string (e.g. for background colors)
	 * @return string Complete HTML table
	 */
	public static function renderTable(
		string $title,
		array $rows,
		array $valueColumns,
		?callable $colorClass = null,
		string $tableClass = 'highscoreTable',
		?callable $rowStyle = null,
	): string {
		$totalColumns = 3 + count($valueColumns); // Place + Name + Premium + values

		$html = '<table class="' . $tableClass . '" border="0">';

		// Caption
		$html .= '<caption><p class="title" style="margin-bottom: 16px;">' . $title . '</p>';
		$html .= '</caption>';

		// Header row
		$html .= '<tr>';
		$html .= '<th width="60px">Place</th>';
		$html .= '<th width="220px" align="left">Name</th>';
		$html .= '<th width="60px">Premium</th>';
		foreach ($valueColumns as $col)
			$html .= '<th width="150px">' . $col['label'] . '</th>';

		$html .= '</tr>';

		// Data rows
		$prevPosition = 0;
		foreach ($rows as $row)
		{
			$pos = $row['position'];

			// Gap separator for non-consecutive positions
			if ($pos - $prevPosition > 1 && $prevPosition > 0)
				$html .= '<tr class="color-separator"><td colspan="' . $totalColumns . '" align="center">⋮</td></tr>';

			// Self-view highlighting
			$selfClass = '';
			if (Auth::isLoggedIn())
			{
				$me = Auth::getUser();
				$rowId = $row['id'] ?? $row['user_id'] ?? null;
				if ($me && $rowId !== null && $rowId == $me['id'])
					$selfClass = ' color-self';
			}

			$name = User::renderLink($row);
			$color = $colorClass ? $colorClass($row, $pos) : '';
			$rowStyleAttr = $rowStyle ? ' style="' . $rowStyle($row, $pos) . '"' : '';

			$html .= '<tr class="' . trim($color . $selfClass) . '"' . $rowStyleAttr . '>';
			$html .= '<td align="center">#' . $pos . '</td>';
			$html .= '<td align="left">' . $name . '</td>';
			$html .= '<td align="center">' . User::renderPremium($row) . '</td>';
			foreach ($valueColumns as $col)
				$html .= '<td align="center">' . $col['render']($row) . '</td>';

			$html .= '</tr>';
			$prevPosition = $pos;
		}

		$html .= '</table>';
		return $html;
	}
}
