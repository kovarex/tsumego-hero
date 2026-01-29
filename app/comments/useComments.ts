import { useMutation, useQuery } from '@tanstack/react-query';
import { post, del } from '../shared/api';
import type { Comment, AddCommentRequest, CommentCounts } from './commentTypes';
import { IssueStatus, type IssueStatusId, type Issue } from '../issues/issueTypes';

interface AddCommentVariables
{
	data: AddCommentRequest;
}

interface DeleteCommentVariables
{
	commentId: number;
}

interface ReplyToIssueVariables
{
	issueId: number;
	text: string;
	tsumegoId: number;
	position?: string;
}

interface MakeIssueVariables
{
	commentId: number;
}

interface CloseIssueVariables
{
	issueId: number;
	newStatus: IssueStatusId;
}

export function useAddComment()
{
	return useMutation({
		mutationFn: async ({ data }: AddCommentVariables) =>
		{
			// If reporting as issue, create issue instead
			if (data.report_as_issue)
				return post<{ success: boolean; issue: Issue }>('/tsumego-issues/create', {
					tsumego_id: data.tsumego_id,
					text: data.text,
					position: data.position
				});

			// Regular comment
			return post<Comment>('/tsumego-comments/add', data);
		}
	});
}

export function useDeleteComment()
{
	return useMutation({
		mutationFn: ({ commentId }: DeleteCommentVariables) =>
			del<{ success: boolean }>(`/tsumego-comments/delete/${commentId}`)
	});
}

export function useReplyToIssue()
{
	return useMutation({
		mutationFn: ({ issueId, text, tsumegoId, position }: ReplyToIssueVariables) =>
			post<Comment>('/tsumego-comments/add', {
				text,
				tsumego_id: tsumegoId,
				issue_id: issueId,
				...(position && { position })
			})
	});
}

export function useCloseReopenIssue()
{
	return useMutation({
		mutationFn: ({ issueId, newStatus }: CloseIssueVariables) =>
		{
			const endpoint =
				newStatus === IssueStatus.CLOSED ? `/tsumego-issues/close/${issueId}` : `/tsumego-issues/reopen/${issueId}`;
			return post<{ success: boolean }>(endpoint, { source: 'play' });
		}
	});
}

export function useMakeIssue()
{
	return useMutation({
		mutationFn: async ({ commentId }: MakeIssueVariables) =>
		{
			// CakePHP expects form data in format: data[Model][field]
			const formData = new FormData();
			formData.append('data[Comment][tsumego_issue_id]', 'new');

			const response = await fetch(`/tsumego-issues/move-comment/${commentId}`, {
				method: 'POST',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: formData
			});

			if (!response.ok) 
				throw new Error(`HTTP ${response.status}`);

			return response.json() as Promise<{
				success: boolean;
				issue: Issue;
				comment_id: number;
			}>;
		}
	});
}

/**
 * Query hook for fetching comments data with automatic refetching.
 *
 * @param tsumegoId - The tsumego ID
 * @param initialData - SSR data from PHP (avoids first fetch)
 */
export function useCommentsQuery(
	tsumegoId: number,
	enabled: boolean = true // Only fetch when tab is clicked
)
{
	return useQuery({
		queryKey: ['comments', tsumegoId],
		queryFn: async () =>
		{
			const response = await fetch(`/tsumego-comments/index/${tsumegoId}`, {
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			});
			if (!response.ok) 
				throw new Error(`Failed to fetch comments: ${response.status}`);

			const data = await response.json();
			return data as { issues: Issue[]; standalone: Comment[]; counts: CommentCounts };
		},
		enabled, // Don't fetch until tab clicked
		staleTime: 0,
		refetchOnWindowFocus: true
	});
}
