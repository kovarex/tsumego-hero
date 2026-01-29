import { useEffect, useRef, useState, useMemo, useCallback } from 'react';
import { Issue } from '../issues/Issue';
import { Comment } from './Comment';
import { CommentForm } from './CommentForm';
import type { Comment as CommentType, CommentCounts } from './commentTypes';
import { IssueStatus, type IssueStatusId, type Issue as IssueType } from '../issues/issueTypes';
import { useCommentsQuery, useCommentMutations } from './useComments';
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
	const commentsQuery = useCommentsQuery(tsumegoId, hasEverOpened);

	// Consolidated mutations with auto-invalidation (per TkDodo best practices)
	const {
		addMutation,
		deleteMutation,
		replyMutation,
		closeReopenMutation,
		makeIssueMutation,
		moveCommentMutation,
		invalidate
	} = useCommentMutations(tsumegoId);

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

	// Reset UI state when switching problems (intentional reset when prop changes)
	useEffect(() =>
	{
		// eslint-disable-next-line react-hooks/set-state-in-effect
		setActiveTab(null);
		setHasEverOpened(false);
	}, [tsumegoId]);

	// Move comment handler (passed to SortableJS hook)
	const handleMoveComment = useCallback(
		(commentId: number, targetIssueId: number | 'standalone') =>
			moveCommentMutation.mutate({ commentId, targetIssueId }),
		[moveCommentMutation]
	);

	// Initialize SortableJS drag-and-drop (extracted to custom hook)
	useSortableDnD({
		containerRef: contentRef,
		isAdmin,
		tsumegoId,
		issues: commentsQuery.data?.issues ?? [],
		onMoveComment: handleMoveComment
	});

	// Handlers - mutations auto-invalidate via onSuccess
	const handleAdd = async (text: string, position?: string, reportAsIssue?: boolean) =>
	{
		await addMutation.mutateAsync({ data: { text, tsumego_id: tsumegoId, position, report_as_issue: reportAsIssue } });
	};

	const handleDelete = async (id: number) =>
	{
		if (!confirm('Delete this comment?')) 
			return;
		await deleteMutation.mutateAsync({ commentId: id });
	};

	const handleReply = async (issueId: number, text: string, position?: string) =>
	{
		await replyMutation.mutateAsync({ issueId, text, position });
	};

	const handleCloseReopen = async (issueId: number, newStatus: IssueStatusId) =>
	{
		await closeReopenMutation.mutateAsync({ issueId, newStatus });
	};

	const handleMakeIssue = (commentId: number) =>
		makeIssueMutation.mutate({ commentId });

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
						onRetry={invalidate}
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
