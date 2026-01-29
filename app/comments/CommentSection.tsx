import { useEffect, useRef, useState, useMemo, useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Issue } from '../issues/Issue';
import { Comment } from './Comment';
import { CommentForm } from './CommentForm';
import type { Comment as CommentType, CommentCounts } from './commentTypes';
import { IssueStatus, type IssueStatusId, type Issue as IssueType } from '../issues/issueTypes';
import { useCommentsQuery, useAddComment, useDeleteComment, useReplyToIssue, useCloseReopenIssue } from './useComments';
import { moveComment } from '../shared/api';
import { useSortableDnD } from './useSortableDnD';
import { CommentsListSkeleton } from './CommentSkeleton';
import { ErrorMessage } from '../shared/ErrorMessage';
import { useAuth } from '../shared/AuthContext';

interface CommentSectionProps
{
	tsumegoId: number;
	initialCounts: CommentCounts;
}

export function CommentSection({ tsumegoId, initialCounts }: CommentSectionProps)
{
	const { userId, isAdmin } = useAuth();
	const contentRef = useRef<HTMLDivElement>(null);

	// Local UI state (tabs and visibility)
	const [activeTab, setActiveTab] = useState<'open' | 'closed' | null>(null);
	const [hasEverOpened, setHasEverOpened] = useState(false); // Track if user clicked a tab

	// React Query for comments data - only fetch when user clicks tab
	const queryClient = useQueryClient();
	const commentsQuery = useCommentsQuery(tsumegoId, hasEverOpened);

	// Use query data as source of truth (empty until first fetch)
	const counts = commentsQuery.data?.counts ?? initialCounts;

	// Merge and sort issues + standalone comments chronologically (oldest first)
	const allItems = useMemo(() =>
	{
		// Extract inside memo to avoid stale dependencies
		const issues = commentsQuery.data?.issues ?? [];
		const standalone = commentsQuery.data?.standalone ?? [];
		const items: Array<{
			type: 'issue' | 'comment';
			created: string;
			data: IssueType | CommentType;
		}> = [];

		// Add issues - use EARLIEST comment date for sorting (not issue creation)
		issues.forEach(issue =>
		{
			let sortDate = issue.created; // Fallback to issue creation
			if (issue.comments && issue.comments.length > 0)
			{
				// Comments in issue are ASC, so first is earliest
				const earliestCommentDate = issue.comments[0].created;
				if (new Date(earliestCommentDate) < new Date(sortDate)) 
					sortDate = earliestCommentDate;
			}
			items.push({
				type: 'issue',
				created: sortDate,
				data: issue
			});
		});

		// Add standalone comments
		standalone.forEach(comment =>
		{
			items.push({
				type: 'comment',
				created: comment.created,
				data: comment
			});
		});

		// Sort by date (oldest first)
		items.sort((a, b) => new Date(a.created).getTime() - new Date(b.created).getTime());

		return items;
	}, [commentsQuery.data]);

	// Toggle tab helper - fetch on first click
	const toggleTab = (tab: 'open' | 'closed') =>
	{
		setHasEverOpened(true); // Enable query
		setActiveTab(current => (current === tab ? null : tab));
	};

	// TanStack Query mutations
	const addMutation = useAddComment();
	const deleteMutation = useDeleteComment();
	const replyMutation = useReplyToIssue();
	const closeReopenMutation = useCloseReopenIssue();

	// Reset tabs when switching problems
	useEffect(() =>
	{
		// Don't auto-expand tabs - let user click to expand
		// Reset query enabled state when switching problems
		setActiveTab(null);
		setHasEverOpened(false); // Require user to click tab again
	}, [tsumegoId]);

	// Move comment handler (passed to SortableJS hook)
	const handleMoveComment = useCallback(
		async (commentId: number, targetIssueId: number | 'standalone') =>
		{
			try
			{
				console.log('[Comment DnD] Moving comment:', commentId, 'to:', targetIssueId);
				const result = await moveComment(commentId, targetIssueId);
				if (result.success)
				{
					// Refetch from server - let backend tell us the new state
					console.log('[Comment DnD] Move successful, invalidating queries to refetch');
					await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
				}
			}
			catch (error)
			{
				console.error('[Comment DnD] Move failed:', error);
				alert('Failed to move comment. Please try again.');
			}
		},
		[queryClient, tsumegoId]
	);

	// Initialize SortableJS drag-and-drop (extracted to custom hook)
	useSortableDnD({
		containerRef: contentRef,
		isAdmin,
		tsumegoId,
		issues: commentsQuery.data?.issues ?? [],
		onMoveComment: handleMoveComment
	});

	// Handlers
	const handleAdd = async (text: string, position?: string, reportAsIssue?: boolean) =>
	{
		return new Promise<void>((resolve, reject) =>
		{
			addMutation.mutate(
				{ data: { text, tsumego_id: tsumegoId, position, report_as_issue: reportAsIssue } },
				{
					onSuccess: async () =>
					{
						console.log('[handleAdd] Comment added, refetching from server');
						await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
						resolve();
					},
					onError: error =>
					{
						console.error('[handleAdd] Comment add failed:', error);
						reject(error);
					}
				}
			);
		});
	};

	const handleDelete = async (id: number) =>
	{
		if (!confirm('Delete this comment?')) 
			return;
		await deleteMutation.mutateAsync({ commentId: id });
		console.log('[handleDelete] Comment deleted, refetching from server');
		await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
	};

	const handleReply = async (issueId: number, text: string, position?: string) =>
	{
		return new Promise<void>((resolve, reject) =>
		{
			replyMutation.mutate(
				{ issueId, text, tsumegoId, position },
				{
					onSuccess: async () =>
					{
						console.log('[handleReply] Reply added, refetching from server');
						await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
						resolve();
					},
					onError: error =>
					{
						console.error('[handleReply] Reply failed:', error);
						reject(error);
					}
				}
			);
		});
	};

	const handleCloseReopen = async (issueId: number, newStatus: IssueStatusId) =>
	{
		return new Promise<void>((resolve, reject) =>
		{
			closeReopenMutation.mutate(
				{ issueId, newStatus },
				{
					onSuccess: async () =>
					{
						console.log('[handleCloseReopen] Issue status changed, refetching from server');
						await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
						resolve();
					},
					onError: reject
				}
			);
		});
	};

	const handleMakeIssue = async (commentId: number) =>
	{
		try
		{
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

			const data = (await response.json()) as {
				success: boolean;
				issue: IssueType;
				comment_id: number;
			};
			console.log('[handleMakeIssue] Issue created:', data, 'refetching from server');
			await queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] });
		}
		catch (error)
		{
			console.error('[CommentSection] Make issue failed:', error);
		}
	};

	// Filtering
	const shouldShowItem = (item: IssueType | CommentType, type: 'issue' | 'standalone') =>
	{
		if (!activeTab) 
			return false;
		if (activeTab === 'open')
			return type === 'standalone' || (item as IssueType).tsumego_issue_status_id === IssueStatus.OPEN;

		return type === 'issue' && (item as IssueType).tsumego_issue_status_id === IssueStatus.CLOSED;
	};

	const showContent = activeTab !== null;
	const showForm = activeTab === 'open';
	const hasContent = counts.comments > 0 || counts.openIssues > 0;
	const commentsPart = counts.comments > 0 ? `${counts.comments} COMMENT${counts.comments > 1 ? 'S' : ''}` : '';
	const issuesPart = counts.openIssues > 0 ? `🔴 ${counts.openIssues} OPEN ISSUE${counts.openIssues > 1 ? 'S' : ''}` : '';

	let commentsTabText = 'COMMENTS';
	if (commentsPart && issuesPart) 
		commentsTabText = `${commentsPart} ${issuesPart}`;
	else if (commentsPart) 
		commentsTabText = commentsPart;
	else if (issuesPart) 
		commentsTabText = issuesPart;

	const closedCount = counts.issues - counts.openIssues;
	const closedTabText = closedCount === 0 ? 'CLOSED ISSUES' : `${closedCount} CLOSED ISSUE${closedCount > 1 ? 'S' : ''}`;

	return (
		<>
			<div className="tsumego-comments__tabs">
				<button
					className={`tsumego-comments__tab ${activeTab === 'open' ? 'active' : ''}${!hasContent ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="open"
					onClick={() => toggleTab('open')}
				>
					{commentsTabText}
				</button>
				<button
					className={`tsumego-comments__tab ${activeTab === 'closed' ? 'active' : ''}${closedCount === 0 ? ' tsumego-comments__tab--empty' : ''}`}
					data-filter="closed"
					onClick={() => toggleTab('closed')}
				>
					{closedTabText}
				</button>
			</div>

			<div className="tsumego-comments__content" id="msg2x" ref={contentRef} style={{ display: showContent ? '' : 'none' }}>
				{/* Skeleton loading on initial load */}
				{commentsQuery.isLoading && hasEverOpened && <CommentsListSkeleton />}

				{/* Error state */}
				{commentsQuery.isError && (
					<ErrorMessage
						message="Failed to load comments. Please try again."
						onRetry={() => queryClient.invalidateQueries({ queryKey: ['comments', tsumegoId] })}
					/>
				)}

				{/* Content (shows even during background refetch) */}
				{!commentsQuery.isLoading && commentsQuery.isSuccess && (
					<>
						{allItems.map(item =>
						{
							if (item.type === 'issue')
							{
								const issue = item.data as IssueType;
								return (
									<div
										key={`issue-${issue.id}`}
										style={{
											display: shouldShowItem(issue, 'issue') ? '' : 'none'
										}}
									>
										<Issue
											issue={issue}
											onDelete={handleDelete}
											onReply={handleReply}
											onCloseReopen={handleCloseReopen}
										/>
									</div>
								);
							}
							else
							{
								const comment = item.data as CommentType;
								return (
									<div
										className="tsumego-comment--standalone"
										key={`comment-${comment.id}`}
										style={{
											display: shouldShowItem(comment, 'standalone') ? '' : 'none'
										}}
									>
										<Comment
											comment={comment}
											onDelete={handleDelete}
											onMakeIssue={handleMakeIssue}
											showIssueContext={true}
										/>
									</div>
								);
							}
						})}

						{userId ? (
							<div style={{ display: showForm ? '' : 'none' }}>
								<h4>Add Comment</h4>
								<CommentForm onSubmit={handleAdd} isSubmitting={addMutation.isPending} />
							</div>
						) : (
							<div className="tsumego-comments__login-prompt" style={{ display: showForm ? '' : 'none' }}>
								<p>
									<a href="/users/login">Log in</a> to leave a comment.
								</p>
							</div>
						)}
					</>
				)}
			</div>
		</>
	);
}
