/**
 * React Query hooks for Issues List page.
 *
 * Handles fetching and mutating global issues list data.
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { del, get, post } from '../shared/api';
import { IssueStatus, type IssuesListResponse, type IssueStatusFilter, type IssueStatusId } from './issueTypes';

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
		queryFn: () => get<IssuesListResponse>(`/tsumego-issues/api?status=${statusFilter}&page=${page}`),
		staleTime: 0, // Always consider stale
		refetchOnMount: true, // Always refetch when component mounts
		refetchOnWindowFocus: true
	});
}

interface CloseIssueVariables
{
	issueId: number;
	newStatus: IssueStatusId;
}

interface DeleteCommentVariables
{
	commentId: number;
}

/**
 * Mutations for Issues List page with auto-invalidation.
 */
export function useIssuesMutations()
{
	const queryClient = useQueryClient();
	const invalidate = () => queryClient.invalidateQueries({ queryKey: ['issues-list'] });

	const closeReopenMutation = useMutation({
		mutationFn: ({ issueId, newStatus }: CloseIssueVariables) =>
		{
			const endpoint =
				newStatus === IssueStatus.CLOSED ? `/tsumego-issues/close/${issueId}` : `/tsumego-issues/reopen/${issueId}`;
			return post<{ success: boolean }>(endpoint, { source: 'list' });
		},
		onSuccess: invalidate
	});

	const deleteMutation = useMutation({
		mutationFn: ({ commentId }: DeleteCommentVariables) =>
			del<{ success: boolean }>(`/tsumego-comments/delete/${commentId}`),
		onSuccess: invalidate
	});

	return { closeReopenMutation, deleteMutation, invalidate };
}
