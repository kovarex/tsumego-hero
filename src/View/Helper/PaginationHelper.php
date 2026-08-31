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

		$output = '<div id="' . $divId . '" class="pagination">';
		$output .= '<span class="pagination__info">Page ' . $currentPage . ' of ' . $totalPages . '</span>';

		// Previous button
		if ($currentPage > 1)
			$output .= '<a class="pagination__link" href="' . $baseQuery . $paramName . '=' . ($currentPage - 1) . $anchorId . '">« Previous</a>';

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
				$output .= '<span class="pagination__ellipsis">...</span>';
			if ($page == $currentPage)
				$output .= '<span class="pagination__link pagination__link--active">' . $page . '</span>';
			else
				$output .= '<a class="pagination__link" href="' . $baseQuery . $paramName . '=' . $page . $anchorId . '">' . $page . '</a>';
			$lastPage = $page;
		}

		// Next button
		if ($currentPage < $totalPages)
			$output .= '<a class="pagination__link" href="' . $baseQuery . $paramName . '=' . ($currentPage + 1) . $anchorId . '">Next »</a>';

		$output .= '</div>';
		return $output;
	}
}
