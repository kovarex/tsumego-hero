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
	 * Only the value columns need to be specified per page.
	 *
	 * @param string $title Table title HTML
	 * @param array $rows Data rows (each must have 'position' key)
	 * @param array $valueColumns Value column definitions: [['label' => string, 'render' => callable($row)], ...]
	 * @param int|null $totalUsers Total user count shown in footer (null to hide)
	 * @return string Complete HTML table
	 */
	public static function renderTable(
		string $title,
		array $rows,
		array $valueColumns,
		?int $totalUsers = null,
	): string {
		$totalColumns = 3 + count($valueColumns); // Place + Name + Premium + values

		$html = '<table class="highscoreTable" border="0">';

		// Caption
		$html .= '<caption><p class="title" style="margin-bottom: 16px;">' . $title . '</p>';
		$html .= '</caption>';

		// Header row
		$html .= '<tr>';
		$html .= '<th width="60px">Place</th>';
		$html .= '<th width="220px" align="left">Name</th>';
		$html .= '<th width="60px">Premium</th>';
		foreach ($valueColumns as $col)
			$html .= '<th>' . $col['label'] . '</th>';

		$html .= '</tr>';

		// Data rows
		$prevPosition = 0;
		foreach ($rows as $row)
		{
			$pos = $row['position'];

			// Gap separator for non-consecutive positions
			if ($pos - $prevPosition > 1 && $prevPosition > 0)
				$html .= '<tr class="color-separator"><td colspan="' . $totalColumns . '" align="center">⋮</td></tr>';

			// Medal colors for top 3
			$medalClass = '';
			if ($pos == 1)
				$medalClass = ' medal-gold';
			elseif ($pos == 2)
				$medalClass = ' medal-silver';
			elseif ($pos == 3)
				$medalClass = ' medal-bronze';

			// Self-view highlighting
			$selfClass = '';
			if (Auth::isLoggedIn())
			{
				$me = Auth::getUser();
				$rowId = $row['id'] ?? null;
				if ($me && $rowId !== null && $rowId == $me['id'])
					$selfClass = ' color-self';
			}

			$name = User::renderLink($row);

			$html .= '<tr class="' . trim($selfClass . $medalClass) . '">';
			$html .= '<td align="center">#' . $pos . '</td>';
			$html .= '<td align="left">' . $name . '</td>';
			$html .= '<td align="center">' . User::renderPremium($row) . '</td>';
			foreach ($valueColumns as $col)
				$html .= '<td align="center">' . $col['render']($row) . '</td>';

			$html .= '</tr>';
			$prevPosition = $pos;
		}

		// Footer with total user count
		if ($totalUsers !== null)
			$html .= '<tr class="color-separator"><td colspan="' . $totalColumns . '" align="center" style="font-size:13px;color:#999;padding:8px 0;">' . $totalUsers . ' users total</td></tr>';

		$html .= '</table>';
		return $html;
	}
}
