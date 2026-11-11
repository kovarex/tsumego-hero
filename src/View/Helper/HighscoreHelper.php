<?php

namespace App\View\Helper;

//HighscoreHelper - Renders highscore tables with standardized layout.
//Every highscore table has the same structure:
//  Place | Name+Rank (with premium badge) | ...value columns...
//Gap rows (⋮) and self-view highlighting are handled automatically.
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

class HighscoreHelper extends AppHelper
{
	//Render a complete highscore table.
	//Standard columns (Place, Name+Rank+Premium) are always included.
	//Only the value columns need to be specified per page.
	//$title: Table title HTML
	//$rows: Data rows (each must have 'position' key)
	//$valueColumns: Value column definitions: [['label' => string, 'render' => callable($row)], ...]
	//$totalUsers: Total user count shown in footer (null to hide)
	//Returns complete HTML table
	public static function renderTable(
		string $title,
		array $rows,
		array $valueColumns,
		?int $totalUsers = null,
	): string {
		$totalColumns = 3 + count($valueColumns); // Place + Name + Premium + values

		$html = '<table class="data-table">';

		// Caption
		$html .= '<caption><p class="title highscore-table__title">' . $title . '</p>';
		$html .= '</caption>';

		// Header row
		$html .= '<tr>';
		$html .= '<th class="highscore-table__place data-table__num">Place</th>';
		$html .= '<th class="highscore-table__name data-table__name legacy-left">Name</th>';
		$html .= '<th class="highscore-table__premium data-table__optional">Premium</th>';
		foreach ($valueColumns as $col)
			$html .= '<th class="data-table__num">' . $col['label'] . '</th>';

		$html .= '</tr>';

		// Data rows
		$prevPosition = 0;
		foreach ($rows as $row)
		{
			$pos = $row['position'];

			// Gap separator for non-consecutive positions
			if ($pos - $prevPosition > 1 && $prevPosition > 0)
				$html .= '<tr class="data-table__row--separator"><td colspan="' . $totalColumns . '" class="legacy-center">⋮</td></tr>';

			// Medal colors for top 3
			$medalClass = '';
			if ($pos == 1)
				$medalClass = ' data-table__row--medal-gold';
			elseif ($pos == 2)
				$medalClass = ' data-table__row--medal-silver';
			elseif ($pos == 3)
				$medalClass = ' data-table__row--medal-bronze';

			// Self-view highlighting
			$selfClass = '';
			if (Auth::isLoggedIn())
			{
				$me = Auth::getUser();
				$rowId = $row['id'] ?? null;
				if ($me && $rowId !== null && $rowId == $me['id'])
					$selfClass = ' data-table__row--self';
			}

			$name = UserTable::renderLink($row);

			$html .= '<tr class="' . trim($selfClass . $medalClass) . '">';
			$html .= '<td class="legacy-center data-table__num">#' . $pos . '</td>';
			$html .= '<td class="legacy-left data-table__name">' . $name . '</td>';
			$html .= '<td class="legacy-center data-table__optional">' . UserTable::renderPremium($row) . '</td>';
			foreach ($valueColumns as $col)
				$html .= '<td class="legacy-center data-table__num">' . $col['render']($row) . '</td>';

			$html .= '</tr>';
			$prevPosition = $pos;
		}

		// Footer with total user count
		if ($totalUsers !== null)
			$html .= '<tr class="data-table__row--separator"><td colspan="' . $totalColumns . '" class="legacy-center highscore-table__total">' . Util::formatNumber($totalUsers) . ' users total</td></tr>';

		$html .= '</table>';
		return $html;
	}
}
