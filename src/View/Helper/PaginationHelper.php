<?php

App::uses('AppHelper', 'View/Helper');

/**
 * PaginationHelper - Generates pagination controls
 *
 * Renders page navigation with Previous/Next buttons, page numbers,
 * and ellipsis for large page counts. Automatically preserves query
 * parameters and generates anchor links for smooth scrolling.
 */
class PaginationHelper extends AppHelper
{
	/**
	 * Render pagination controls
	 *
	 * @param int $currentPage Current page number (1-indexed)
	 * @param int $totalPages Total number of pages
	 * @param string $paramName Query parameter name (e.g., 'activity_page')
	 * @return string HTML pagination controls
	 */
	public static function render($currentPage, $totalPages, $paramName)
	{
		if ($totalPages <= 1)
			return '';

		// Auto-generate anchor ID from param name (e.g., 'activity_page' -> '#pagination-activity')
		$anchorId = '#pagination-' . str_replace('_page', '', $paramName);
		$divId = 'pagination-' . str_replace('_page', '', $paramName);

		// Build query string preserving other pagination parameters
		$queryParams = $_GET;
		unset($queryParams[$paramName]); // Remove current param, will add it back with new value
		$baseQuery = empty($queryParams) ? '?' : '?' . http_build_query($queryParams) . '&';

		// Style variables for easy customization
		$containerStyle = 'margin:15px 0; text-align:center;';
		$ellipsisStyle = 'margin:0 5px; color:#999;';

		$output = '<div id="' . $divId . '" style="' . $containerStyle . '">';
		$output .= '<span class="paginator-info">Page ' . $currentPage . ' of ' . $totalPages . '</span>';

		// Previous button
		if ($currentPage > 1)
			$output .= '<a class="paginator-link" href="' . $baseQuery . $paramName . '=' . ($currentPage - 1) . $anchorId . '">« Previous</a>';

		// Show page numbers with reduced range for tighter pagination
		$pages = [];
		$pages[] = 1;
		for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++)
			$pages[] = $i;
		if ($totalPages > 1)
			$pages[] = $totalPages;
		$pages = array_unique($pages);
		sort($pages);

		// Render page numbers with ellipsis
		$lastPage = 0;
		foreach ($pages as $page)
		{
			if ($page > $lastPage + 1)
				$output .= '<span style="' . $ellipsisStyle . '">...</span>';
			if ($page == $currentPage)
				$output .= '<span class="active-paginator-link">' . $page . '</span>';
			else
				$output .= '<a class="paginator-link" href="' . $baseQuery . $paramName . '=' . $page . $anchorId . '">' . $page . '</a>';
			$lastPage = $page;
		}

		// Next button
		if ($currentPage < $totalPages)
			$output .= '<a class="paginator-link" href="' . $baseQuery . $paramName . '=' . ($currentPage + 1) . $anchorId . '">Next »</a>';

		$output .= '</div>';
		return $output;
	}
}
