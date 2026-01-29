/**
 * React Query hooks for Issues List page.
 *
 * Handles fetching and mutating global issues list data.
 */

import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { IssuesListResponse, IssueStatusFilter } from './issueTypes';

/**
 * Fetch issues list with filtering and pagination.
 *
 * Query key includes filter and page for proper caching.
 * Uses placeholderData for SSR, refetchOnMount ensures fresh data on filter changes.
 */
export function useIssuesQuery(statusFilter: IssueStatusFilter, page: number)
{
	return useQuery({
		queryKey: ['issues-list', statusFilter, page],
		queryFn: async () =>
		{
			// Use dedicated API endpoint (cleaner than AJAX detection)
			const response = await fetch(`/tsumego-issues/api?status=${statusFilter}&page=${page}`, {
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			});
			if (!response.ok) 
				throw new Error(`Failed to fetch issues: ${response.status}`);

			const data = await response.json();
			return data as IssuesListResponse;
		},
		staleTime: 0, // Always consider stale
		refetchOnMount: true, // Always refetch when component mounts
		refetchOnWindowFocus: true
	});
}

/**
 * Helper to invalidate issues list queries after mutations.
 *
 * Call this after close/reopen/delete actions to refresh the list.
 */
export function useInvalidateIssuesList()
{
	const queryClient = useQueryClient();

	return async () =>
	{
		await queryClient.invalidateQueries({ queryKey: ['issues-list'] });
	};
}
