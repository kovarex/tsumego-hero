import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { post, del, get, postFormData } from '../shared/api';
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
	position?: string;
}

interface MakeIssueVariables
{
	commentId: number;
}

interface MoveCommentVariables
{
	commentId: number;
	targetIssueId: number | 'standalone';
}

interface CloseIssueVariables
{
	issueId: number;
	newStatus: IssueStatusId;
}

/**
 * All comment mutations share the same tsumegoId for invalidation.
 * Pass tsumegoId to create hooks that auto-invalidate the comments query.
 */
export function useCommentMutations(tsumegoId: number)
{
	const queryClient = useQueryClient();
	const invalidate = () => queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });

	const addMutation = useMutation({
		mutationFn: async ({ data }: AddCommentVariables) =>
		{
			if (data.report_as_issue)
				return post<{ success: boolean; issue: Issue }>('/tsumego-issues/create', {
					tsumego_id: data.tsumego_id,
					text: data.text,
					position: data.position
				});
			return post<Comment>('/tsumego-comments/add', data);
		},
		onSuccess: invalidate
	});

	const deleteMutation = useMutation({
		mutationFn: ({ commentId }: DeleteCommentVariables) =>
			del<{ success: boolean }>(`/tsumego-comments/delete/${commentId}`),
		onSuccess: invalidate
	});

	const replyMutation = useMutation({
		mutationFn: ({ issueId, text, position }: ReplyToIssueVariables) =>
			post<Comment>('/tsumego-comments/add', {
				text,
				tsumego_id: tsumegoId,
				issue_id: issueId,
				...(position && { position })
			}),
		onSuccess: invalidate
	});

	const closeReopenMutation = useMutation({
		mutationFn: ({ issueId, newStatus }: CloseIssueVariables) =>
		{
			const endpoint =
				newStatus === IssueStatus.CLOSED ? `/tsumego-issues/close/${issueId}` : `/tsumego-issues/reopen/${issueId}`;
			return post<{ success: boolean }>(endpoint, { source: 'play' });
		},
		onSuccess: invalidate
	});

	const makeIssueMutation = useMutation({
		mutationFn: ({ commentId }: MakeIssueVariables) =>
			postFormData<{ success: boolean; issue: Issue; comment_id: number }>(
				`/tsumego-issues/move-comment/${commentId}`,
				{ 'data[Comment][tsumego_issue_id]': 'new' }
			),
		onSuccess: invalidate
	});

	const moveCommentMutation = useMutation({
		mutationFn: ({ commentId, targetIssueId }: MoveCommentVariables) =>
			postFormData<{ success: boolean }>(
				`/tsumego-issues/move-comment/${commentId}`,
				{ 'data[Comment][tsumego_issue_id]': String(targetIssueId) }
			),
		onSuccess: invalidate
	});

	return {
		addMutation,
		deleteMutation,
		replyMutation,
		closeReopenMutation,
		makeIssueMutation,
		moveCommentMutation,
		invalidate
	};
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
		queryFn: () => get<{ issues: Issue[]; standalone: Comment[]; counts: CommentCounts }>(`/tsumego-comments/index/${tsumegoId}`),
		enabled, // Don't fetch until tab clicked
		staleTime: 0,
		refetchOnWindowFocus: true
	});
}

/**
 * Standalone hooks for use outside tsumego context (e.g., admin issues list).
 * These don't auto-invalidate comments - caller handles invalidation.
 */
export function useCloseReopenIssue()
{
	return useMutation({
		mutationFn: ({ issueId, newStatus }: CloseIssueVariables) =>
		{
			const endpoint =
				newStatus === IssueStatus.CLOSED ? `/tsumego-issues/close/${issueId}` : `/tsumego-issues/reopen/${issueId}`;
			return post<{ success: boolean }>(endpoint, { source: 'issues-list' });
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
